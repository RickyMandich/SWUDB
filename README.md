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
- Pagina admin espansioni (`/admin/espansioni`, permesso `expansions.manage`): una riga con form inline per espansione per correggere `legal_date`, `rotation`, `group_main_expansion` e `confirmed`.
- Comando `cards:scan` + `ImportCardsFromSwuApiJob` (import carte, espansioni, aspetti, tratti e download immagini),
  schedulato ogni lunedì 00:00 (vedi "Scansione carte").
- Email `NewCardsEmail` e `AdminScanReportEmail`, con anteprima su `/render-mail/{type}`. Il template `emails/md-new-cards.blade.php` linka `cards.new-releases` (con `since` in formato `gg/mm/aaaa`) e `cards.show`, e regge anche una collezione vuota.
- Icone del sito (favicon, apple-touch-icon, manifest) generate dal `Dockerfile` da `public/icon-mine.svg` (vedi "Icone del sito").
- Worker delle code (servizio `worker` nel compose di sviluppo; `app` e `worker` condividono la stessa immagine `unlimiteddb:dev`).
- `TelegramService` (facade `Http`, nessun SDK) con `TelegramActionResult`: invio, modifica e cancellazione di messaggi, invio foto; senza `TELEGRAM_BOT_TOKEN` o `chat_id` non parte nessuna richiesta HTTP.
- Bot Telegram (piano 09): webhook `POST /telegram/webhook` gestito da `TelegramController` (fuori da qualunque gruppo `auth`, esente da CSRF in `bootstrap/app.php`). Il controller accetta la richiesta solo se l'header `X-Telegram-Bot-Api-Secret-Token` coincide con `TELEGRAM_WEBHOOK_SECRET` (con il secret non configurato risponde sempre 403). Comandi: `/scan` (solo dalla chat `TELEGRAM_ADMIN_CHAT_ID`: mette in coda `ImportCardsFromSwuApiJob`) e `/search <nome carta>` (invia la foto della carta, o il link a `cards.show` se Telegram non riesce a scaricare l'immagine, ad esempio in locale); qualunque altro testo riceve l'elenco dei comandi. `NotifyAdminJob` (in coda) invia un messaggio alla chat admin; per ora nessun codice lo mette in coda. Il webhook va registrato una volta con `setWebhook` (vedi "Bot Telegram").
- Admin errori: vista di dettaglio `/admin/errori/{id}`, componenti `<x-flash-message>` e `<x-badge>`, lista paginata.
- Pannello Log viewer (`opcodesio/log-viewer`), linkato dal menu utente a chi ha il permesso `log.viewer`.
- Catalogo carte pubblico `/carte` (anche per gli ospiti): griglia paginata (24 carte per pagina) di `<x-card>` con l'ordinamento di default di `CardBuilder` (Leader, Base, poi aspetti, tipo, costo, nome, `legal_date` dell'espansione e numero). Il componente `<x-cards-filter>` usa `<x-multi-datalist>` (suggerimenti testuali con chip rimovibili per espansioni e tratti) e `<x-range-slider>` (slider a doppio input con limiti dal database per costo, salute, potenza). `CardSearch` implementa la logica reale dei filtri via parametri GET (multi-valore in AND per aspetti e tratti, e `whereIn` per espansioni e tipi). La navigazione (`layouts/navigation.blade.php`) funziona anche per gli ospiti (link Login/Register) e ha la voce "Carte". Pagina di dettaglio `/carte/{expansion}/{number}` (`cards/show.blade.php`, con componenti Bladewind): immagine fronte (con pulsante "Gira la carta" e flip 3D verso il retro, per le carte che ne hanno uno), aspetti, tratti, tipo, rarità, costo, vita, potenza, arena, artista, abilità e abilità "Da schierato". Pagina "Ultime uscite" `/nuove-uscite` (`cards/new-releases.blade.php`): un datepicker Bladewind (formato `gg/mm/aaaa`, limiti tra la prima e l'ultima data di rilascio) sceglie la data `since` (default: la data di rilascio più recente); le carte con `release_date` da quel giorno in poi sono paginate (24 per pagina), con l'ordinamento di default (`CardBuilder::withDefaultOrder()`), e raggruppate per data di rilascio all'interno della pagina.
- Mazzi, pagine (piano 07): lista `/mazzi` (solo l'ultima versione di ogni mazzo, con la copertina `<x-deck-cover>`: base dietro e leader davanti, per Twin Suns le metà sinistre dei due leader affiancate; pulsanti "Nuovo mazzo" e "Importa mazzo"), creazione `/mazzi/crea`, vista di sola lettura `/mazzo/{utente}/{mazzo}/{versione?}` con unità raggruppate assieme e indicatore di arena (`Terrestre`/`Spaziale`) su ciascuna riga per facilitare il montaggio fisico del mazzo, anteprima al passaggio del cursore (desktop hover con overlay fluttuante) e al tocco (popup modale su mobile), statistiche del mazzo incorporate a fondo pagina (curva dei costi interattiva con toggle per tipo a badge, grafico a linee di potenza e vita medie per costo, grafico a torta degli aspetti con fette zebrate multi-aspetto, grafico a torta delle rarità con palette ufficiale, e tratti più presenti), cronologia versioni `/mazzo/{utente}/{mazzo}/versioni`. Pagina di deck-building `/mazzo/modifica/{utente}/{mazzo}` con ricerca carte e filtri integrati, link diretti alla scheda della carta, colonna tipo carta allineata a larghezza fissa, salvataggio batch con avviso modifiche non salvate, e statistiche incorporate a fondo pagina.
- Versioni e copia dei mazzi: le versioni precedenti all'ultima sono in sola lettura (avviso in `decks/show`, niente "Modifica mazzo"). Chi può vedere un mazzo ma non modificarlo, o guarda una versione vecchia, ha il pulsante "Copia come nuovo mazzo" (`POST /mazzi/copia/{utente}/{mazzo}/{versione?}`, `DeckController::copy`): crea un mazzo privato `copia di <nome>` (con suffisso `(2)`, `(3)`... se il nome esiste già) alla versione 1.
- Export/import dei mazzi (piano 07, Step 7.7). Export: `GET /mazzo/{utente}/{mazzo}/esporta/{txt|json}/{versione?}` (download, stessi permessi di `decks.show`) con `DeckExporter`: il TXT ha le sezioni `Leaders`/`Base`/`Deck` con righe `{copie} | {nome}[ | {titolo}]`, il JSON è nel formato ufficiale (id `{ESPANSIONE}_{numero}`, chiavi `leader`, `secondleader` per Twin Suns, `base`, `deck`). Import: `/mazzi/importa` (solo loggati, `DeckImportController` e `DeckImporter`) da file TXT/JSON (max 2 MB) o da URL https (i link `swudb.com/deck/...` sono riscritti verso la loro API); il formato è riconosciuto dal contenuto, il JSON nativo di swudb.com è convertito, la sideboard è ignorata e contata. L'import è fail-soft: le righe non riconosciute non bloccano il mazzo, finiscono in un avviso nell'editor, e se non si riconosce nessuna carta non si crea nulla. L'URL è controllato contro l'SSRF (solo https, host con indirizzo pubblico, nessun redirect seguito, timeout, risposta max 1 MB, content-type JSON o testo).
- Carte mancanti/presenti di un mazzo (piano 08, Step 8.3): `/mazzo/{utente}/{mazzo}/delta/{versione?}` (solo loggati, visibile se il mazzo è visibile) confronta il mazzo con la collezione **di chi guarda** tramite `DeckGapCalculator`. Riepilogo in copie, toggle `?mostra=mancanti` / `?mostra=presenti`; in modalità "mancanti" distingue le copie mancanti del tutto da quelle impegnate in altri mazzi assemblati dell'utente. Linkata da `decks/show` (solo loggati) e `decks/edit`.
- Collezione (piano 08): tabella `collection_cards` (una riga per utente, carta e variante di stampa: normale, foil, hyperspace, hyperspace foil, prestige), pagina `/collezione` (solo loggati) con gli stessi filtri di `/carte` e una quantità per variante su ogni carta, salvata senza reload con `PATCH /collezione` (quantità 0 = riga eliminata).
- Dominio dei mazzi (piano 06): enum `DeckFormat` con cast su `Deck`, relazioni `Deck::leaders()` e `Deck::baseCard()`, un validator per formato in `app/Services/DeckValidation/` (scelto con `DeckFormatValidatorFactory::make($deck->format)`) e `DeckPolicy`. Regole implementate: Premier = 1 leader, 1 base, almeno 50 carte escluse leader e base (60 con la base `JTL24`, Data Vault), al massimo `max_copies` copie per carta (default 3) e solo espansioni delle ultime due rotazioni (`Expansion::validExpansions()`, solo espansioni confermate); Eternal = come Premier senza il controllo di rotazione; Twin Suns = 2 leader con al massimo un aspetto secondario (Eroismo/Malvagità, `Card::secondaryAspect()`) tra i due, 1 base, almeno 80 carte (90 con `JTL24`) e una copia per carta salvo `max_copies`.
- API REST pubblica di sola lettura (piano 10, `routes/api.php`, prefisso `/api`): ricerca carte, dettaglio carta e dettaglio mazzo pubblico in JSON, con `CardResource` e `DeckResource` (vedi "API pubblica"). Sanctum è installato ma non ancora usato.

**Non ancora implementato / non funzionante**
- Bot Telegram: nessun codice mette ancora in coda `NotifyAdminJob`, e il funzionamento del webhook in produzione non è stato verificato con un update reale (`todo.md`, 12.1). Nessun test Pest su `TelegramController` (`todo.md`, 9.7). Il prefisso dei messaggi (es. `dev: `) è configurabile con `TELEGRAM_MESSAGE_PREFIX`.
- **Job di import, problema noto**: `ImportCardsFromSwuApiJob` elabora tutto lo scan in un solo job e tiene in memoria le carte nuove, quelle già presenti e gli errori: con la lista completa (~7200 carte, ~180 pagine) esaurisce i 128 MB di PHP (2026-10-18); inoltre `retry_after` della coda (90 s) è più corto dello scan e un 502 dell'API interrompe lo scan senza ritentare. Il fix (un job per pagina, `cards:scan --page=N`, lock, mail con tetto) è nel piano `implementationPlan-importCartePerPagina.md`, non ancora applicato.
- Job di import, rifiniture aperte (`todo.md`, 5.5.6–5.5.8, assorbite dallo stesso piano): `sendNotifications()` usa ancora `User::role('admin')` (lancia `RoleDoesNotExist` se il ruolo non esiste), `release_date` non tollera un `publishedAt` mancante, il `Log::warning` in `handle()` scrive ancora il payload intero e il `SystemError` del download immagine non riporta `source_url` e `side` nel `context`. Nella suite del 2026-10-26 i 4 test del job sono ancora rossi per queste cause (sezione Test).
- Mazzi (piani 06 e 07, rifiniture aperte; dettaglio in `todo.md`):
  - nessun test Pest sui validator di formato (piano 06, Step 7.5bis) e `'JTL24'` (Data Vault) scritto a mano nei tre validator;
  - import TXT: i nomi nel database sono in italiano, quindi per nome funziona solo con file esportati da questo sito (per nomi inglesi si usa il JSON, che identifica la carta dall'id); le chiavi `secondleader`/`secondLeader` dell'export reale di swudb.com sono da verificare (`todo.md`, 7.7.0bis);
  - verifiche a mano ancora da fare: copertina in `/mazzi` (7.6.7.4) e ricerca carte nell'editor (7.6.6bis.6).
- Collezione, rifiniture: nessun test Pest su `CollectionController` e `DeckGapCalculator`.
- API pubblica, rifiniture (piano 10, `todo.md` 11.5 e 11.3): i test sono in `tests/Feature/Api/PublicApiTest.php` (14, tutti verdi dal 2026-10-26: `CardBuilder::aspectOrderKey()` sceglie l'SQL per MariaDB o SQLite); in `CardBuilder::applyDefaultOrder()` il blocco di ordinamento per aspetti è duplicato (innocuo, da togliere); gli endpoint autenticati con token Sanctum non esistono (`User` non ha `HasApiTokens`).
- Catalogo carte, rifiniture aperte (piano 05, dettaglio nel `todo.md`): con `front_art_path` nullo l'immagine è rotta, sia nel dettaglio carta sia in `<x-card>`; la voce "Carte" del menu non si evidenzia sul dettaglio carta (`:active` usa `cards.index`); in `CardController::newReleases` resta un `Log::debug` che scrive l'intero paginatore nel log, e l'avviso di `/nuove-uscite` conta le carte e le date della pagina corrente, non il totale.
- `/` non è una pagina pubblica: fa redirect a `/dashboard` (richiede login e email verificata). Il logo nel menu punta ancora alla dashboard anche per gli ospiti.
- Nessuno scheduler nel compose di sviluppo (voluto: `cards:scan` si lancia a mano). In produzione `schedule:work` gira come programma di `docker/supervisor/worker.conf` nel container `worker`; da verificare dopo il deploy (Fase 12 del `todo.md`).
- Nessuna pipeline di deploy per il branch `new` nel repo (nessuna cartella `.github/workflows`): piano 11 (`implementationPlan-11-githubActionBuildGhcr.md`); la messa in produzione per scambio degli ambienti è il piano 12 (`implementationPlan-12-ricostruzioneDeploy.md`).

## Stack

| Livello | Tecnologia |
|---|---|
| Backend | PHP 8.2, Laravel 12 |
| Autenticazione | Laravel Breeze (Blade) con verifica email |
| Permessi | `spatie/laravel-permission` 6.x |
| API | Laravel Sanctum 4.x (installato, token non ancora usati) e API Resources |
| Database | MariaDB 11 |
| Code / cache / sessioni | driver `database` |
| Email | Resend (`resend/resend-php`) |
| Frontend | Blade, Tailwind CSS 4, Alpine.js, Vite 7, BladewindUI (`bladewindui/ui` e `bladewindui/table`, componenti pubblicati in `resources/views/components/bladewind`) |
| Test | Pest 3 |
| Infrastruttura | Docker (php-fpm + nginx + mariadb + worker), Traefik su VM Oracle in produzione |

## Struttura delle cartelle

```
app/
  Console/Commands/ScanCards.php      comando `cards:scan` (mette in coda l'import)
  Enums/DeckFormat.php                formato del mazzo (premier, eternal, twin_suns)
  Policies/DeckPolicy.php             view/update/delete dei mazzi
  Http/Controllers/CardController.php catalogo pubblico (`index` con filtri, `show` con la vista di dettaglio e `newReleases` per le ultime uscite)
  Http/Controllers/CollectionController.php collezione personale (`/collezione`, quantità per variante di stampa)
  Http/Controllers/DeckController.php mazzi: lista, creazione, vista di sola lettura, deck-building (batch e singolo), ricerca carte (JSON), snapshot di versione, cronologia, export, copia, carte mancanti
  Http/Controllers/DeckImportController.php import di un mazzo da file TXT/JSON o da URL https
  Http/Controllers/Admin/             ExpansionController, SystemErrorController, UserManagementController
  Http/Controllers/Api/               CardController (ricerca e dettaglio carta) e DeckController (dettaglio mazzo pubblico) dell'API JSON
  Http/Controllers/Auth/              controller Breeze
  Http/Controllers/TelegramController.php webhook del bot Telegram (`/scan`, `/search`)
  Http/Resources/                     CardResource e DeckResource (forma dei JSON dell'API)
  Jobs/ImportCardsFromSwuApiJob.php   import carte dall'API ufficiale SWU
  Jobs/NotifyAdminJob.php             messaggio Telegram alla chat admin (in coda)
  Mail/                               NewCardsEmail, AdminScanReportEmail
  Models/                             Aspect, Card, CardTrait, CollectionCard, Deck, DeckCard, Expansion, SystemError, User
  Models/Builders/CardBuilder.php     builder Eloquent di `Card` con ordinamento di default (usato da `/carte`; si applica solo se la query non ha già un `orderBy`)
  Services/                           CardImageDownloader (immagini carta in storage), CardSearch (filtri catalogo), TelegramService + TelegramActionResult (Telegram), DeckValidation/ (validator per formato del mazzo e factory), DeckExporter e DeckImporter (export/import dei mazzi), DeckGapCalculator (carte mancanti/presenti rispetto alla collezione)
bash/                                 script di commit/versionamento/FTP (vedi "Versionamento")
database/migrations, seeders          schema e PermissionSeeder
docker/                               entrypoint.sh, nginx/default.conf, mysql/init.sql e init.dev.sql, supervisor/worker.conf, icons/generate-icons.sh
resources/views/                      viste Blade (admin/users, admin/errors, admin/expansions, auth, profile, ...)
routes/web.php, routes/api.php, routes/console.php  rotte web, API e schedulazione
tests/                                test Pest (Feature/Auth, Feature/Decks, Feature/Jobs, Feature/Services, ProfileTest, ...; helper `makeCard()`/`makeDeck()` in Pest.php)
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
esterna `proxy`, host `unlimiteddb-test.mandich.dev`, worker con `supervisord -c /etc/supervisor/worker.conf`). Anche qui `app` e `worker` condividono un'unica immagine (`unlimiteddb:prod`, costruita dal servizio `app`).

`migrate --seed` crea permessi e ruolo `admin` (`PermissionSeeder`) e, se `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` sono
impostate nel `.env`, un utente admin con email già verificata (`DatabaseSeeder`, tramite `config/seed.php`).

### Asset frontend
Gli asset Vite sono compilati nello stage `node-builder` del `Dockerfile` e l'`entrypoint.sh` li copia in
`public/build` a ogni avvio del container `app`: basta `up -d --build` per averli aggiornati.
In alternativa, fuori da Docker: `npm install && npm run dev`.

**Tailwind CSS 4**: non esiste più `tailwind.config.js`, la configurazione sta in `resources/css/app.css` (`@import 'tailwindcss'`, `@plugin`, `@theme`, `@source`) e il plugin PostCSS in `postcss.config.js`. Tailwind individua da solo i file da scansionare partendo dalla radice del progetto (ignorando `.gitignore` e `node_modules`) più i path dichiarati con `@source`. Nello stage `node-builder` del `Dockerfile` ci sono solo `resources/` e `public/`, quindi i path `@source` che puntano altrove vanno resi disponibili lì: oggi le viste di paginazione di Laravel (`vendor/laravel/framework/src/Illuminate/Pagination/resources/views`) sono copiate dallo stage `composer-deps`. Se aggiungi un `@source` su un'altra cartella (es. `app/`), aggiungi anche la relativa `COPY` nello stage `node-builder`. `public/build` è in `.dockerignore` perché Tailwind scansionerebbe i vecchi build e ne manterrebbe le classi.

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
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ADMIN_CHAT_ID`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_MESSAGE_PREFIX` | lette da `config/services.php`; il job di scan e `/scan` usano `TELEGRAM_ADMIN_CHAT_ID`; `TELEGRAM_WEBHOOK_SECRET` è il `secret_token` registrato con `setWebhook` (solo `A-Z a-z 0-9 _ -`) e deve essere impostato, altrimenti il webhook risponde 403; il prefisso (es. `dev: `) è anteposto a ogni messaggio ed è vuoto di default (le prime tre sono in `.env.example`, il prefisso no) |
| `SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL`, `SEED_ADMIN_PASSWORD` | credenziali dell'utente admin creato da `db:seed`, lette tramite `config/seed.php` (non sono in `.env.example`: vanno aggiunte al proprio `.env`) |
| `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_PORT` | solo per gli script FTP in `bash/` |
| `APP_VERSION_*` (in `.env-overrides`) | versione dell'app, gestita da `bash/all.sh` |

## Database

Migrazioni in `database/migrations`. Tabelle:

- `users` + tabelle di spatie/permission (ruoli e permessi).
- `expansions`: PK `expansion` (codice, max 10 caratteri); `legal_date`; `rotation` (1 carattere, default `'0'`,
  finestra di rotazione: in formato Premier sono giocabili le ultime due); `confirmed` (dati controllati da un
  admin); `group_main_expansion` (auto-riferimento all'espansione principale del gruppo; nullo per i set standalone).
  Il job crea le espansioni nuove con `rotation` = massima rotazione esistente e `legal_date` = data di pubblicazione
  dell'API (valori approssimativi da correggere a mano dalla pagina `/admin/espansioni`).
- `cards`: PK `id` (stringa `"{expansion}{number}"`, es. `JTL256`, generata dal modello `Card`), `UNIQUE(expansion, number)` e `cid`
  univoco (`cardUid` dell'API, chiave di lookup dell'import); FK `expansion` → `expansions.expansion`.
  `front_art_path`/`back_art_path` sono path relativi nel disk `public`. `type` e `rarity` sono enum SQL con i valori **in italiano** dell'API (`Unità`, `Evento`, `Miglioria`, `Leader`, `Base`, ...); `deploy_text` è il testo dell'abilità "Da schierato".
- `aspects` + `card_aspect`; `traits` (PK `name`) + `card_trait`: aspetti e tratti normalizzati. Le pivot hanno la colonna `card_id` (PK composta `card_id`+`aspect_id` / `card_id`+`trait_name`); `card_aspect` ha anche `position` (ordine degli aspetti sulla carta, scritto dal job di import).
- `decks` (`user_id`, `name`, `format` premier/eternal/twin_suns, `is_public`, `assembled`, `version`,
  `previous_version_id`; `UNIQUE(user_id, name, version)`) e `deck_cards` (PK `deck_id`+`card_id`, `quantity`). Il ruolo Leader/Base non è una colonna:
  si deduce da `cards.type`.
- `collection_cards`: collezione personale, una riga per utente, carta e variante di stampa (`variant`: normale, foil, hyperspace, hyperspace foil, prestige; `UNIQUE(user_id, card_id, variant)`) con `quantity`; la quantità 0 elimina la riga.
- `system_errors`: `source`, `message`, `stack_trace`, `context` (json), `status` (`open`/`ignored`/`resolved`),
  `resolved_at` (valorizzato solo quando lo stato è `resolved`).
- `jobs`, `failed_jobs`, `cache`, `sessions`: infrastruttura Laravel.
- `personal_access_tokens`: tabella di Sanctum (migration `2026_10_19_093052`), oggi non usata da nessun endpoint.

## Autenticazione e permessi

Registrazione/login con Breeze, email da verificare. Permessi definiti in `PermissionSeeder`:
`cards.import`, `cards.manage`, `decks.manage-any`, `collections.manage-any`, `users.manage`,
`bot.notifications.receive`, `mails.test`, `system.manage-errors`, `log.viewer`, `expansions.manage`. Il ruolo `admin` li ha tutti.

Oggi le rotte ne usano quattro (`users.manage`, `system.manage-errors`, `mails.test`, `expansions.manage`); `log.viewer` abilita il link al
Log viewer nel menu utente; `decks.manage-any` è usato da `DeckPolicy` (tramite `Gate::authorize` nel `DeckController`); gli altri sono definiti in vista delle funzionalità future. Con Laravel 12 + spatie 6.x gli alias middleware `role`/`permission`/
`role_or_permission` sono registrati a mano in `bootstrap/app.php`.

## Rotte (`routes/web.php`)

| Rotta | Accesso | Descrizione |
|---|---|---|
| `GET /` | pubblica | redirect a `/dashboard` |
| `GET /carte` | pubblica | catalogo carte paginato, filtri GET (`CardSearch`) |
| `GET /carte/{expansion}/{number}` | pubblica | dettaglio carta (vista `cards.show`) |
| `GET /nuove-uscite` | pubblica | carte rilasciate dalla data `since` (`gg/mm/aaaa`), raggruppate per data (vista `cards.new-releases`) |
| `GET /mazzi` | pubblica | lista dei mazzi pubblici più i propri (`decks.index`) |
| `GET /mazzi/crea`, `POST /mazzi` | login + email verificata | creazione di un mazzo |
| `GET /mazzi/importa`, `POST /mazzi/importa` | login + email verificata | import di un mazzo da file TXT/JSON o da URL https (`decks.import`, `decks.import.store`) |
| `POST /mazzi/copia/{utente}/{mazzo}/{versione?}` | login + email verificata, mazzo visibile (policy `view`) | copia il mazzo come nuovo mazzo privato dell'utente (`decks.copy`) |
| `GET /mazzo/{utente}/{mazzo}/{versione?}`, `GET /mazzo/{utente}/{mazzo}/versioni` | pubblica se il mazzo è pubblico, altrimenti proprietario (policy `view`) | vista di sola lettura e cronologia versioni (una versione che non è l'ultima è in sola lettura); `GET /mazzo/{utente}/{mazzo}/statistiche/{versione?}` rimanda a `decks.show#statistiche` |
| `/mazzo/modifica/{utente}/{mazzo}`: `GET /`, `PUT /carte`, `POST /carte`, `DELETE /carte/{card}`, `PATCH /assembla`, `POST /versione`, `GET /cerca-carte` (JSON paginato, 60 richieste al minuto) | login + email verificata, proprietario o `decks.manage-any` (policy `update`) | deck-building e azioni sul mazzo |
| `GET /mazzo/{utente}/{mazzo}/esporta/{txt\|json}/{versione?}` | come `decks.show` | download del mazzo in TXT o JSON (`decks.export`) |
| `GET /mazzo/{utente}/{mazzo}/delta/{versione?}` | login + email verificata, mazzo visibile | carte mancanti/presenti rispetto alla collezione di chi guarda (`decks.gap`; `?mostra=mancanti\|presenti`) |
| `GET /collezione`, `PATCH /collezione` | login + email verificata | collezione personale e salvataggio delle quantità |
| `GET /dashboard` | login + email verificata | dashboard |
| `/profile` (GET, PATCH, DELETE) | login | profilo utente (Breeze) |
| rotte di `routes/auth.php` | — | login, registrazione, reset password, verifica email (Breeze) |
| `GET /admin/utenti`, `GET /admin/utenti/{user}/modifica`, `PUT /admin/utenti/{user}` | `users.manage` | gestione utenti |
| `GET /admin/errori` | `system.manage-errors` | lista errori (filtro `?status=`) |
| `GET /admin/errori/{systemError}` | `system.manage-errors` | dettaglio errore |
| `PATCH /admin/errori/{systemError}`, `PATCH /admin/errori/bulk` | `system.manage-errors` | cambio stato singolo / bulk |
| `GET /admin/espansioni`, `PUT /admin/espansioni/{expansion}` | `expansions.manage` | lista espansioni e modifica dei campi curati a mano |
| `GET /render-mail/{type}` | `mails.test` | anteprima email (`new-cards`, `admin-scan-report`) |
| `POST /telegram/webhook` | pubblica, protetta dall'header `X-Telegram-Bot-Api-Secret-Token`; esente da CSRF | webhook del bot Telegram (`TelegramController@handle`, rotta `telegram.webhook`) |

## API pubblica (`routes/api.php`)

API JSON di sola lettura, senza autenticazione, sotto il prefisso `/api`. Tutte le rotte hanno `throttle:60,1` (60 richieste al minuto per IP, poi 429).

| Rotta | Descrizione |
|---|---|
| `GET /api/cards/search` | ricerca carte, 24 per pagina (risposta con `data`, `links`, `meta`; i filtri restano nei link di paginazione) |
| `GET /api/cards/{expansion}/{number}` | dettaglio di una carta (404 se non esiste) |
| `GET /api/decks/{userName}/{deckName}` | ultima versione di un mazzo **pubblico** (404 se il mazzo non esiste o è privato) |

I filtri di `/api/cards/search` sono gli stessi di `/carte` (`CardSearch::FILTER_KEYS`): `nome`, `espansioni[]`, `tipi[]`, `aspetti[]` (id degli aspetti), `tratti[]` (nomi), `costo_min`/`costo_max`, `vita_min`/`vita_max`, `potenza_min`/`potenza_max`, `unique_card` (vuoto, `1` o `0`). Un parametro con un nome diverso viene ignorato senza errore.

`CardResource` espone `cid` (chiave pubblica stabile; l'`id` interno `{espansione}{numero}` è escluso), `name`, `title`, `type`, `rarity`, `cost`, `power`, `health`, `text`, `expansion`, `number`, `aspects`, `traits`, `front_image` e `back_image` (URL assoluti, `null` se manca l'immagine). `type`, `rarity` e `text` sono nella lingua dell'API ufficiale (italiano; `text` può contenere HTML). `DeckResource` espone `name`, `format`, `is_public`, `version` e `cards` (le carte come sopra più `quantity`).

**Non ancora implementato**: endpoint autenticati con token Sanctum (piano 10, Step 11.3); i test Pest degli endpoint esistono ma non sono ancora verdi (Step 11.5.8).

## Bot Telegram

Il bot riceve gli update dal webhook `POST /telegram/webhook`. Registrazione una tantum (da terminale, sostituendo token e secret con `TELEGRAM_BOT_TOKEN` e `TELEGRAM_WEBHOOK_SECRET`):

```bash
curl -F "url=https://unlimiteddb-test.mandich.dev/telegram/webhook" -F "secret_token=IL_TUO_SECRET" https://api.telegram.org/bot<TOKEN>/setWebhook
```

Se cambia l'host del sito il webhook va riregistrato. Comandi: `/scan` (solo chat admin) e `/search <nome carta>`.

## Scansione carte

1. `routes/console.php` schedula `cards:scan` ogni **lunedì alle 00:00** (serve uno scheduler che giri).
2. `cards:scan` mette in coda `ImportCardsFromSwuApiJob`.
3. Il **worker** (`php artisan queue:work`) esegue il job: invia un messaggio di avanzamento alla chat admin
   Telegram (tramite `TelegramService`, prefisso opzionale `TELEGRAM_MESSAGE_PREFIX`), interroga
   `https://admin.starwarsunlimited.com/api/card-list` (locale `it`, pagine da 40), fa upsert di espansioni,
   carte, aspetti e tratti e scarica le immagini con `CardImageDownloader`.
4. Le eccezioni (es. download immagine fallito) vengono salvate in `system_errors` e conteggiate; le carte già
   presenti non vanno tra gli `system_errors` (Opzione (a) del piano 03): sono contate a parte e riportate nel
   messaggio Telegram finale. A fine scan partono le email `NewCardsEmail` (a tutti gli utenti, se ci sono nuove
   carte) e `AdminScanReportEmail` (agli utenti con ruolo `admin`, se la collection errori, fatta di `SystemError`, non è
   vuota). Un lato dell'immagine assente nell'API è solo loggato (`Log::debug`); un `SystemError` nasce solo se l'URL
   c'è ma il download fallisce.
5. Eccezione hardcoded nel job: la carta JTL #256 ha `max_copies` = 15.

Lancio manuale: `docker compose -f docker-compose.dev.yml exec app php artisan cards:scan`.

## Immagini delle carte

Salvate nel disk `public` (`storage/app/public/cards/{expansion}/{number}-front|back.{ext}`, ignorate da Git).
Niente `storage:link` in Docker: nginx le serve con l'alias `/storage/` (`docker/nginx/default.conf`) e
`./storage/app/public` è montato nel servizio `nginx` del compose di sviluppo (in produzione, secondo `todo.md`, idem).

## Test

I test si lanciano **dal computer locale**, non nel container: l'immagine Docker installa le dipendenze con
`composer install --no-dev`, quindi Pest non c'è.

```bash
composer test
# oppure: php artisan test
```

Su Windows, se si redirige l'output in un file non usare `:` nel nome (`> test_2026-10-04_09-18.log`): il `:` crea un
alternate data stream NTFS e il file principale resta vuoto.

Framework: Pest. Ci sono i test di autenticazione/profilo di Breeze, `tests/Feature/Services/TelegramServiceTest.php`, `tests/Feature/Services/CardSearchTest.php` (filtri del catalogo)
`tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php` per il job di import e, per i mazzi, `tests/Feature/Decks/DeckPagesTest.php`, `tests/Feature/Decks/DeckStatisticsTest.php`, `tests/Feature/Services/DeckExporterTest.php` e `tests/Feature/Services/DeckImporterTest.php` (helper `makeCard()` e `makeDeck()` in `tests/Pest.php`). Non hanno ancora test i validator di formato (piano 06, Step 7.5bis), `CollectionController`, `DeckGapCalculator` e `TelegramController`. L'API pubblica ha `tests/Feature/Api/PublicApiTest.php` (14 test, tutti verdi). L'`ExampleTest` di Breeze ora si aspetta il redirect a `/dashboard` e passa.

**Ultima esecuzione (2026-10-26, da host): 111 passati, 4 falliti (371 asserzioni).** I 4 fallimenti sono tutti in `ImportCardsFromSwuApiJobTest`: `RoleDoesNotExist` per il ruolo `admin` (3) e mail `AdminScanReportEmail` accodata quando non doveva (1). Cause in `todo.md`, 5.5.6 e 5.5.8, assorbite da `implementationPlan-importCartePerPagina.md`; applicato quel piano, la suite dovrebbe essere tutta verde.

Storico: il 2026-10-23 erano 30 falliti e 85 passati, 26 dei quali per `CardBuilder::applyDefaultOrder()` (SQL solo MariaDB, `near "ORDER"` su SQLite), risolti con lo Step 11.5.8 del piano 10. Il 2026-10-04: 29 passati, 5 falliti.

## Deploy

Il branch `new` gira sulla VM Oracle (infrastruttura Docker + Traefik dei siti `*.mandich.dev`, host
`unlimiteddb-test.mandich.dev`) e nel repo **non ha ancora una pipeline** (nessuna cartella `.github/workflows`):
il piano 11 (`implementationPlan-11-githubActionBuildGhcr.md`) prevede build su GitHub Actions, immagine su `ghcr.io` e
pull sulla VM. La pipeline "merge sul branch `laravel`" riguarda la vecchia SWUDB. Il piano 12 porterà il sito in produzione scambiando gli ambienti: la vecchia SWUDB si sposta su `unlimiteddb-old.mandich.dev` e questo sito (oggi `unlimiteddb-test`) diventa `unlimiteddb.mandich.dev`; fino ad allora gli host in questo README sono quelli di test. In produzione c'è anche il
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
- Leggere e rispettare le regole in `.agent/rules/*.md`. Oggi `main.md`: l'agente non modifica il codice (può toccare solo la docker infra), scrive
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
- Viste Blade: layout `x-app-layout` / `x-guest-layout`, riuso dei componenti Breeze, componenti Bladewind (`<x-bladewind.*>`) per i widget che Breeze non ha, stile Tailwind
  (dettagli nella sezione "Convenzioni per le view" di `implementationPlan-ricostruzioneUnlimitedDB.md`).
- **Dark Mode e Pallette UI**: l'app supporta la dark mode tramite la classe `dark:` di Tailwind. La pallette da mantenere coerente è:
  - Sfondo primario: `bg-gray-100` / `dark:bg-gray-900`
  - Sfondo secondario (card, header, nav): `bg-white` / `dark:bg-gray-800`
  - Testo principale: `text-gray-800` o `text-gray-900` / `dark:text-gray-100` o `dark:text-gray-200`
  - Testo secondario: `text-gray-500` / `dark:text-gray-400`
  - Bordi: `border-gray-200` / `dark:border-gray-700`
  - Componenti Bladewind: non seguono da soli la palette del progetto, quindi passare esplicitamente le classi `dark:` (es. `class="bg-white dark:bg-gray-800"` su `<x-bladewind.card>`)
  - Colori Aspetti: sono fissi a DB e non variano col tema (Vigilanza #4073d4, Eroismo #ffffff, Offensiva #d30808, Malvagità #000000, Autorità #0b992d, Astuzia #eb9f1c).
