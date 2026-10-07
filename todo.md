# rebuild unlimitedDB

> Progresso puntuale, a specchio degli implementation plan numerati: indice e ordine in [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> Una sezione per piano, stesso numero e stessi numeri di step. Quando un piano è finito: spuntare qui e rinominare il file in `implementationPlan-V-NN-...md`.

## Documentazione e infrastruttura trasversale
- [x] README.md sostituito con la documentazione reale del progetto
- [x] README riallineato al codice e ai piani numerati (stato reale, icone, mail, vista dettaglio errore)
- [x] Icone del sito generate dal `Dockerfile` (stage `icon-builder`) a partire da `public/icon-mine.svg` (`docker/icons/generate-icons.sh`, `layouts/favicons.blade.php`, `/favicon.ico` in nginx)
- [ ] Verificare la build dell'immagine con lo stage `icon-builder` (`docker compose -f docker-compose.dev.yml up -d --build`) e che `public/build/icons/` contenga i file
- [ ] Da ora in poi: il README descrive solo il codice effettivamente implementato (il pianificato va segnalato come "non ancora implementato") e si aggiorna a ogni modifica che cambia setup/struttura/rotte/env/permessi
- [x] `welcome.blade.php` include `layouts/favicons.blade.php` come gli altri layout
- [x] Upgrade a Tailwind CSS 4: `Dockerfile` aggiornato (via `COPY tailwind.config.js` che non esiste più, stage `composer-deps` separato da `composer-builder`, viste di paginazione Laravel copiate nello stage `node-builder` per il `@source` di `app.css`), `public/build` e `public/hot` in `.dockerignore`; README aggiornato. Compose dev e prod invariati (usano lo stesso `Dockerfile`)
- [ ] Verificare la build dopo l'upgrade Tailwind 4: `docker compose -f docker-compose.dev.yml up -d --build` (e poi in prod), controllare che la paginazione di `/carte` e `/admin/errori` sia stilata e che il CSS in `public/build/assets` contenga le classi delle viste

## 00 — Fondamenta (Fasi 1–4bis) ✅ `implementationPlan-V-00-ricostruzioneFondamenta.md`
- [x] Fase 1 — setup progetto (Breeze, Spatie, `.env-overrides`)
- [x] Fase 2 — ambiente Docker locale (compose dev, rebuild automatico asset, icone da SVG)
- [x] 3.1 Verifica email nativa
- [x] 3.2 Pagina admin gestione utenti `/admin/utenti`
- [x] 3.5.1–3.5.5 Resend (dominio, API key, pacchetto, variabili, invio di test)
- [x] 4.1 `use Schedule` in `routes/console.php`
- [x] 4.2 FK `cards.expansion → expansions.expansion`
- [x] 4.3 Aspetti e tratti / 4.4 Relazioni nei modelli
- [x] 4.5 `ImportCardsFromSwuApiJob` (+ 4.5bis Mailable)
- [x] 4.6 Download locale immagini (`CardImageDownloader`) e alias nginx `/storage/`
- [x] 4.7 Test Pest del job (da riparare, vedi piano 02, Step 4ter.5)
- [x] 4bis.1 Servizio `worker` in `docker-compose.dev.yml`
- [x] 4bis.2 `worker` di produzione corretto + `new-site.sh` aggiornato
- [x] 4bis.3 Worker attivo in locale e in produzione

## 01 — TelegramService (Fase 9a) ✅ `implementationPlan-V-01-ricostruzioneTelegramService.md`
- [X] 9.1 Libreria: facade `Http`, nessun SDK
- [X] 9.2.1 Variabili `TELEGRAM_*` in `.env.example`, `.env` locale e di produzione
- [X] 9.2.2 `TelegramActionResult`
- [X] 9.2.3 `TelegramService` (chatId/messageId nullable, nessuna richiesta HTTP senza token)
- [X] 9.2.4 Test `TelegramServiceTest`
- [X] 9.2.5 Verifica: `cards:scan` esegue il job senza errori nel worker

## 02 — Allineamento schema, modelli e test (Fase 4ter) ✅ `implementationPlan-V-02-ricostruzioneAllineamentoSchema.md`
- [X] 4ter.1 Riscrivere le migration delle pivot con `card_id` (`card_aspect`, `card_trait`, `deck_cards`; oggi la colonna si chiama `id`, e `card_aspect` ha anche un `id()` duplicato)
- [X] 4ter.2 Aggiornare relazioni in `Card`, `Aspect`, `CardTrait`, `DeckCard`, `Deck::cards()`
- [X] 4ter.3 `migrate:fresh --seed`, verifica colonne, rifare la promozione ad admin
- [X] 4ter.4 Verificare `publishedAt` nel payload dell'import (altrimenti `release_date` = data dello scan)
- [X] 4ter.5 Riparare `ImportCardsFromSwuApiJobTest` (`Expansion::create` con `expansion`, helper `fakeSwuHttp`, conteggio richieste `card-list`)

## 03 — Admin errori (Fase 5) `implementationPlan-V-03-ricostruzioneAdminErrori.md` (file già rinominato con `V-`, ma 5.5.6–5.5.8 sono ancora aperti: restano tracciati qui sotto)
- [x] 5.1 Migration e modello `SystemError`
- [x] 5.2 Permesso `system.manage-errors`
- [x] 5.3.1 Controller (`resolved_at` valorizzato solo se risolto, ignorato resta NULL)
- [x] 5.3.2 Rotte (`bulk` registrata prima di `{systemError}`)
- [x] 5.3.3 Vista lista (funzionante ma grezza)
- [x] 5.3.4 Vista dettaglio (esiste in versione grezza: da rifare con 5.4.10)
- [x] 5.4.4 Link a `errors.show`
- [x] 5.4.7 Voci di navigazione responsive
- [X] 5.4.1 Rinominare `flash-massage` → `flash-message`, sistemarne lo stile, aggiungere `status_level` dove manca
- [X] 5.4.2 Titoli nello slot `header`
- [X] 5.4.3 Bug `$error->stack` (colonna inesistente)
- [X] 5.4.5 Componente `<x-badge>` + `SystemError::statusColor()`
- [X] 5.4.6 Pulsanti (`<x-primary-button>` in `admin/users/edit`)
- [X] 5.4.8 Bug variabile `$errors` sovrascritta + rifacimento vista lista (incorpora 5.4.9 e 5.4.11)
- [X] 5.4.9 Paginazione nella lista errori
- [X] 5.4.10 Rifare la vista dettaglio
- [X] 5.4.11 Pulsanti di stato contestuali
- [X] 5.4.12 Tabelle e stile di `admin/users/*` (+ `<x-input-error>`)
- [X] 5.4.13 Evitare l'auto-lockout in `UserManagementController::update`
- [X] 5.5.1 Bug `context['error']` mancante nell'email admin (il template ora legge `context['error_message']`)
- [X] 5.5.2 Salvare `$e->getMessage()` nel `context`, non l'oggetto eccezione (chiavi `error_message`, `error_line`, `error_code`, `error_file`, `raw`)
- [X] 5.5.3 Decisione chiusa: Opzione (a) — carte già presenti non salvate come SystemError, ma contate a parte (`$existingCards` passata a `processCard()`, conteggio nel messaggio Telegram finale)
- [X] 5.5.4 Test del rendering di `AdminScanReportEmail`
- [X] 5.5.5 Mail admin con `SystemError` invece di `Throwable`: `$errors` passata a `processCard()` e `downloadImages()` e popolata con i `SystemError`; un lato assente nell'API è solo loggato (`Log::debug`), un `SystemError` nasce solo se l'URL c'è e il download fallisce
- [ ] 5.5.6 Suite del 2026-10-04 (da host): 29 passati, 5 falliti. I tre test del job 'crea', 'pagina', 'dati malformati' cadono per `RoleDoesNotExist` (admin) e 'carta già presente' per la mail accodata (tutti per le cause in 5.5.8); `ExampleTest` di Breeze si aspetta 200 su `/`, che ora fa redirect a `/dashboard`: riscriverlo con `assertRedirect('/dashboard')`
- [ ] 5.5.7 Doppio `SystemError` sul download immagine fallito (lo crea `CardImageDownloader::download()` e poi `downloadImages()`; solo il secondo finisce nel report admin): togliere quello del downloader (lasciare un `Log::warning`) e arricchire il `context` di quello del job con `source_url` e `side`
- [ ] 5.5.8 Cause dei test del job falliti: (1) il fixture `api-example-result.json` non ha `publishedAt` al primo livello di `attributes` mentre il job lo chiede nei `fields` → `Undefined array key "publishedAt"` su ogni carta (riga ~174): aggiungerlo in `fakeCardEntry()` e rendere il job tollerante, `release_date` è nullable; (2) `sendNotifications()` usa `User::role('admin')`, che lancia `RoleDoesNotExist` se il ruolo non c'è: usare `User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))`; (3) il `Log::warning` in `handle()` scrive l'intero payload e lo stack trace, già nel `SystemError`: loggare solo `cardUid` e messaggio
- [ ] I test si lanciano da host (`composer test`), non nel container: l'immagine usa `composer install --no-dev`; su Windows non usare `:` nel nome del file di log (crea un alternate data stream e il file resta vuoto): rinominare/eliminare `test_2026-10-04_09` nella radice

## 04 — Admin espansioni (Fase 6) `implementationPlan-V-04-ricostruzioneAdminEspansioni.md`
- [x] 6.1 Permesso `expansions.manage` (seeder + `db:seed --class=PermissionSeeder`)
- [x] 6.2.1 Cast `legal_date`/`confirmed` su `Expansion`
- [x] 6.2.2 `ExpansionController` (unica differenza dal piano: `index` ordina per `rotation` e `legal_date` decrescenti, non per codice)
- [x] 6.2.3 Rotte `/admin/espansioni`
- [x] 6.2.4 Vista `admin/expansions/index` (con `<x-input-error>` sotto la tabella per gli errori di validazione)
- [x] 6.3 Voce di navigazione (desktop e responsive; nel blocco responsive sta dopo "Log viewer" e non subito dopo "Gestione errori", solo estetico)
- [ ] 6.4 Verifica manuale con utente admin (cambio `rotation` con flash verde "Espansione XXX aggiornata."; gruppo inesistente → errore di validazione ora visibile sotto la tabella). Piano già rinominato in `V-`

## 05 — Catalogo pubblico e UI (Fase 10) `implementationPlan-05-ricostruzioneCatalogoPubblico.md`
- [x] 10.3 `layouts/navigation.blade.php` utilizzabile dagli ospiti (`@if (Auth::check()) ... @else` con link Login/Register, desktop e responsive). Differenza dal piano: il logo punta ancora a `route('dashboard')` (per un ospite porta al login) invece di `url('/')`, da decidere
- [x] 10.1.1 `CardSearch`
- [x] 10.1.2 `CardController::index` (differenze dal piano: `with(['aspects','traits'])`, ordinamento per `legal_date` dell'espansione decrescente, poi `expansion` e `number`). `show` esiste ma restituisce il modello come JSON: placeholder fino a 10.1.6
- [x] 10.1.2bis `CardController::index`: `->paginate(3)// 24)` è un residuo di debug, riportare a `paginate(24)`
- [x] 10.1.3 Rotte `cards.index`, `cards.show` (`/carte/{expansion}/{number}`)
- [x] 10.1.4 Cast su `Card` (`release_date`, `unique_card`)
- [x] 10.1.5 Vista lista carte (occhio al bug del campo nome non ripopolato). Fatto: `cards/index.blade.php` con griglia, paginazione e componente `<x-card>` (`components/card.blade.php`, non previsto dal piano: immagine, snippet, tratti, aspetti, costo/potenza/vita, rarità). Il form dei filtri esiste (`<x-cards-filter>`, collassabile con Alpine; tipi e aspetti già badge) ma legge ancora i nomi singoli lato backend, quindi per ora non filtra: vedi `implementationPlan-filtroCarteGrafica.md` (grafica) e la sua Parte B (backend)
- [x] 10.1.5bis `<x-card>`: `Storage::url($card->front_art_path)` con `front_art_path` nullo produce un'immagine rotta (gestire il caso senza immagine); il piano 10.1.6 prevede `<x-badge>` per gli aspetti, la card usa invece `style` inline con `color`/`text_color`
- [x] 10.1.5ter Filtro carte, grafica — `implementationPlan-V-filtroCarteGrafica.md` (Parte A)
  - [x] Step 1 CSS dei pallini dello slider (`resources/css/app.css`)
  - [x] Step 2 Componente `x-range-slider` (costo, vita, potenza: due pallini su una linea)
  - [x] Step 3 Componente `x-multi-datalist` (espansioni e tratti: datalist + chip rimovibili)
  - [x] Step 4 Riscrivere `components/cards-filter.blade.php` (id univoci, `bounds` provvisori, layout a 3 colonne)
  - [x] Step 5 Verifica visiva su `/carte` (checklist nel piano)
  - [x] Step 6 (opzionale) plugin `@alpinejs/collapse` per l'animazione di apertura dei filtri
  - [x] Step 7 README aggiornato dopo l'applicazione
  - [x] Modifica fuori piano (2026-10-06): il pannello "Filtri" si apre solo se c'è un filtro realmente attivo (slider fermi sui limiti = nessun filtro; `page` e parametri sconosciuti ignorati), calcolato in `@php` in cima a `cards-filter.blade.php`
  - [x] Modifica fuori piano (2026-10-06): filtro carte uniche a tre stati (`unique_card` = vuoto/`1`/`0`: tutte, solo uniche, solo non uniche) con radio a badge in `cards-filter.blade.php`; la parte backend (`CardSearch`) è descritta in B.3 del piano e va applicata con la Parte B (10.1.5quater)
- [x] 10.1.5quater Filtro carte, backend — Parte B dello stesso piano (da dettagliare dopo la verifica grafica): nuovi nomi dei parametri nel controller, `$bounds` min/max dal DB per costo/vita/potenza, `CardSearch` con `whereIn`/AND, intervalli applicati solo se più stretti dei limiti e `unique_card` a tre stati (vuoto/`1`/`0`), test Pest
- [ ] 10.1.6 Vista dettaglio carta
- [ ] 10.1.7 Voce di navigazione "Carte"
- [ ] 10.4 Pagina "Nuove uscite" (`cards.new-releases`) + voce di navigazione
- [ ] 10.5 Allineare `emails/new-cards.blade.php` ai nomi di rotta (`cards.new-releases`, `cards.show`)
- [ ] 10.2 Statistiche mazzo (curva costi, tipi, tratti, medie) — **dopo il piano 07**

## 06 — Mazzi, dominio (Fase 7a) `implementationPlan-06-ricostruzioneMazziDominio.md`
- [ ] 7.1 Enum `DeckFormat` + cast su `Deck`
- [ ] 7.2 Sostituire `Deck::leader()`/`base()` con `leaders()`/`baseCard()` (oggi interrogano `Card` direttamente e con `'leader'` minuscolo)
- [ ] 7.3 Validator per formato (Premier / Eternal / TwinSuns; allineamento dei leader di Twin Suns in sospeso)
- [ ] 7.4 `DeckFormatValidatorFactory`
- [ ] 7.5 `DeckPolicy` (`view` accetta anche gli ospiti)

## 07 — Mazzi, pagine (Fase 7b) `implementationPlan-07-ricostruzioneMazziPagine.md`
- [ ] 7.6.1 Rotte mazzi (`/mazzo/{username}/{deckname}`, `/mazzo/modifica/...`)
- [ ] 7.6.2 `DeckController` (con risoluzione deck, show pubblica/owner, edit owner, `syncCards` batch e `createVersion`)
- [ ] 7.6.3 Viste mazzi (index, create, show di sola lettura, edit con deck-building batch e tasto salva, versions)
- [x] 7.6.4 Decisioni chiuse: rotte contestuali all'utente, show per chi possiede/pubblico, edit per proprietario, versioning snapshot on-demand, salvataggio batch primario
- [ ] 7.7 Export/Import mazzi (verificare la sintassi dell'export ufficiale SWU)

## 08 — Collezione (Fase 8) `implementationPlan-08-ricostruzioneCollezione.md`
- [ ] 8.1 Migration, modello `CollectionCard`, `Card::collectionCards()`
- [ ] 8.2 Pagina `/collezione`
- [ ] 8.3.1–8.3.3 `DeckGapCalculator` (per l'utente che guarda) e metodo `gap`
- [ ] 8.3.4 Vista `decks/gap` con toggle carte mancanti / carte presenti

## 09 — Bot Telegram, webhook (Fase 9b) `implementationPlan-09-ricostruzioneBotTelegramWebhook.md`
- [ ] 9.3 Controller, rotta, esenzione CSRF in `bootstrap/app.php`, registrazione webhook via curl
- [ ] 9.6 `NotifyAdminJob`

## 10 — API pubblica (Fase 11) `implementationPlan-10-ricostruzioneApiPubblica.md`
- [ ] 11.1 Sanctum (`install:api`)
- [ ] 11.2 Endpoint pubblici `/api/cards`, `/api/decks` (nome mazzo non univoco: decidere l'URL stabile)
- [ ] 11.4 API Resources (`CardResource`, `DeckResource`)

## 11 — Deploy (Fase 12) `implementationPlan-11-ricostruzioneDeploy.md`
- [x] Deploy automatizzato del branch `laravel` (vecchia SWUDB): merge → la pipeline fa il resto. Per il branch `new` nel repo non c'è nessun `.github/workflows`: vedi sezione "Pipeline di build su GitHub Actions"
- [x] `new-site.sh` sul server aggiornato per generare anche il servizio `worker`
- [x] `docker-compose.dev.yml`: immagine unica `unlimiteddb:dev` condivisa da `app` (con `build: .`) e `worker` (solo `image:`), così in locale si costruisce una volta sola
- [x] `docker-compose.yml` (produzione): stesso schema, immagine unica `unlimiteddb:prod` condivisa da `app` (con `build: .`) e `worker` (solo `image:`); lo Step 3 di `implementationPlan-githubActionBuildGhcr.md` la sostituirà con `ghcr.io/rickymandich/swudb:new` (da riverificare sulla VM al prossimo `up -d --build`)
- [x] 12.0.1 Fix produzione: `supervisor` installato nell'immagine (`apk add ... supervisor` nel `Dockerfile`); il container `unlimiteddb_worker` andava in loop con `supervisord: not found` (da riverificare sulla VM dopo il prossimo build)
- [x] 12.0.2 Fix produzione: nel servizio `nginx` di `docker-compose.yml` `build_assets` è montato su `/var/www/html/public/build:ro` (era `public_build`) → CSS/JS (`/build/assets/*`) e `site.webmanifest` (`/build/icons/*`) (da riverificare sulla VM)
- [ ] 12.1 Verifica post-deploy: webhook Telegram, `failed_jobs` vuota, scan schedulato (serve anche `schedule:run`), immagini carta su `/storage/...`, icone su `/favicon.ico`, container `_worker` `Up`
- [ ] 12.2.0 Verificare che il redeploy non sovrascriva le personalizzazioni di `Dockerfile`, `entrypoint.sh`, `nginx/default.conf`, `docker-compose.yml`
- [ ] 12.2 Redeploy pulito a fine sviluppo (non ora): stop container → branch nuovo come default → commenta Action del vecchio sito → verifica `.env` → cancella `~/sites/SWUDB` → rilancia `new-site.sh`

## Pipeline di build su GitHub Actions (piano trasversale) `implementationPlan-githubActionBuildGhcr.md`
- [ ] Step 0 Pulizia sulla VM (build appese, `docker system df`)
- [ ] Step 1 `~/scripts/deploy-image.sh` sulla VM
- [ ] Step 2 Chiave SSH dedicata e secret `SSH_PRIVATE_KEY_UNLIMITEDDB`
- [ ] Step 3 `docker-compose.yml`: `image: ghcr.io/rickymandich/swudb:new` su `app` e `worker` (il `worker` senza `build`)
- [ ] Step 4 Workflow `.github/workflows/deploy-unlimiteddb.yml`
- [ ] Step 5 Commit e push su `new`
- [ ] Step 6 Primo run e accesso a ghcr.io dalla VM
- [ ] Step 7 Verifica post-deploy e `docker builder prune`
- [ ] Step 9 README (sezione Deploy) e rinomina del piano in `implementationPlan-V-...`

## Debito tecnico trovato il 2026-10-03 (confronto codice/piani, senza piano dedicato)
- [X] `TelegramService`: il prefisso dei messaggi è configurabile (`TELEGRAM_MESSAGE_PREFIX`, default vuoto); impostare `"dev: "` solo negli `.env` di sviluppo/test
- [ ] Seeder admin da variabili d'ambiente: `config/seed.php` e `DatabaseSeeder` ci sono; restano `SEED_ADMIN_*` e `TELEGRAM_MESSAGE_PREFIX` in `.env.example`, la riga `$user->save();` finale inutile e il cambio della vecchia password se l'utente esiste su un DB raggiungibile da fuori (resta nella cronologia Git)
- [X] `layouts/navigation.blade.php`, blocco responsive: tag di chiusura del link "Log viewer" corretto
- [X] Scheduler in produzione: `schedule:work` come programma di `docker/supervisor/worker.conf` nel container `worker` (da verificare dopo il deploy; in dev volutamente assente, lo scan si lancia a mano)
- [X] `docker-compose.dev.yml`: `DB_PASSWORD=${DB_PASSWORD}` ora in entrambi i servizi `app` e `worker`

## 12 — Dark Mode UI (Fase 12) `implementationPlan-V-12-darkModeUi.md`
- [x] 12.1 Layouts
- [x] 12.2 Navigazione
- [x] 12.3 Componenti e Viste base
- [x] 12.4 Aggiornamento colori (solo per il dark mode)

## Backlog (non pianificato in dettaglio)
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (Aggro/Control/Budget/Meta)
- Modalità offline/PWA
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica probabilità ipergeometrica nelle statistiche mazzo
- Apertura digitale dei booster pack
