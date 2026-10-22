# Implementation plan — Ricostruzione UnlimitedDB · Fondamenta (Fasi 1–4bis) ✅ COMPLETATO

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> Storico di tutto ciò che è già nel codice: **non c'è nulla da eseguire qui**. I blocchi di codice lunghi del vecchio piano sono
> stati sostituiti dal puntatore al file reale, e restano solo decisioni, gotcha e tabelle di riferimento ancora utili.
> Allineato al codice a fine settembre 2026. Le correzioni ancora aperte sono nelle altre parti (vedi indice).

## Fase 1 — Setup progetto

### Step 1.1 — Pacchetti base
Laravel 12 creato con `laravel new`, Breeze (Blade) e `spatie/laravel-permission` 6.x installati; migrazioni di `jobs`/`failed_jobs`
e delle tabelle Spatie eseguite.

### Step 1.2 — Configurazione ambiente
`.env` con MariaDB e `QUEUE_CONNECTION=database`; `.env-overrides` (variabili non sensibili tracciate in Git, es. `APP_VERSION_*`)
caricato in `bootstrap/app.php` **prima** del `.env` principale.

## Fase 2 — Ambiente Docker locale

### Step 2.1 — Stack di sviluppo
`Dockerfile` multi-stage (`node-builder` per Vite, `composer-builder`, `icon-builder`, immagine finale `php:8.2-fpm-alpine`),
`docker/entrypoint.sh`, `docker/nginx/default.conf`, `docker/mysql/init.sql` (produzione, `172.23.0.%`) e `init.dev.sql` (sviluppo),
`docker-compose.dev.yml` (servizi `app`, `nginx`, `db`, `worker`). Verificato su `http://localhost:66`.

### Step 2.2 — Rebuild automatico degli asset frontend
Problema: il bind mount `./public/build` nasconde il contenuto copiato dall'immagine. Soluzione in uso: il `Dockerfile` tiene una copia
immutabile in `/opt/build-assets` e `docker/entrypoint.sh` ripulisce `public/build` e la ripopola a ogni avvio del container `app`.
Basta `docker compose -f docker-compose.dev.yml up -d --build` (storico: `implementationPlan-V-autoRebuildAssetsOnDockerBuild.md`).

### Step 2.3 — Icone del sito generate da `public/icon-mine.svg`
Lo stage `icon-builder` del `Dockerfile` esegue `docker/icons/generate-icons.sh` (Debian slim con `rsvg-convert` + ImageMagick) e produce in
`public/build/icons/`: `favicon-16x16.png`, `favicon-32x32.png`, `favicon.ico` (16/32/48), `apple-touch-icon.png` (180, sfondo bianco),
`android-chrome-192x192.png`, `android-chrome-512x512.png`, `site.webmanifest`. Le icone viaggiano con gli asset Vite (stesso percorso
`/opt/build-assets` → `public/build`), quindi per aggiornarle basta modificare l'SVG e ricostruire. I `<link>` stanno in
`resources/views/layouts/favicons.blade.php` (incluso da `layouts/app` e `layouts/guest`); nginx serve `/favicon.ico` da `build/icons`.
Parametri (nome nel manifest, colore, sfondo Apple) sono variabili d'ambiente dello script, documentate in testa al file.

## Fase 3 — Autenticazione e permessi

### Step 3.1 — Verifica email nativa
`User implements MustVerifyEmail` + middleware `verified` sulle rotte protette. Viste e rotte di verifica sono quelle di Breeze.

### Step 3.2 — Pagina admin gestione utenti
`/admin/utenti` (permesso `users.manage`): `App\Http\Controllers\Admin\UserManagementController` (`index`, `edit`, `update` con
`syncRoles`/`syncPermissions`), rotte in `routes/web.php` (gruppo con `->controller(...)`), viste `admin/users/index` e `admin/users/edit`,
voce "Gestione utenti" in **entrambi** i blocchi di `layouts/navigation.blade.php`.

**Gotcha Laravel 12 + spatie 6.x**: gli alias middleware `role`, `permission`, `role_or_permission` non si registrano più da soli. Vanno
aggiunti a mano in `bootstrap/app.php` con `$middleware->alias([...])` (già fatto). Sintomo se mancano: `BindingResolutionException`
("Target class [permission] does not exist") invece di un 403.

Stile e correzioni ancora aperti su queste viste: vedi Step 5.4 in [`implementationPlan-03-ricostruzioneAdminErrori.md`](implementationPlan-03-ricostruzioneAdminErrori.md).

## Fase 3.5 — Email transazionale con Resend

### Step 3.5.1 — Dominio `mandich.dev` verificato su Resend
Verificato l'apice `mandich.dev` (non un sottodominio per sito): vale per tutti i siti `*.mandich.dev`. Record DNS (SPF, DKIM, DMARC opzionale) su Cloudflare.
Contesto: la porta 25 in uscita è bloccata sul piano gratuito Oracle Cloud, quindi niente SMTP self-hosted.

