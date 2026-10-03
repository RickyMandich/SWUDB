# UnlimitedDB

Database delle carte di **Star Wars: Unlimited** in italiano, con (a regime) gestione dei mazzi, gestione della
collezione personale e le altre funzioni tipiche dei siti database per TCG, più un bot Telegram per la scansione
delle nuove carte e le notifiche all'admin.

Sito: <https://unlimiteddb-test.mandich.dev> (host configurato oggi in `docker-compose.yml` e `docker/nginx/default.conf`)

> Questo repository è la **ricostruzione da zero** del vecchio progetto SWUDB (che girava su Altervista e
> usava soluzioni pezzotte come il `fireAndForget` con POST HTTP ricorsive). Qui si usano code reali,
> permessi granulari e le best practice di Laravel. La vecchia versione, in produzione fino al cambio,
> sta nel progetto separato `SWUDB`.

> **Questo README descrive il progetto così com'è oggi**, non come sarà a fine implementation plan.
> Ciò che non funziona ancora è indicato esplicitamente ("Non ancora implementato"). La roadmap sta in
> [`todo.md`](todo.md) e negli implementation plan numerati, il cui indice e ordine di esecuzione sono in [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).

## Stato attuale

**Funziona**
- Registrazione/login (Breeze) con verifica email, profilo utente.
- Permessi granulari (spatie/laravel-permission) e ruolo `admin`.
- Pagina admin utenti (`/admin/utenti`).
- Pagina admin errori di sistema (`/admin/errori`): lista con filtro per stato, cambio stato singolo e bulk.
- Comando `cards:scan` + `ImportCardsFromSwuApiJob` (import carte, espansioni, aspetti, tratti e download immagini),
  schedulato ogni lunedì 00:00 (vedi "Scansione carte").
