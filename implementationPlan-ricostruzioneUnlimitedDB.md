# Implementation Plan — Ricostruzione UnlimitedDB (esercizio guidato)

> Questo documento è una guida passo-passo pensata per essere eseguita manualmente da te, uno step alla volta. Ogni fase è divisa in step numerati con: cosa fare, comando/file esatto, perché, e link alla documentazione se qualcosa non torna.
>
> Ambiente di riferimento: progetto in `C:\Users\RickyMandich\PROJECT\unlimiteddb`, Laravel 12.12, PHP 8.2.29 (via cmd.exe, dove sono installati Composer/Laravel), MariaDB, Pest, Laravel Breeze per l'auth, deploy Docker+Traefik su VM Oracle (stesso schema degli altri siti `*.mandich.dev`).

---

## Fase 0 — Setup progetto

**Step 0.1 — Verifica coerenza ambienti PHP**
Hai PHP 8.4.24 in WSL ma Composer/Laravel girano su PHP 8.2.29 (cmd.exe). Decidi ora quale terminale userai per tutto il progetto (composer install, artisan, test) e resta coerente — mescolare i due può creare pacchetti incompatibili nel lockfile. Se vuoi restare con 8.2.29, va benissimo per Laravel 12; annotalo nel README.

**Step 0.2 — Installa i pacchetti core**
```
composer require laravel/breeze --dev
composer require spatie/laravel-permission
```
Perché: **Laravel Breeze** per lo scaffolding auth — a differenza di `laravel/ui` (in maintenance mode, non più evoluto da Laravel) è lo standard attualmente mantenuto e consigliato, e nella variante Blade usa esattamente lo stack Blade + Alpine.js già scelto per il progetto (niente Livewire), quindi non introduce nulla in più rispetto a quanto serve davvero. `spatie/laravel-permission` per i permessi granulari (vedi motivazione in fondo al documento, sezione Analisi).

**Step 0.3 — Configura `.env`**
Verifica/aggiorna in `.env`:
```
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unlimiteddb
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
```
`QUEUE_CONNECTION=database` è la scelta di partenza (niente Redis da gestire in più); si può cambiare in seguito senza toccare il codice dei job.

**Step 0.4 — Crea le tabelle base**
```
php artisan queue:table
php artisan queue:failed-table
php artisan migrate
```
Crea le tabelle `jobs` e `failed_jobs` necessarie per il driver queue `database`.

**Step 0.5 — Pubblica e migra le tabelle di Spatie**
```
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```
Riferimento: https://spatie.be/docs/laravel-permission/v6/installation-laravel

**Step 0.6 — Verifica che tutto giri**
```
php artisan serve
```
apri `http://127.0.0.1:8000` e controlla che la pagina di default carichi senza errori.

**Step 0.7 — Aggancia `.env-overrides` (pattern già usato in SWUDB)**
Il file `.env-overrides` esiste già nella root del progetto (creato dal boilerplate) ma non era ancora agganciato — fatto in questo step.

In `bootstrap/app.php`, **prima** di `Application::configure(...)`:
```php
// Carica le variabili non sensibili (es. APP_VERSION_*) da .env-overrides,
// file tracciato in Git a differenza di .env. Va fatto PRIMA che Laravel
// processi il suo .env principale: il repository usato da Laravel per
// leggere il .env e' immutabile e non sovrascrive variabili gia' presenti
// in $_ENV/$_SERVER, quindi impostandole qui vincono su quelle (se presenti)
// nel .env vero e proprio.
\Dotenv\Dotenv::createMutable(dirname(__DIR__), '.env-overrides')->safeLoad();
```
Verifica che `.env-overrides` **non** sia in `.gitignore` (deve restare tracciato in Git, a differenza di `.env`) — nel progetto attuale è già così, nessuna modifica necessaria a `.gitignore`.

