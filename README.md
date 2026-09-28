# UnlimitedDB

Database delle carte di **Star Wars: Unlimited** in italiano, con (a regime) gestione dei mazzi, gestione della
collezione personale e le altre funzioni tipiche dei siti database per TCG, più un bot Telegram per la scansione
delle nuove carte e le notifiche all'admin.

Sito: <https://unlimiteddb.mandich.dev>

> Questo repository è la **ricostruzione da zero** del vecchio progetto SWUDB (che girava su Altervista e
> usava soluzioni pezzotte come il `fireAndForget` con POST HTTP ricorsive). Qui si usano code reali,
> permessi granulari e le best practice di Laravel. La vecchia versione, in produzione fino al cambio,
> sta nel progetto separato `SWUDB`.

> **Questo README descrive il progetto così com'è oggi**, non come sarà a fine implementation plan.
> Ciò che non funziona ancora è indicato esplicitamente ("Non ancora implementato"). La roadmap sta in
> [`todo.md`](todo.md) e nel piano [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).

## Stato attuale

**Funziona**
- Registrazione/login (Breeze) con verifica email, profilo utente.
- Permessi granulari (spatie/laravel-permission) e ruolo `admin`.
- Pagina admin utenti (`/admin/utenti`).
- Pagina admin errori di sistema (`/admin/errori`): lista con filtro per stato, cambio stato singolo e bulk.
- Comando `cards:scan` + `ImportCardsFromSwuApiJob` (import carte, espansioni, aspetti, tratti e download immagini),
  schedulato ogni lunedì 00:00 (vedi "Scansione carte").
- Email `NewCardsEmail` e `AdminScanReportEmail`, con anteprima su `/render-mail/{type}`.
- Worker delle code (servizio `worker` nel compose di sviluppo).

**Non ancora implementato / non funzionante**
- `App\Services\TelegramService` **non esiste**, ma `ImportCardsFromSwuApiJob` lo usa (Fase 9.2 del `todo.md`):
  finché non viene creato il job fallisce appena parte, quindi anche `cards:scan`. Il bot Telegram (webhook,
  comandi, ricerca carte, `NotifyAdminJob`) non esiste.
- Vista di dettaglio errore: rotta e controller ci sono, ma `resources/views/admin/errors/show.blade.php` è ancora vuota.
- Mazzi: esistono solo tabelle e modelli (`Deck`, `DeckCard`); **nessuna rotta, pagina, policy, enum di formato o
  validatore**. `Deck::cards()`, `Deck::leader()` e `Deck::base()` sono da correggere (vedi `todo.md`, Fase 7).
- Collezione, admin espansioni (i dati di `expansions` si correggono solo a mano sul DB), ricerca carte,
  statistiche, API pubblica: non iniziati.
- Nessuna pagina pubblica oltre alla `welcome` di default e alle pagine di autenticazione.
- Nessun servizio scheduler nel compose di sviluppo: `cards:scan` parte da solo solo se qualcosa esegue
  `schedule:run`/`schedule:work` (in produzione da verificare, vedi Fase 12 del `todo.md`).

## Stack

| Livello | Tecnologia |
|---|---|
| Backend | PHP 8.2, Laravel 12 |
| Autenticazione | Laravel Breeze (Blade) con verifica email |
| Permessi | `spatie/laravel-permission` 6.x |
| Database | MariaDB 11 |
| Code / cache / sessioni | driver `database` |
| Email | Resend (`resend/resend-php`) |
| Frontend | Blade, Tailwind CSS 3, Alpine.js, Vite 7 |
| Test | Pest 3 |
| Infrastruttura | Docker (php-fpm + nginx + mariadb + worker), Traefik su VM Oracle in produzione |

## Struttura delle cartelle