- Email `NewCardsEmail` e `AdminScanReportEmail`, con anteprima su `/render-mail/{type}` (l'anteprima `new-cards` per ora dà errore: il template usa le rotte `cards.new-release` e `card.show`, che non esistono ancora; piano 05, Step 10.5).
- Icone del sito (favicon, apple-touch-icon, manifest) generate dal `Dockerfile` da `public/icon-mine.svg` (vedi "Icone del sito").
- Worker delle code (servizio `worker` nel compose di sviluppo; `app` e `worker` condividono la stessa immagine `unlimiteddb:dev`).
- `TelegramService` (facade `Http`, nessun SDK) con `TelegramActionResult`: invio, modifica e cancellazione di messaggi, invio foto; senza `TELEGRAM_BOT_TOKEN` o `chat_id` non parte nessuna richiesta HTTP.
- Admin errori: vista di dettaglio `/admin/errori/{id}`, componenti `<x-flash-message>` e `<x-badge>`, lista paginata.
- Pannello Log viewer (`opcodesio/log-viewer`), linkato dal menu utente a chi ha il permesso `log.viewer`.

**Non ancora implementato / non funzionante**
- Bot Telegram: esiste solo il `TelegramService` (notifiche di avanzamento dello scan). Webhook, comandi (`/scan`, `/search`), ricerca carte e `NotifyAdminJob` non esistono (piano 09). Il service antepone `dev: ` a ogni messaggio e caption (vedi "Debito tecnico" in `todo.md`).
- Mail agli admin dello scan (`todo.md`, 5.5.5, 5.5.6): il job passa a `AdminScanReportEmail` dei `Throwable`, mentre la mail e la sua vista si aspettano modelli `SystemError`, quindi con errori reali non si renderizza. Alcuni test del job vanno riallineati al codice.
- Mazzi: esistono solo tabelle e modelli (`Deck`, `DeckCard`); **nessuna rotta, pagina, policy, enum di formato o
  validatore**. `Deck::leader()` e `Deck::base()` sono da sostituire con `leaders()`/`baseCard()` (piano 06, Step 7.2); `Deck::cards()` è già corretta.
- Collezione, admin espansioni (i dati di `expansions` si correggono solo a mano sul DB), ricerca carte,
  statistiche, API pubblica: non iniziati.
- Nessuna pagina pubblica oltre alle pagine di autenticazione: `/` fa redirect a `/dashboard` (che richiede login e email verificata). `layouts/navigation.blade.php` legge `Auth::user()` senza controlli, quindi `<x-app-layout>` non è ancora utilizzabile dagli ospiti (piano 05, Step 10.3).
- Nessun servizio scheduler nel compose di sviluppo né in quello di produzione: `cards:scan` parte da solo solo se qualcosa esegue
  `schedule:run`/`schedule:work` (vedi Fase 12 del `todo.md`).
- Nessuna pipeline di deploy per il branch `new` nel repo (nessuna cartella `.github/workflows`): piano `implementationPlan-githubActionBuildGhcr.md`.

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
  Services/                           CardImageDownloader (immagini carta in storage), TelegramService + TelegramActionResult (Telegram)
bash/                                 script di commit/versionamento/FTP (vedi "Versionamento")
database/migrations, seeders          schema e PermissionSeeder
docker/                               entrypoint.sh, nginx/default.conf, mysql/init.sql e init.dev.sql, supervisor/worker.conf, icons/generate-icons.sh
resources/views/                      viste Blade (admin/users, admin/errors, auth, profile, ...)
routes/web.php, routes/console.php    rotte web e schedulazione
tests/                                test Pest (Feature/Auth, Feature/Jobs, Feature/Services, ProfileTest, ...)
.agent/rules/*.md                     regole specifiche del progetto (da rispettare sempre)
.env-overrides                        variabili NON sensibili tracciate in Git (versione app)
todo.md                               roadmap/avanzamento
implementationPlan-NN-*.md            piani di implementazione numerati, in ordine di esecuzione (indice: implementationPlan-ricostruzioneUnlimitedDB.md)
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
`app` e `worker` usano la stessa immagine (`image: unlimiteddb:dev`, costruita una volta sola dal servizio `app`):
per ricostruirla `docker compose -f docker-compose.dev.yml build app` oppure `up -d --build`.

`docker-compose.dev.yml` è il compose di **sviluppo**; `docker-compose.yml` è quello di **produzione** (Traefik, rete
esterna `proxy`, host `unlimiteddb-test.mandich.dev`, worker con `supervisord -c /etc/supervisor/worker.conf`).

`migrate --seed` crea permessi e ruolo `admin` (`PermissionSeeder`) e un utente admin con email già verificata,
i cui dati (email e password) sono scritti nel `DatabaseSeeder`: da spostare in variabili d'ambiente ("Debito
tecnico" in `todo.md`).

### Asset frontend
Gli asset Vite sono compilati nello stage `node-builder` del `Dockerfile` e l'`entrypoint.sh` li copia in
`public/build` a ogni avvio del container `app`: basta `up -d --build` per averli aggiornati.
In alternativa, fuori da Docker: `npm install && npm run dev`.

### Icone del sito
Le icone si generano da un unico file, `public/icon-mine.svg`. Lo stage `icon-builder` del `Dockerfile` esegue `docker/icons/generate-icons.sh` (librsvg + ImageMagick) e produce in `public/build/icons/`:
`favicon-16x16.png`, `favicon-32x32.png`, `favicon.ico`, `apple-touch-icon.png` (180px, sfondo bianco), `android-chrome-192x192.png`, `android-chrome-512x512.png`, `site.webmanifest`.
Viaggiano insieme agli asset Vite (stesso meccanismo di `/opt/build-assets`), quindi per cambiare l'icona basta modificare l'SVG e rifare `docker compose -f docker-compose.dev.yml up -d --build`.
I `<link>` stanno in `resources/views/layouts/favicons.blade.php`, incluso da `layouts/app`, `layouts/guest` e `welcome`; nginx serve `/favicon.ico` da `build/icons`.
Nome nel manifest, colore e sfondo Apple si cambiano con le variabili d'ambiente descritte in testa allo script.

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
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ADMIN_CHAT_ID`, `TELEGRAM_WEBHOOK_SECRET` | lette da `config/services.php`; il job di scan usa `TELEGRAM_ADMIN_CHAT_ID` (presenti, vuote, in `.env.example`) |
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
- `cards`: PK `id` (stringa `"{expansion}{number}"`, es. `JTL256`, generata dal modello `Card`), `UNIQUE(expansion, number)` e `cid`
  univoco (`cardUid` dell'API, chiave di lookup dell'import); FK `expansion` → `expansions.expansion`.
  `front_art_path`/`back_art_path` sono path relativi nel disk `public`.
- `aspects` + `card_aspect`; `traits` (PK `name`) + `card_trait`: aspetti e tratti normalizzati. Le pivot hanno la colonna `card_id` (PK composta `card_id`+`aspect_id` / `card_id`+`trait_name`).
- `decks` (`user_id`, `name`, `format` premier/eternal/twin_suns, `is_public`, `assembled`, `version`,
  `previous_version_id`) e `deck_cards` (PK `deck_id`+`card_id`, `quantity`). Il ruolo Leader/Base non è una colonna:
  si deduce da `cards.type`. Vedi "Non ancora implementato" per lo stato della funzionalità.
- `system_errors`: `source`, `message`, `stack_trace`, `context` (json), `status` (`open`/`ignored`/`resolved`),
  `resolved_at` (valorizzato solo quando lo stato è `resolved`).
- `jobs`, `failed_jobs`, `cache`, `sessions`: infrastruttura Laravel.

## Autenticazione e permessi

Registrazione/login con Breeze, email da verificare. Permessi definiti in `PermissionSeeder`:
`cards.import`, `cards.manage`, `decks.manage-any`, `collections.manage-any`, `users.manage`,
`bot.notifications.receive`, `mails.test`, `system.manage-errors`, `log.viewer`. Il ruolo `admin` li ha tutti.

Oggi le rotte ne usano tre (`users.manage`, `system.manage-errors`, `mails.test`); `log.viewer` abilita il link al
Log viewer nel menu utente; gli altri sono definiti in vista delle funzionalità future. Con Laravel 12 + spatie 6.x gli alias middleware `role`/`permission`/
`role_or_permission` sono registrati a mano in `bootstrap/app.php`.

## Rotte (`routes/web.php`)

| Rotta | Accesso | Descrizione |
|---|---|---|
| `GET /` | pubblica | redirect a `/dashboard` |
| `GET /dashboard` | login + email verificata | dashboard |
| `/profile` (GET, PATCH, DELETE) | login | profilo utente (Breeze) |
| rotte di `routes/auth.php` | — | login, registrazione, reset password, verifica email (Breeze) |
| `GET /admin/utenti`, `GET /admin/utenti/{user}/modifica`, `PUT /admin/utenti/{user}` | `users.manage` | gestione utenti |
| `GET /admin/errori` | `system.manage-errors` | lista errori (filtro `?status=`) |
| `GET /admin/errori/{systemError}` | `system.manage-errors` | dettaglio errore |
| `PATCH /admin/errori/{systemError}`, `PATCH /admin/errori/bulk` | `system.manage-errors` | cambio stato singolo / bulk |
| `GET /render-mail/{type}` | `mails.test` | anteprima email (`new-cards`, `admin-scan-report`) |

## Scansione carte

1. `routes/console.php` schedula `cards:scan` ogni **lunedì alle 00:00** (serve uno scheduler che giri).
2. `cards:scan` mette in coda `ImportCardsFromSwuApiJob`.
3. Il **worker** (`php artisan queue:work`) esegue il job: invia un messaggio di avanzamento alla chat admin
   Telegram (tramite `TelegramService`, messaggi con prefisso `dev: `), interroga
   `https://admin.starwarsunlimited.com/api/card-list` (locale `it`, pagine da 40), fa upsert di espansioni,
   carte, aspetti e tratti e scarica le immagini con `CardImageDownloader`.
4. Le eccezioni (es. download immagine fallito) vengono salvate in `system_errors` e conteggiate; le carte già
   presenti non vanno tra gli `system_errors` (Opzione (a) del piano 03): sono contate a parte e riportate nel
   messaggio Telegram finale. A fine scan partono le email `NewCardsEmail` (a tutti gli utenti, se ci sono nuove
   carte) e `AdminScanReportEmail` (agli utenti con ruolo `admin`, se la collection errori non è vuota; vedi
   "Non ancora implementato" per il problema noto della mail).
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

Framework: Pest. Ci sono i test di autenticazione/profilo di Breeze, `tests/Feature/Services/TelegramServiceTest.php`
e `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php` per il job di import; quest'ultimo va riallineato al codice
e riverificato (todo 5.5.6): un test si aspetta la mail agli admin per una carta già presente, che il job non segnala più.

## Deploy

Il branch `new` gira sulla VM Oracle (infrastruttura Docker + Traefik dei siti `*.mandich.dev`, host
`unlimiteddb-test.mandich.dev`) e nel repo **non ha ancora una pipeline** (nessuna cartella `.github/workflows`):
il piano `implementationPlan-githubActionBuildGhcr.md` prevede build su GitHub Actions, immagine su `ghcr.io` e
pull sulla VM. La pipeline "merge sul branch `laravel`" riguarda la vecchia SWUDB. In produzione c'è anche il
servizio `worker`; l'utente MySQL deve essere autorizzato sulla subnet dei container (`laravel@172.22.0.%`, vedi
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
     operativo (comandi esatti, codice, file da creare); i piani della ricostruzione sono numerati
     (`implementationPlan-NN-...md`) nell'ordine in cui vanno eseguiti;
  3. esecuzione del piano;
  4. rinomina in `implementationPlan-V-{changeToBeDone}.md` per segnarlo come completato
     (per i piani numerati `implementationPlan-V-NN-...md`; i file `implementationPlan-V-*.md` e `*.pdf` sono in `.gitignore`).
  Per modifiche molto piccole il piano non serve.
- Tenere aggiornato `todo.md` a specchio di ogni modifica.
- **Il README va di pari passo con il codice effettivamente implementato**, non con quello solo pianificato: si
  aggiorna quando una funzionalità è davvero nel codice, e ciò che è pianificato ma assente si segnala come tale
  in "Non ancora implementato".
- Viste Blade: layout `x-app-layout` / `x-guest-layout`, riuso dei componenti Breeze, stile Tailwind
  (dettagli nella sezione "Convenzioni per le view" di `implementationPlan-ricostruzioneUnlimitedDB.md`).