Contenuto di `.env-overrides` (versione iniziale del progetto):
```
APP_VERSION_TYPE=""
APP_VERSION_PRIMARY=0
APP_VERSION_SECONDARY=1
APP_VERSION_TERTIARY=0
APP_VERSION="${APP_VERSION_TYPE}${APP_VERSION_PRIMARY}.${APP_VERSION_SECONDARY}.${APP_VERSION_TERTIARY}"
```
Aggiorna `APP_VERSION_*` ad ogni release, così `APP_VERSION` resta leggibile anche in produzione (utile per debug/notifiche bot) senza esporre dati sensibili in Git.

☐ Fase 0 completata

---

## Fase 0bis — Ambiente Docker locale per i test (porta 66, senza Traefik)

> Obiettivo: poter testare l'app in Docker durante tutto lo sviluppo, con build/comportamento identici a quelli che avrà in produzione (Fase 7), ma senza dipendere da Traefik: nginx pubblica direttamente la porta sull'host.

**Step 0bis.1 — Crea il `Dockerfile`** nella root del progetto (stesso schema multi-stage già validato in SWUDB: build asset con Node, dipendenze PHP con Composer, immagine finale PHP-FPM):
```dockerfile
# --- Stage 1: build frontend assets con Vite ---
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/
RUN npm run build

# --- Stage 2: dipendenze PHP con Composer ---
# Si usa la stessa immagine php:8.4-fpm-alpine dello stage finale (non
# l'immagine standalone "composer:2", che porta con se' un PHP proprio e puo'
# cambiarne la versione senza preavviso, causando incompatibilita' col
# composer.lock del progetto). Composer viene copiato come binario.
FROM php:8.4-fpm-alpine AS composer-builder
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stage 3: immagine finale PHP-FPM ---
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    libpng-dev libzip-dev libxml2-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath xml gd zip

WORKDIR /var/www/html

COPY --from=composer-builder /app /var/www/html
COPY --from=node-builder /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
EXPOSE 9000
CMD ["php-fpm"]
```
Nota: l'immagine usa `php:8.4-fpm-alpine`, la versione più recente supportata da Laravel 12 — non deve necessariamente coincidere con la versione PHP che usi in locale per Composer/Artisan (8.2.29): quella conta solo per il vincolo minimo `^8.2` nel `composer.json`, l'immagine Docker può tranquillamente usarne una più recente e beneficiare delle ultime patch di sicurezza/performance.

**Step 0bis.2 — Crea `docker/entrypoint.sh`**:
```sh
#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
```