```
app/
  Console/Commands/ScanCards.php      comando `cards:scan` (mette in coda l'import)
  Http/Controllers/Admin/             SystemErrorController, UserManagementController
  Http/Controllers/Auth/              controller Breeze
  Jobs/ImportCardsFromSwuApiJob.php   import carte dall'API ufficiale SWU
  Mail/                               NewCardsEmail, AdminScanReportEmail
  Models/                             Aspect, Card, CardTrait, Deck, DeckCard, Expansion, SystemError, User
  Services/CardImageDownloader.php    scarica le immagini carta in storage
bash/                                 script di commit/versionamento/FTP (vedi "Versionamento")
database/migrations, seeders          schema e PermissionSeeder
docker/                               entrypoint.sh, nginx/default.conf, mysql/init.sql e init.dev.sql
resources/views/                      viste Blade (admin/users, admin/errors, auth, profile, ...)
routes/web.php, routes/console.php    rotte web e schedulazione
tests/                                test Pest (Feature/Auth, Feature/Jobs, ProfileTest, ...)
.agent/rules/*.md                     regole specifiche del progetto (da rispettare sempre)
.env-overrides                        variabili NON sensibili tracciate in Git (versione app)
todo.md                               roadmap/avanzamento
implementationPlan-*.md               piani di implementazione (vedi "Convenzioni")
```

## Setup locale

Prerequisiti: Docker Desktop. (PHP/Composer servono solo per lanciare `artisan` fuori da Docker: il progetto
richiede PHP `^8.2`.)

```bash
cp .env.example .env
# imposta APP_KEY nel .env: il container monta il .env in sola lettura, quindi key:generate
# dentro il container non può scriverlo. Genera la chiave con:
#   docker compose -f docker-compose.dev.yml run --rm app php artisan key:generate --show
# e incollala in APP_KEY=
docker compose -f docker-compose.dev.yml up -d --build
docker compose -f docker-compose.dev.yml exec app php artisan migrate --seed
```

Il sito risponde su <http://localhost:66>. Servizi del compose di sviluppo: `app` (php-fpm), `nginx`,
`db` (MariaDB, senza password), `worker` (`queue:work`). Tutti con `restart: unless-stopped`.

`docker-compose.dev.yml` è il compose di **sviluppo**; quello di produzione (`docker-compose.yml`) viene
generato sul server da `new-site.sh` (infrastruttura `mandich.dev`) e non sta in questa cartella.

`migrate --seed` crea permessi e ruolo `admin` (`PermissionSeeder`) e un utente di test
`test@example.com` (`DatabaseSeeder`, via `UserFactory`).

### Asset frontend
Gli asset Vite sono compilati nello stage `node-builder` del `Dockerfile` e l'`entrypoint.sh` li copia in
`public/build` a ogni avvio del container `app`: basta `up -d --build` per averli aggiornati.
In alternativa, fuori da Docker: `npm install && npm run dev`.

### Primo utente admin
Per promuovere un utente registrato al ruolo `admin`:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan tinker
>>> App\Models\User::where('email', 'tua@email')->first()->assignRole('admin');
```

## Variabili d'ambiente

`.env` (non tracciato, contiene i segreti; base: `.env.example`) e `.env-overrides` (tracciato, solo valori non
sensibili). `bootstrap/app.php` carica `.env-overrides` **prima** del `.env`, quindi le sue variabili vincono.

| Variabile | Uso |
|---|---|
| `DB_*` | connessione MariaDB (in Docker `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` sono impostate dal compose) |
| `QUEUE_CONNECTION=database` | le code girano nel worker (default di `.env.example`) |
| `MAIL_MAILER`, `RESEND_API_KEY` | `.env.example` imposta `MAIL_MAILER=log`; per inviare email vere serve `resend` + la chiave (dominio `mandich.dev` verificato su Resend) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | mittente delle email |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ADMIN_CHAT_ID`, `TELEGRAM_WEBHOOK_SECRET` | lette da `config/services.php`; il job di scan usa `TELEGRAM_ADMIN_CHAT_ID` (non presenti in `.env.example`) |
| `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_PORT` | solo per gli script FTP in `bash/` |
| `APP_VERSION_*` (in `.env-overrides`) | versione dell'app, gestita da `bash/all.sh` |

## Database

Migrazioni in `database/migrations`. Tabelle:

