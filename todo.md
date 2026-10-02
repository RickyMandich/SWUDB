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

## 01 — TelegramService (Fase 9a) — bloccante per lo scan `implementationPlan-01-ricostruzioneTelegramService.md`
- [X] 9.1 Libreria: facade `Http`, nessun SDK
- [X] 9.2.1 Variabili `TELEGRAM_*` in `.env.example`, `.env` locale e di produzione
- [X] 9.2.2 `TelegramActionResult`
- [X] 9.2.3 `TelegramService` (chatId/messageId nullable, nessuna richiesta HTTP senza token)
- [X] 9.2.4 Test `TelegramServiceTest`
- [X] 9.2.5 Verifica: `cards:scan` esegue il job senza errori nel worker

## 02 — Allineamento schema, modelli e test (Fase 4ter) `implementationPlan-02-ricostruzioneAllineamentoSchema.md`
- [X] 4ter.1 Riscrivere le migration delle pivot con `card_id` (`card_aspect`, `card_trait`, `deck_cards`; oggi la colonna si chiama `id`, e `card_aspect` ha anche un `id()` duplicato)
- [X] 4ter.2 Aggiornare relazioni in `Card`, `Aspect`, `CardTrait`, `DeckCard`, `Deck::cards()`
- [X] 4ter.3 `migrate:fresh --seed`, verifica colonne, rifare la promozione ad admin
- [X] 4ter.4 Verificare `publishedAt` nel payload dell'import (altrimenti `release_date` = data dello scan)
- [X] 4ter.5 Riparare `ImportCardsFromSwuApiJobTest` (`Expansion::create` con `expansion`, helper `fakeSwuHttp`, conteggio richieste `card-list`)

## 03 — Admin errori (Fase 5) `implementationPlan-03-ricostruzioneAdminErrori.md`
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
- [X] 5.5.1 Bug `context['error']` mancante nell'email admin
- [X] 5.5.2 Salvare `$e->getMessage()` nel `context`, non l'oggetto eccezione
- [X] 5.5.3 Decisione chiusa: Opzione (a) — collection carte già presenti (espansione, numero) e report Telegram senza salvarle come SystemError
- [X] 5.5.4 Test del rendering di `AdminScanReportEmail`

## 04 — Admin espansioni (Fase 6) `implementationPlan-04-ricostruzioneAdminEspansioni.md`
- [ ] 6.1 Permesso `expansions.manage` (seeder + `db:seed --class=PermissionSeeder`)
- [ ] 6.2.1 Cast `legal_date`/`confirmed` su `Expansion`
- [ ] 6.2.2 `ExpansionController`
- [ ] 6.2.3 Rotte `/admin/espansioni`
- [ ] 6.2.4 Vista `admin/expansions/index`
- [ ] 6.3 Voce di navigazione (desktop e responsive)
- [ ] 6.4 Verifica

## 05 — Catalogo pubblico e UI (Fase 10) `implementationPlan-05-ricostruzioneCatalogoPubblico.md`
- [ ] 10.3 Rendere `layouts/navigation.blade.php` utilizzabile dagli ospiti (oggi legge `Auth::user()`), prerequisito delle pagine pubbliche
- [ ] 10.1.1 `CardSearch`
- [ ] 10.1.2 `CardController` (`index`, `show`)
- [ ] 10.1.3 Rotte `cards.index`, `cards.show` (`/carte/{expansion}/{number}`)
- [ ] 10.1.4 Cast su `Card` (`release_date`, `unique_card`)
- [ ] 10.1.5 Vista lista carte (occhio al bug del campo nome non ripopolato)
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
- [x] Deploy automatizzato: merge su branch `laravel` → la pipeline fa il resto
- [x] `new-site.sh` sul server aggiornato per generare anche il servizio `worker`
- [ ] 12.0.1 Fix produzione: installare `supervisor` nell'immagine (`apk add supervisor` nel `Dockerfile`), altrimenti il container `unlimiteddb_worker` va in loop con `supervisord: not found`
- [ ] 12.0.2 Fix produzione: nel servizio `nginx` di `docker-compose.yml` montare `build_assets` su `/var/www/html/public/build:ro` (oggi su `public_build`, path che nginx non serve) → ripristina CSS/JS (`/build/assets/*`) e `site.webmanifest` (`/build/icons/*`)
- [ ] 12.1 Verifica post-deploy: webhook Telegram, `failed_jobs` vuota, scan schedulato (serve anche `schedule:run`), immagini carta su `/storage/...`, icone su `/favicon.ico`, container `_worker` `Up`
- [ ] 12.2.0 Verificare che il redeploy non sovrascriva le personalizzazioni di `Dockerfile`, `entrypoint.sh`, `nginx/default.conf`, `docker-compose.yml`
- [ ] 12.2 Redeploy pulito a fine sviluppo (non ora): stop container → branch nuovo come default → commenta Action del vecchio sito → verifica `.env` → cancella `~/sites/SWUDB` → rilancia `new-site.sh`

## Backlog (non pianificato in dettaglio)
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (Aggro/Control/Budget/Meta)
- Modalità offline/PWA
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica probabilità ipergeometrica nelle statistiche mazzo
- Apertura digitale dei booster pack