### Step 3.5.2 — API key
Key con permesso "Sending access". Mai in Git (nemmeno in `.env-overrides`, che è tracciato).

### Step 3.5.3 — Pacchetto
`composer require resend/resend-php` (fatto, presente in `composer.json`). `config/mail.php` e `config/services.php` erano già predisposti.

### Step 3.5.4 — Variabili d'ambiente (locale e server)
```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxx
MAIL_FROM_ADDRESS="unlimiteddb@mandich.dev"
MAIL_FROM_NAME="UnlimitedDB"
```
In produzione il `.env` sta sulla VM (`~/sites/SWUDB/.env`); dopo averlo cambiato va riavviato il container app. `.env.example` tiene
`MAIL_MAILER=log` per lo sviluppo locale.

### Step 3.5.5 — Verifica invio
`Mail::raw(...)` da `php artisan tinker` + controllo su [resend.com/emails](https://resend.com/emails). Fatto.

## Fase 4 — Catalogo carte e import via queue

### Step 4.1 — `use Schedule` in `routes/console.php`
Fatto; lo scan è schedulato con `Schedule::command('cards:scan')->weeklyOn(1, '00:00')` (lunedì, mezzanotte).

### Step 4.2 — Schema `expansions`/`cards`
Migration con FK `cards.expansion → expansions.expansion` (`cascadeOnDelete`), `unique(['expansion','number'])`, PK `cards.id` = `"{expansion}{number}"`
valorizzato dal modello `Card` (hook `creating`).

### Step 4.3 — Aspetti e tratti
Modelli `Aspect`/`CardTrait`, tabelle pivot `card_aspect`/`card_trait`. **Attenzione**: nel DB la colonna che punta alla carta si chiama `id`, non
`card_id` come nello schema di riferimento: allineamento in [`implementationPlan-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-02-ricostruzioneAllineamentoSchema.md).

### Step 4.4 — Relazioni nei modelli
`Card::aspects()`, `traits()`, `expansionModel()`, `decks()`; `Expansion::cards()`, `mainExpansion()`, `subExpansions()`, scope `confirmed`.

### Step 4.5 — `ImportCardsFromSwuApiJob`
Codice: `app/Jobs/ImportCardsFromSwuApiJob.php` (`handle()` pagina l'API, `processCard()` per ogni carta, `upsertExpansion()`,
`syncAspectsAndTraits()`, `downloadImages()`, `sendNotifications()`). Comportamento reale:

- Endpoint `GET https://admin.starwarsunlimited.com/api/card-list`, `locale=it`, `filters[variantOf][id][$null]=true`, `fields[]` con i soli scalari
  che servono, **`pageSize=40`**, paginazione da `page=1` fino a `meta.pagination.pageCount`.
- Fail-soft: ogni carta che lancia un'eccezione diventa un `SystemError` e lo scan continua; una pagina con risposta non-2xx interrompe la paginazione.
- Un solo messaggio Telegram di avanzamento per scan, aggiornato con `TelegramService::editMessage()` (**il servizio non esiste ancora**, vedi
  [`implementationPlan-01-ricostruzioneTelegramService.md`](implementationPlan-01-ricostruzioneTelegramService.md), Step 9.2: finché manca, il job fallisce).
- Carta già presente → `SystemError` con stato `ignored` ("Carta {cid} gia' presente, dati aggiornati") e aggiornamento dei dati.
- `$newCards` e `$errors` sono Collection di **modelli** (`Card`, `SystemError`), non di stringhe.
- A fine scan: `NewCardsEmail` a ogni utente (se ci sono carte nuove) e `AdminScanReportEmail` a ogni utente con ruolo `admin` (se ci sono errori), una `Mail::to()->queue()` per destinatario.
- Espansione nuova → `Expansion::firstOrCreate` con `legal_date` = `publishedAt` dell'espansione e `rotation` = massima rotazione esistente (`'0'` se non ce ne sono):
  **placeholder approssimativi**, `confirmed` resta `false` finché un admin non li corregge (Fase 6).
- `cards.release_date` = `publishedAt` della carta (diverso da `expansions.legal_date`: uscita di una carta vs legalità di un'espansione).
- Immagini: priorità a `artFront|artBack.data.attributes.url` (qualità maggiore, peso trascurabile), fallback `formats.card.url`.
- Eccezione hardcoded: la carta JTL #256 ha `max_copies = 15`; per altre eccezioni future valutare una tabella/config invece di altri `if` nel job.
- `Card` ha `$primaryKey = 'id'` (stringa, non incrementale); `cid` (= `cardUid` dell'API) è solo chiave di lookup dell'upsert e identificativo pubblico stabile.

#### Mapping campo API → colonna `cards` (confermato sui dati reali)

| Campo risposta API | Percorso | Colonna | Note |
|---|---|---|---|
| `cardUid` | root | `cid` | `validationId` ha lo stesso valore, si ignora |
| `cardNumber` | root | `number` | |
| `title` | root | `name` | il "titolo" dell'API è il **nome** della carta |
| `subtitle` | root | `title` | il "sottotitolo" dell'API è la colonna `title` |
| `unique` | root | `unique_card` | |
| `cost`, `hp`, `power` | root | `cost`, `health`, `power` | |
| `text`, `artist` | root | `text`, `artist` | `textStyled` (HTML) non serve |
| `type.data.attributes.value` | relazione | `type` | `value` è già nel formato dell'enum SQL |
| `rarity.data.attributes.englishName` | relazione | `rarity` | `name` è localizzato in italiano, non combacia con l'enum |
| `expansion.data.attributes.code` | relazione | `expansion` (FK) | codice naturale (`SOR`), non il nome esteso |
| `arenas.data[0].attributes.name` | relazione | `arena` | nome localizzato, solo per display |
| `traits.data[].attributes.name` | relazione | pivot `card_trait` | upsert su `traits` + `sync` |
| `aspects.data[].attributes.name/color/sortValue/englishName` | relazione | pivot `card_aspect` | upsert su `aspects` (`color`, `order`, `slug`) + `sync` |
| `artFront` / `artBack` | relazione | `front_art_path` / `back_art_path` | via `CardImageDownloader` |
| `publishedAt` | root | `release_date` | **da verificare**: vedi Step 4ter.4 |

Campi che l'API non fornisce: `max_copies` (solo l'eccezione JTL #256 sopra, altrimenti `null` e modificabile a mano).

### Step 4.5bis — Mailable
`app/Mail/NewCardsEmail.php` e `AdminScanReportEmail.php` (entrambe `ShouldQueue`, sintassi `envelope()`/`content()`/`attachments()`), template Markdown
in `resources/views/emails/`. Anteprima su `/render-mail/{type}` (permesso `mails.test`).
Problemi noti ancora aperti (nomi di rotta, colonna `context`): Step 5.5 in [`implementationPlan-03-ricostruzioneAdminErrori.md`](implementationPlan-03-ricostruzioneAdminErrori.md)
e Step 10.5 in [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md).

### Step 4.6 — Download locale delle immagini
`app/Services/CardImageDownloader.php`: scarica l'immagine, deduce l'estensione dal `Content-Type` (`webp`/`jpg`/`png`), salva su disk `public` come
`cards/{expansion}/{number}-{front|back}.{ext}` (numero senza zeri iniziali) e ritorna il path relativo, `null` se qualcosa va storto (il job registra il `SystemError`).
Niente `storage:link` in Docker: nginx serve `/storage/` con un `alias` su `storage/app/public`, montato in sola lettura nel servizio `nginx` dei compose.
`/storage/app/public/cards` è in `.gitignore`.

### Step 4.7 — Test Pest del job
`tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`: `Http::fake` sull'endpoint card-list, `Mail::fake`, fixture reale letta da
`storage/app/private/api-example-result.json` (storico: `implementationPlan-V-updateFakeCardEntryFromJson.md`). Problemi noti (test rotto, dipendenza da `TelegramService`): Step 4ter.5.

## Fase 4bis — Worker delle code

### Step 4bis.1 — Servizio `worker` in dev
`docker-compose.dev.yml`: stessa immagine di `app`, `command: php artisan queue:work --max-time=3600`, `restart: unless-stopped`, IP `172.30.0.11`.
`schedule:run` **non** processa la coda: mette solo in coda i comandi schedulati, serve un `queue:work` sempre attivo.

### Step 4bis.2 — Produzione
`docker-compose.yml` (generato da `new-site.sh` sulla VM, non sta in questa repo) con `worker` allineato all'`app` (subnet `172.23.0.0/24`, stesso DB/utente).
`new-site.sh` aggiornato per generare il servizio `worker` nei siti futuri.

### Step 4bis.3 — Verifica
Worker in ascolto in locale (`docker logs unlimiteddb_worker_dev -f`) e container `_worker` `Up` in produzione.

### Gotcha da ricordare
- **Utente MariaDB per IP singolo**: `utente@172.x.0.10` è un'identità diversa da `utente@172.x.0.11`; il worker viene rifiutato ("Access denied") finché il GRANT non usa il wildcard della subnet (`172.30.0.%` in dev, `172.23.0.%` in produzione). `init*.sql` gira solo alla prima creazione del volume: su un DB esistente il GRANT va applicato a mano.
- **Variabili del blocco `environment:` del compose battono il `.env`**: phpdotenv non sovrascrive variabili già presenti nell'ambiente del processo, quindi cambiare `DB_HOST`/`DB_USERNAME` nel `.env` non ha effetto dentro Docker.