- `users` + tabelle di spatie/permission (ruoli e permessi).
- `expansions`: PK `expansion` (codice, max 10 caratteri); `legal_date`; `rotation` (1 carattere, default `'0'`,
  finestra di rotazione: in formato Premier sono giocabili le ultime due); `confirmed` (dati controllati da un
  admin); `group_main_expansion` (auto-riferimento all'espansione principale del gruppo; nullo per i set standalone).
  Il job crea le espansioni nuove con `rotation` = massima rotazione esistente e `legal_date` = data di pubblicazione
  dell'API (valori approssimativi da correggere a mano).
- `cards`: PK composta (`expansion`, `number`) e `cid` univoco (`cardUid` dell'API); FK `expansion` →
  `expansions.expansion`. Il modello `Card` usa `cid` come `$primaryKey` Eloquent (non supporta PK composite).
  `front_art_path`/`back_art_path` sono path relativi nel disk `public`.
- `aspects` + `card_aspect`; `traits` (PK `name`) + `card_trait`: aspetti e tratti normalizzati.
- `decks` (`user_id`, `name`, `format` premier/eternal/twin_suns, `is_public`, `assembled`, `version`,
  `previous_version_id`) e `deck_cards` (PK `deck_id`+`cid`, `quantity`). Il ruolo Leader/Base non è una colonna:
  si deduce da `cards.type`. Vedi "Non ancora implementato" per lo stato della funzionalità.
- `system_errors`: `source`, `message`, `stack_trace`, `context` (json), `status` (`open`/`ignored`/`resolved`),
  `resolved_at` (valorizzato solo quando lo stato è `resolved`).
- `jobs`, `failed_jobs`, `cache`, `sessions`: infrastruttura Laravel.

## Autenticazione e permessi

Registrazione/login con Breeze, email da verificare. Permessi definiti in `PermissionSeeder`:
`cards.import`, `cards.manage`, `decks.manage-any`, `collections.manage-any`, `users.manage`,
`bot.notifications.receive`, `mails.test`, `system.manage-errors`. Il ruolo `admin` li ha tutti.

Oggi le rotte ne usano solo tre (`users.manage`, `system.manage-errors`, `mails.test`); gli altri sono definiti in
vista delle funzionalità future. Con Laravel 12 + spatie 6.x gli alias middleware `role`/`permission`/
`role_or_permission` sono registrati a mano in `bootstrap/app.php`.

## Rotte (`routes/web.php`)

| Rotta | Accesso | Descrizione |
|---|---|---|
| `GET /` | pubblica | `welcome` di default |
| `GET /dashboard` | login + email verificata | dashboard |
| `/profile` (GET, PATCH, DELETE) | login | profilo utente (Breeze) |
| rotte di `routes/auth.php` | — | login, registrazione, reset password, verifica email (Breeze) |
| `GET /admin/utenti`, `GET /admin/utenti/{user}/modifica`, `PUT /admin/utenti/{user}` | `users.manage` | gestione utenti |
| `GET /admin/errori` | `system.manage-errors` | lista errori (filtro `?status=`) |
| `GET /admin/errori/{systemError}` | `system.manage-errors` | dettaglio errore (**vista ancora vuota**) |
| `PATCH /admin/errori/{systemError}`, `PATCH /admin/errori/bulk` | `system.manage-errors` | cambio stato singolo / bulk |
| `GET /render-mail/{type}` | `mails.test` | anteprima email (`new-cards`, `admin-scan-report`) |

## Scansione carte

1. `routes/console.php` schedula `cards:scan` ogni **lunedì alle 00:00** (serve uno scheduler che giri).
2. `cards:scan` mette in coda `ImportCardsFromSwuApiJob`.
3. Il **worker** (`php artisan queue:work`) esegue il job: invia un messaggio di avanzamento alla chat admin
   Telegram (tramite `TelegramService`, **non ancora esistente**, vedi sopra), interroga
   `https://admin.starwarsunlimited.com/api/card-list` (locale `it`, pagine da 40), fa upsert di espansioni,
   carte, aspetti e tratti e scarica le immagini con `CardImageDownloader`.
4. Gli errori finiscono in `system_errors` (anche i "carta già presente, dati aggiornati", con stato `ignored`);
   a fine scan partono le email `NewCardsEmail` (a tutti gli utenti, se ci sono nuove carte) e
   `AdminScanReportEmail` (agli utenti con ruolo `admin`, se ci sono errori).
5. Eccezione hardcoded nel job: la carta JTL #256 ha `max_copies` = 15.

Lancio manuale: `docker compose -f docker-compose.dev.yml exec app php artisan cards:scan`.

## Immagini delle carte

Salvate nel disk `public` (`storage/app/public/cards/{expansion}/{number}-front|back.{ext}`, ignorate da Git).
Niente `storage:link` in Docker: nginx le serve con l'alias `/storage/` (`docker/nginx/default.conf`) e
`./storage/app/public` è montato nel servizio `nginx` del compose di sviluppo (in produzione, secondo `todo.md`, idem).

## Test

```bash
docker compose -f docker-compose.dev.yml exec app php artisan test
# oppure, con PHP locale: composer test
```

Framework: Pest. Ci sono i test di autenticazione/profilo di Breeze e `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`
per il job di import.

## Deploy

Automatizzato: **il merge sul branch `laravel` fa partire la pipeline** che ricostruisce e riavvia i container
sulla VM Oracle (infrastruttura Docker + Traefik dei siti `*.mandich.dev`). In produzione c'è anche il servizio
`worker`; l'utente MySQL deve essere autorizzato sulla subnet dei container (`utente@172.23.0.%`, vedi
`docker/mysql/init.sql`), non su un IP singolo.
Da verificare post-deploy (aperto in `todo.md`): worker `Up`, `failed_jobs` vuota, scan schedulato, immagini
raggiungibili su `/storage/...`.

## Versionamento e script

La versione sta in `.env-overrides` (`APP_VERSION_PRIMARY/SECONDARY/TERTIARY`). Script in `bash/`:

| Script | Cosa fa |
|---|---|
| `bash/all.sh` | incrementa la versione e chiama `cmt.sh`. `-v` major (resetta minor e patch), `-p` minor (resetta patch), senza opzioni incrementa la patch; `-n` non fa `git add .`; `-m "msg"` messaggio aggiuntivo |
| `bash/cmt.sh` | `git add .` + commit `aggiornamento <data> [<versione>]` + push |
| `bash/pull.sh` | `git pull` e mostra l'ultimo commit |
| `bash/ftp.sh` | carica via FTP (credenziali `FTP_*` dal `.env`) tutti i file del progetto |
| `bash/onlyFtpOfLastCmt.sh`, `bash/onlyFtpOfCmtById.sh <id>` | come `ftp.sh` ma solo per i file dell'ultimo commit / di un commit |

## Convenzioni di sviluppo

- **Non modificare mai** i file dei framework/dipendenze (`vendor/`, `node_modules/`, ecc.).
- Leggere e rispettare le regole in `.agent/rules/*.md`. Oggi `vault.md`: l'agente non modifica il codice, scrive
  solo gli implementation plan che poi vengono applicati a mano; può invece modificare il README e gli altri file
  di documentazione markdown.
- Flusso di lavoro per ogni modifica non banale:
  1. analisi e pianificazione;
  2. piano nella radice come `implementationPlan-{changeToBeDone}.md`, con ogni step spiegato in dettaglio
     operativo (comandi esatti, codice, file da creare);
  3. esecuzione del piano;
  4. rinomina in `implementationPlan-V-{changeToBeDone}.md` per segnarlo come completato
     (i file `implementationPlan-V-*.md` e `*.pdf` sono in `.gitignore`).
  Per modifiche molto piccole il piano non serve.
- Tenere aggiornato `todo.md` a specchio di ogni modifica.
- **Il README va di pari passo con il codice effettivamente implementato**, non con quello solo pianificato: si
  aggiorna quando una funzionalità è davvero nel codice, e ciò che è pianificato ma assente si segnala come tale
  in "Non ancora implementato".
- Viste Blade: layout `x-app-layout` / `x-guest-layout`, riuso dei componenti Breeze, stile Tailwind
  (dettagli nella sezione "Convenzioni per le view" di `implementationPlan-ricostruzioneUnlimitedDB.md`).