**Step 0bis.3 — Crea `docker/nginx/default.conf`**:
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;
    charset utf-8;

    client_max_body_size 20m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    if (!-d $request_filename) {
        rewrite ^/(.+)/$ /$1 permanent;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
`server_name localhost` invece del dominio reale, dato che qui non c'e' Traefik a instradare per hostname.

**Step 0bis.4 — Crea `docker-compose.dev.yml`** nella root del progetto:
```yaml
services:
  app:
    build: .
    container_name: unlimiteddb_app_dev
    restart: unless-stopped
    volumes:
      - ./storage:/var/www/html/storage
      - ./.env:/var/www/html/.env:ro
    environment:
      - DB_HOST=db
      - DB_DATABASE=unlimiteddb
      - DB_USERNAME=unlimiteddb
      - DB_PASSWORD=${DB_PASSWORD}
    networks:
      - internal
    depends_on:
      db:
        condition: service_healthy

  nginx:
    image: nginx:alpine
    container_name: unlimiteddb_nginx_dev
    restart: unless-stopped
    ports:
      - "66:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
    networks:
      - internal
    depends_on:
      - app

  db:
    image: mariadb:11
    container_name: unlimiteddb_db_dev
    restart: unless-stopped
    environment:
      - MYSQL_DATABASE=unlimiteddb
      - MYSQL_USER=unlimiteddb
      - MYSQL_PASSWORD=${DB_PASSWORD}
      - MYSQL_RANDOM_ROOT_PASSWORD=yes
    volumes:
      - db_data_dev:/var/lib/mysql
    networks:
      - internal
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10
      start_period: 10s

networks:
  internal:
    driver: bridge

volumes:
  db_data_dev:
```
Differenze rispetto al `docker-compose.yml` di produzione che creerai nella Fase 7 (stessa logica, stesso Dockerfile, stessa conf nginx):
- niente rete esterna `proxy` ne' label `traefik.*` sul servizio `nginx`
- `nginx` pubblica direttamente `"66:80"` sull'host invece di essere instradato da Traefik
- nomi container/volume con suffisso `_dev` per non entrare in conflitto se mai avvii anche lo stack di produzione sulla stessa macchina

Nota sulla password: a differenza del compose di SWUDB (che usava `MYSQL_ALLOW_EMPTY_PASSWORD=yes`), qui viene richiesta una password reale tramite la variabile `DB_PASSWORD` letta dal tuo `.env` — anche in locale costa zero avere una password vera, e riduce il rischio se in futuro esponi per sbaglio la porta del DB. Assicurati che `DB_PASSWORD` in `.env` sia valorizzato prima di avviare il compose.

**Step 0bis.5 — Avvia e verifica**
```
docker compose -f docker-compose.dev.yml up --build -d
```
apri `http://localhost:66` e verifica che la pagina carichi. Per i log: `docker compose -f docker-compose.dev.yml logs -f app`.

**Step 0bis.6 — Mantieni sincronizzati Dockerfile e nginx conf con la Fase 7**
Quando in Fase 7 creerai il `docker-compose.yml` di produzione, riuserai lo stesso `Dockerfile` e lo stesso `docker/nginx/default.conf` di questo step (con `server_name` aggiornato al dominio reale) — se in futuro modifichi uno dei due file, verifica che il comportamento resti equivalente in entrambi gli ambienti.

☐ Fase 0bis completata

---

## Fase 1 — Autenticazione e permessi

**Step 1.1 — Installa lo scaffolding di Breeze**
```
php artisan breeze:install blade
npm install && npm run build
php artisan migrate
```
(la variante `blade` usa Blade + Alpine.js, coerente con la scelta già presa di non usare Livewire; Breeze crea anche le migration standard per `users`, `password_reset_tokens`, ecc.)

**Step 1.2 — Aggiungi il trait `HasRoles` al modello User**
In `app/Models/User.php`:
```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

**Step 1.3 — Definisci il set iniziale di permessi via seeder**
Crea `database/seeders/PermissionSeeder.php`:
```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'cards.import',
            'cards.manage',
            'decks.manage-any',
            'collections.manage-any',
            'users.manage',
            'bot.notifications.receive',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $admin = Role::findOrCreate('admin');
        $admin->givePermissionTo($permissions);
    }
}
```
Registra il seeder in `database/seeders/DatabaseSeeder.php` e lancia:
```
php artisan db:seed --class=PermissionSeeder
```
Perché array di permessi singoli e non ruoli hardcoded: puoi assegnare a un utente esattamente i permessi che ti servono, senza dover creare un nuovo "ruolo" ogni volta che cambia una combinazione. Il ruolo `admin` qui è solo una scorciatoia per assegnare tutti i permessi in un colpo.

**Step 1.4 — Assegna il ruolo admin al tuo utente**
Via `php artisan tinker`:
```php
$user = App\Models\User::find(1);
$user->assignRole('admin');
```

**Step 1.5 — Proteggi le rotte/azioni con i permessi**
Esempio in una route:
```php
Route::middleware(['auth', 'permission:cards.manage'])->group(function () {
    // rotte gestione carte
});
```
oppure nel codice:
```php
if (! $user->can('cards.manage')) {
    abort(403);
}
```
Riferimento: https://spatie.be/docs/laravel-permission/v6/basic-usage/middleware

☐ Fase 1 completata

---

## Fase 2 — Catalogo carte e import via queue

**Step 2.1 — Migration `sets` e `cards`**
```
php artisan make:model Set -m
php artisan make:model Card -m
```
Nella migration di `cards`, colonne indicative: `name_it`, `name_en`, `set_id` (FK), `rarity`, `type`, `text`, `cost`, `aspects` (json), `image_url`, `external_id` (id della carta secondo l'API ufficiale SWU, utile per il matching in fase di import/aggiornamento).

**Step 2.2 — Crea il job di import**
```
php artisan make:job ImportCardsFromSwuApiJob
```
Struttura minima:
```php
class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    public function handle(): void
    {
        // 1. chiama l'API ufficiale SWU (Http::get(...))
        // 2. per ogni carta ricevuta, updateOrCreate su Card usando external_id come chiave
        // 3. dispaccia NotifyAdminJob con il riepilogo (nuove carte trovate, eventuali errori)
    }
}
```
`$tries` e `$backoff` sostituiscono la gestione manuale dei retry che facevi con le chiamate ricorsive.
Riferimento: https://laravel.com/docs/12.x/queues#creating-jobs

**Step 2.3 — Comando Artisan che dispaccia il job**
```
php artisan make:command ScanCards
```
```php
class ScanCards extends Command
{
    protected $signature = 'cards:scan';

    public function handle(): void
    {
        ImportCardsFromSwuApiJob::dispatch();
        $this->info('Scan carte accodato.');
    }
}
```
Questo comando sarà richiamato sia dal bot (comando `/scan`) sia dallo scheduler — logica scritta una sola volta.

**Step 2.4 — Schedula lo scan periodico**
In `routes/console.php` (Laravel 12 usa questo file invece di `app/Console/Kernel.php`):
```php
Schedule::command('cards:scan')->weeklyOn(1, '00:00');
```
Riferimento: https://laravel.com/docs/12.x/scheduling#scheduling-artisan-commands

**Step 2.5 — Avvia il worker in locale per testare**
```
php artisan queue:work
```
in un terminale separato, poi lancia `php artisan cards:scan` in un altro e osserva il worker processare il job.

☐ Fase 2 completata

---

## Fase 3 — Gestione mazzi multi-formato

**Step 3.1 — Enum formato mazzo**
Crea a mano `app/Enums/DeckFormat.php`:
```php
enum DeckFormat: string
{
    case Premier = 'premier';
    case Eternal = 'eternal';
    case TwinSuns = 'twin_suns';
}
```

**Step 3.2 — Migration `decks` e `deck_cards`**
```
php artisan make:model Deck -m
php artisan make:migration create_deck_cards_table
```
`decks`: `user_id`, `name`, `format` (string, castato a `DeckFormat`), `leader_card_id`, `base_card_id`, `is_public` (bool).
`deck_cards`: `deck_id`, `card_id`, `quantity`.

Nel modello `Deck`:
```php
protected $casts = [
    'format' => DeckFormat::class,
];
```
Riferimento: https://laravel.com/docs/12.x/eloquent-mutators#enum-casting

**Step 3.3 — Interfaccia e classi di validazione per formato**
```php
interface DeckFormatValidator
{
    public function validate(Deck $deck): array; // ritorna array di errori, vuoto se valido
}
```
Crea `PremierFormatValidator`, `EternalFormatValidator`, `TwinSunsFormatValidator` in `app/Services/DeckValidation/`, ciascuna con le proprie regole (limiti di copie per carta, leader/base ammessi, ecc. — da definire in base al regolamento ufficiale del formato).

**Step 3.4 — Factory per scegliere il validator giusto**
```php
class DeckFormatValidatorFactory
{
    public static function make(DeckFormat $format): DeckFormatValidator
    {
        return match ($format) {
            DeckFormat::Premier => new PremierFormatValidator(),
            DeckFormat::Eternal => new EternalFormatValidator(),
            DeckFormat::TwinSuns => new TwinSunsFormatValidator(),
        };
    }
}
```
Così per aggiungere un quarto formato in futuro aggiungi solo un case all'enum + una classe, senza toccare il resto.

**Step 3.5 — Policy per l'autorizzazione**
```
php artisan make:policy DeckPolicy --model=Deck
```
```php
public function update(User $user, Deck $deck): bool
{
    return $user->id === $deck->user_id || $user->can('decks.manage-any');
}
```
Riferimento: https://laravel.com/docs/12.x/authorization#creating-policies

☐ Fase 3 completata

---

## Fase 4 — Gestione collezione

**Step 4.1 — Migration `collection_cards`**
```
php artisan make:migration create_collection_cards_table
```
Colonne: `user_id`, `card_id`, `quantity`. Non serve una tabella `collections` separata se la collezione è implicitamente "tutte le collection_cards di un utente".

**Step 4.2 — UI di gestione**
Pagina con ricerca carte (riusa i filtri della Fase 6) + bottone incrementa/decrementa quantità posseduta, salvato via una piccola interazione Alpine.js senza reload pagina.

**Step 4.3 — Funzione "carte mancanti per un mazzo"**
Query che confronta `deck_cards` del mazzo con `collection_cards` dell'utente e restituisce il delta — buon differenziatore rispetto a un semplice database carte.

☐ Fase 4 completata

---

## Fase 5 — Bot Telegram

**Step 5.1 — Libreria per l'API Telegram**
Usa direttamente la facade `Http` nativa di Laravel (https://laravel.com/docs/12.x/http-client) per chiamare l'API Telegram, senza aggiungere una libreria esterna dedicata. La vecchia versione (SWUDB) usava il pacchetto `telegram-bot/api`, ma l'ecosistema dei wrapper PHP per Telegram di quella fascia è in gran parte poco mantenuto o esplicitamente abbandonato (es. `vjik/telegram-bot-api`, deprecato dallo stesso autore) — per un bot con poche funzioni (scan, ricerca, notifica admin) non c'è un vero vantaggio nell'aggiungere quella dipendenza, mentre con `Http` hai pieno controllo e zero rischio di dover rimpiazzare un pacchetto abbandonato in futuro.
Esempio invio messaggio:
```php
Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
    'chat_id' => $chatId,
    'text' => $text,
]);
```

**Step 5.2 — Webhook controller**
```
php artisan make:controller TelegramController
```
Riceve gli update di Telegram via webhook, instrada in base al comando (`/scan`, `/search <query>`).

**Step 5.3 — Comando `/scan` dal bot**
Nel metodo che gestisce `/scan`, richiama la stessa logica della Fase 2:
```php
Artisan::call('cards:scan');
```
oppure dispaccia direttamente `ImportCardsFromSwuApiJob::dispatch()` — nessuna logica duplicata rispetto allo scan schedulato.

**Step 5.4 — Comando `/search`**
Query su `Card` (nome IT/EN, `LIKE` o full-text se il volume di carte lo giustifica), risposta formattata in Markdown Telegram con nome, set, testo carta.

**Step 5.5 — Job di notifica admin**
```
php artisan make:job NotifyAdminJob
```
```php
public function handle(): void
{
    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
        'chat_id' => config('services.telegram.admin_chat_id'),
        'text' => $this->message,
    ]);
}
```
Richiamato da `ImportCardsFromSwuApiJob` a fine scan e da qualunque altro evento critico vorrai monitorare.

☐ Fase 5 completata

---

## Fase 6 — UI/UX e funzioni comuni TCG

**Step 6.1 — Ricerca/filtri carte**
Form con filtri per set, aspetto, tipo, costo, testo libero; query Eloquent con `when()` per applicare i filtri solo se presenti.

**Step 6.2 — Statistiche mazzo**
Pagina che mostra, per un mazzo: curva dei costi (grafico semplice), distribuzione per aspetto/tipo.

**Step 6.3 — Separazione viste pubbliche/autenticate**
Definisci chiaramente nelle rotte quali sono accessibili senza login (catalogo, mazzi pubblici) e quali richiedono `auth` (creare/modificare mazzi, collezione).

☐ Fase 6 completata

---

## Fase 7 — Deploy

**Step 7.1 — Dockerfile applicativo**
Riusa la struttura già validata in [[mandich-dev-infra]] per gli altri siti `*.mandich.dev`.

**Step 7.2 — Servizio queue worker**
Nel `docker-compose`, aggiungi un servizio separato che lancia `php artisan queue:work --tries=3` in loop (o usa Supervisor nello stesso container applicativo) — deve restare sempre attivo, a differenza del container web che risponde solo alle richieste HTTP.

**Step 7.3 — Scheduler**
Assicurati che un vero cron di sistema (nel container) lanci `php artisan schedule:run` ogni minuto — è il meccanismo standard con cui Laravel esegue poi `cards:scan` alla frequenza configurata nella Fase 2.

**Step 7.4 — Integrazione Traefik**
Segui `new-site.sh` come per gli altri siti, dominio `unlimiteddb.mandich.dev`.

**Step 7.5 — Verifica post-deploy**
- il webhook Telegram punta al dominio giusto
- il queue worker sta effettivamente consumando i job (controlla `failed_jobs` per errori)
- lo scan schedulato parte al lunedì a mezzanotte come da requisito

☐ Fase 7 completata

---

## Note di analisi (perché queste scelte)

- **Queue reali invece di fireAndForget**: il vecchio sistema simulava thread con richieste HTTP POST ricorsive per aggirare l'assenza di code su Altervista — fragile, senza retry strutturato, errori persi nella risposta scartata. Le queue di Laravel danno retry/backoff/failed-jobs nativi. https://laravel.com/docs/12.x/queues
- **Permessi granulari (Spatie)**: permessi singoli assegnabili liberamente, i "ruoli" sono solo scorciatoie per assegnarne un gruppo insieme, non autorità hardcoded nel codice. https://spatie.be/docs/laravel-permission/v6/introduction
- **Enum + Strategy per i formati mazzo**: evita `if/else` sparsi, aggiungere un formato futuro richiede solo una nuova classe, non modifiche al codice esistente.
- **Blade + Alpine.js invece di Livewire**: nella vecchia versione Livewire risultava lento e senza feedback di caricamento adeguato; Blade classico è più prevedibile e più semplice da debuggare per le poche interazioni dinamiche necessarie.
- **`.env-overrides`**: pattern già in uso in SWUDB per tenere in Git (a differenza di `.env`) l'`APP_VERSION`, utile per riconoscere subito quale versione sia effettivamente in produzione.

## Riferimenti documentazione Laravel 12

| Argomento | Link |
|---|---|
| Queue | https://laravel.com/docs/12.x/queues |
| Scheduling | https://laravel.com/docs/12.x/scheduling |
| Autenticazione | https://laravel.com/docs/12.x/authentication |
| Autorizzazione (Gates/Policies) | https://laravel.com/docs/12.x/authorization |
| Migrations | https://laravel.com/docs/12.x/migrations |
| Eloquent relationships | https://laravel.com/docs/12.x/eloquent-relationships |
| Eloquent casting (enum) | https://laravel.com/docs/12.x/eloquent-mutators#enum-casting |
| Validazione | https://laravel.com/docs/12.x/validation |
| HTTP Client | https://laravel.com/docs/12.x/http-client |
| Testing (Pest) | https://laravel.com/docs/12.x/testing |
| Spatie Laravel-permission | https://spatie.be/docs/laravel-permission/v6/introduction |
