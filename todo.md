# rebuild unlimitedDB

> Progresso puntuale, a specchio degli implementation plan numerati: indice e ordine in [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> Una sezione per piano, stesso numero e stessi numeri di step. Quando un piano è finito: spuntare qui e rinominare il file in `implementationPlan-V-NN-...md`.

## Documentazione e infrastruttura trasversale
- [x] README.md sostituito con la documentazione reale del progetto
- [x] README riallineato al codice e ai piani numerati (stato reale, icone, mail, vista dettaglio errore)
- [x] README e `todo.md` riallineati al codice dopo la chiusura dello Step 10.1 del piano 05 (2026-10-08)
- [x] README e `todo.md` riallineati al codice dopo lo Step 10.4 del piano 05 (2026-10-09)
- [x] README e `todo.md` riallineati al codice dopo gli Step 7.1–7.5 del piano 06 (2026-10-09)
- [x] README e `todo.md` riallineati al codice reale del piano 07 (Step 7.6.0–7.6.3 già applicati, bug trovati e step 7.6.5–7.9 aggiunti al piano) (2026-10-11)
- [x] README, `todo.md` e indice dei piani (`implementationPlan-ricostruzioneUnlimitedDB.md`) riallineati al codice dopo la chiusura dei piani fino al 09 (riletti routes, controller, viste, servizi e test il 2026-10-19). Il README ora descrive export/import dei mazzi, copertina `<x-deck-cover>`, copia di un mazzo, rotte e test reali
- [x] README e `todo.md` riallineati al codice dopo l'applicazione del piano 10 (API pubblica, riletti `routes/api.php`, controller `Api/*` e Resource il 2026-10-19): sezione "API pubblica" nel README, Sanctum nello stack, `personal_access_tokens` nel database
- [x] Piani 11 (build GitHub Actions, script di deploy generico) e 12 (messa in produzione per scambio degli ambienti, dal 2026-10-26 piano 13) riscritti e numerati; indice, README e `todo.md` riallineati (2026-10-21; riletto anche lo stato del piano 10: non chiuso, manca 11.5.8)
- [x] Suite rilanciata il 2026-10-27 alle 19:3x (`test.log`) dopo gli Step 1–4 e 6 del piano 12: **122 passati (388 asserzioni), nessun fallito** (111 + i 4 test del job ora verdi + i 7 test nuovi). Storico: suite rilanciata il 2026-10-27 dopo le rifiniture del catalogo (10.1.5bis, 10.1.6bis, 10.4quinquies): stesso esito, 4 falliti e 111 passati (371 asserzioni), solo `ImportCardsFromSwuApiJobTest`. Suite rilanciata da host il 2026-10-26 (`test.log`) dopo lo Step 11.5.8: **4 falliti, 111 passati (371 asserzioni)**; rilanciata di nuovo alle 23:21 dopo aver tolto il blocco duplicato di `applyDefaultOrder()`: stesso identico esito (il job di import non è stato ancora toccato, ultima modifica 2026-10-18). Restano solo i 4 test di `ImportCardsFromSwuApiJobTest` (ruolo `admin` assente ×3, mail admin accodata ×1: cause 5.5.8, assorbite dal piano importCartePerPagina). Storico: il 2026-10-23 erano 30 falliti e 85 passati, 26 dei quali per `CardBuilder` su SQLite (`near "ORDER"`). Esito annotato nel README (sezione Test). Chiude con il piano importCartePerPagina
- [x] Libreria UI Bladewind (`bladewindui/ui`, `bladewindui/table`) in uso, con i componenti pubblicati in `resources/views/components/bladewind` (la vista `cards/show` usa `<x-bladewind.card>` e `<x-bladewind.description-list>`); il README la riporta nello stack
- [x] Bladewind aggiunto alle convenzioni per le view (indice, sezione "Componenti Bladewind")
- [x] Icone del sito generate dal `Dockerfile` (stage `icon-builder`) a partire da `public/icon-mine.svg` (`docker/icons/generate-icons.sh`, `layouts/favicons.blade.php`, `/favicon.ico` in nginx)
- [x] Verificare la build dell'immagine con lo stage `icon-builder` (`docker compose -f docker-compose.dev.yml up -d --build`) e che `public/build/icons/` contenga i file
- [x] Da ora in poi: il README descrive solo il codice effettivamente implementato (il pianificato va segnalato come "non ancora implementato") e si aggiorna a ogni modifica che cambia setup/struttura/rotte/env/permessi
- [x] `welcome.blade.php` include `layouts/favicons.blade.php` come gli altri layout
- [x] Upgrade a Tailwind CSS 4: `Dockerfile` aggiornato (via `COPY tailwind.config.js` che non esiste più, stage `composer-deps` separato da `composer-builder`, viste di paginazione Laravel copiate nello stage `node-builder` per il `@source` di `app.css`), `public/build` e `public/hot` in `.dockerignore`; README aggiornato. Compose dev e prod invariati (usano lo stesso `Dockerfile`)
- [x] Verificare la build dopo l'upgrade Tailwind 4: `docker compose -f docker-compose.dev.yml up -d --build` (e poi in prod), controllare che la paginazione di `/carte` e `/admin/errori` sia stilata e che il CSS in `public/build/assets` contenga le classi delle viste

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

## 03 — Admin errori (Fase 5) `implementationPlan-V-03-ricostruzioneAdminErrori.md` (file già rinominato con `V-`; 5.5.6–5.5.8 erano rimasti aperti e sono stati chiusi dal piano 12 il 2026-10-27, restano tracciati qui sotto)
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
- [x] 5.5.6 (chiuso il 2026-10-27: suite a 122 passati, nessun fallito) Storico: suite rilanciata il 2026-10-26: i 4 test del job sono ancora rossi (identici il 2026-10-23) (3× `RoleDoesNotExist` per `admin`, 1× mail `AdminScanReportEmail` accodata quando non doveva), stesse cause di 5.5.8; `ExampleTest` ora passa. Storico, suite del 2026-10-04 (da host): 29 passati, 5 falliti. I tre test del job 'crea', 'pagina', 'dati malformati' cadono per `RoleDoesNotExist` (admin) e 'carta già presente' per la mail accodata (tutti per le cause in 5.5.8); `ExampleTest` di Breeze si aspettava 200 su `/` (che fa redirect a `/dashboard`): già riscritto con `assertRedirect('/dashboard')` (verificato sul codice il 2026-10-19). Resta da rilanciare la suite e annotare l'esito
- [x] 5.5.7 (chiuso, verificato sul codice il 2026-10-27: il `SystemError` di `downloadImages()` ha `source_url` e `side` nel `context`; il downloader fa solo `Log::warning` con `cid`, non il modello) Storico: doppio `SystemError` sul download immagine fallito (lo crea `CardImageDownloader::download()` e poi `downloadImages()`; solo il secondo finisce nel report admin): metà fatta (verificato il 2026-10-19): il downloader ora fa solo `Log::warning`; resta da arricchire il `context` del `SystemError` creato da `downloadImages()` con `source_url` e `side` (oggi ha solo `error_message`)
- [x] 5.5.8 (applicato dal piano 12, Step 3 e 6.1, e verificato sul codice e dalla suite il 2026-10-27: `whereHas` al posto di `User::role('admin')`, `publishedAt` tollerante, nessun payload nel log; storico: assorbito da `implementationPlan-12-importCartePerPagina.md`) Cause dei test del job falliti (riletto sul codice il 2026-10-19: (1), (2) e (3) risultano ancora tutte presenti nel job; `sendNotifications()` usa ancora `User::role('admin')`, `Carbon::parse($cardData['publishedAt'])` non è tollerante, il `Log::warning` in `handle()` scrive ancora `raw` e l'eccezione): (1) il fixture `api-example-result.json` non ha `publishedAt` al primo livello di `attributes` mentre il job lo chiede nei `fields` → `Undefined array key "publishedAt"` su ogni carta (riga ~174): aggiungerlo in `fakeCardEntry()` e rendere il job tollerante, `release_date` è nullable; (2) `sendNotifications()` usa `User::role('admin')`, che lancia `RoleDoesNotExist` se il ruolo non c'è: usare `User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))`; (3) il `Log::warning` in `handle()` scrive l'intero payload e lo stack trace, già nel `SystemError`: loggare solo `cardUid` e messaggio
> Nota: i test si lanciano da host (`composer test`), non nel container: l'immagine usa `composer install --no-dev`; su Windows non usare `:` nel nome del file di log (crea un alternate data stream e il file resta vuoto): rinominare/eliminare `test_2026-10-04_09` nella radice

## 04 — Admin espansioni (Fase 6) `implementationPlan-V-04-ricostruzioneAdminEspansioni.md`
- [x] 6.1 Permesso `expansions.manage` (seeder + `db:seed --class=PermissionSeeder`)
- [x] 6.2.1 Cast `legal_date`/`confirmed` su `Expansion`
- [x] 6.2.2 `ExpansionController` (unica differenza dal piano: `index` ordina per `rotation` e `legal_date` decrescenti, non per codice)
- [x] 6.2.3 Rotte `/admin/espansioni`
- [x] 6.2.4 Vista `admin/expansions/index` (con `<x-input-error>` sotto la tabella per gli errori di validazione)
- [x] 6.3 Voce di navigazione (desktop e responsive; nel blocco responsive sta dopo "Log viewer" e non subito dopo "Gestione errori", solo estetico)
- [ ] 6.4 Verifica manuale con utente admin (cambio `rotation` con flash verde "Espansione XXX aggiornata."; gruppo inesistente → errore di validazione ora visibile sotto la tabella). Piano già rinominato in `V-`

## 05 — Catalogo pubblico e UI (Fase 10) ✅ `implementationPlan-V-05-ricostruzioneCatalogoPubblico.md` (piano completo, file già rinominato come gli altri; restano solo rifiniture tracciate qui sotto)
- [x] 10.3 `layouts/navigation.blade.php` utilizzabile dagli ospiti (`@if (Auth::check()) ... @else` con link Login/Register, desktop e responsive). Differenza dal piano: il logo punta ancora a `route('dashboard')` (per un ospite porta al login) invece di `url('/')`, da decidere
  - [x] 10.3bis (risolto, verificato sul codice il 2026-10-19: "Dashboard" sta dentro `@auth` sia nel menu desktop sia nel responsive; resta solo il logo, che punta ancora a `route('dashboard')`) Storico: nel menu desktop il link "Dashboard" è visibile anche agli ospiti (il piano lo voleva dentro `@auth`; per un ospite porta al login), come il logo
- [x] 10.1.1 `CardSearch`
- [x] 10.1.2 `CardController::index` (differenze dal piano: `with(['aspects','traits'])`, nessun `orderBy` nel controller: l'ordine è il default di `CardBuilder`). `show` ora restituisce la vista `cards.show` (10.1.6)
- [x] 10.1.2bis `CardController::index`: il residuo di debug `->paginate(3)// 24)` è stato sostituito con `->paginate(24)` (2026-10-08)
- [x] 10.1.3 Rotte `cards.index`, `cards.show` (`/carte/{expansion}/{number}`)
- [x] 10.1.4 Cast su `Card` (`release_date`, `unique_card`)
- [x] 10.1.5 Vista lista carte (occhio al bug del campo nome non ripopolato). Fatto: `cards/index.blade.php` con griglia, paginazione e componente `<x-card>` (`components/card.blade.php`, non previsto dal piano: immagine, snippet, tratti, aspetti, costo/potenza/vita, rarità). Il form dei filtri (`<x-cards-filter>`, collassabile con Alpine) è descritto in `implementationPlan-V-filtroCarteGrafica.md` (grafica e backend, voci 10.1.5ter e 10.1.5quater)
- [x] 10.1.5bis (risolto il 2026-10-27: placeholder "Immagine non disponibile" in `<x-card>` e in `cards/show`) `<x-card>` e `cards/show`: `Storage::url($card->front_art_path)` con `front_art_path` nullo produce un'immagine rotta, il caso senza immagine non è ancora gestito in nessuno dei due file (gli aspetti invece ora usano `<x-badge>`, come voleva il piano)
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
- [x] 10.1.5quater Filtro carte, backend — Parte B dello stesso piano: nuovi nomi dei parametri nel controller, `$bounds` min/max dal DB per costo/vita/potenza (`CardSearch::getBounds()`, in cache per un'ora e non invalidata a fine import), `CardSearch` con `whereIn`/AND, intervalli applicati solo se più stretti dei limiti e `unique_card` a tre stati (vuoto/`1`/`0`); test Pest in `tests/Feature/Services/CardSearchTest.php`
- [x] 10.1.6 Vista dettaglio carta (`cards/show.blade.php` con componenti Bladewind: immagine fronte, aspetti e tratti con `<x-badge>`, tipo, rarità, costo, vita, potenza, arena, artista, abilità e abilità "Da schierato"; `CardController::show` carica `aspects`, `traits` ed `expansionModel`)
  - [x] 10.1.6bis (chiuso il 2026-10-27: placeholder per l'immagine nulla, righe costo/vita/potenza nascoste se `NULL` con `filled()` perché lo 0 è un valore vero, righe vuote nascoste, `show(): View`; restano come sono `{!! $card->text !!}` e espansione/numero solo nello `snippet`) Rifiniture rispetto al piano: `Storage::url($card->front_art_path)` con path nullo dà un'immagine rotta; le abilità sono rese con `{!! $card->text !!}` (HTML dell'API, a capo compresi); costo/vita/potenza/arena/artista nulli compaiono come righe vuote; espansione e numero stanno solo nello `snippet` dell'header; `show()` non ha il return type `: View` del piano
  - [x] 10.1.6ter Pulsante "Gira la carta" (compare se `back_art_path` c'è): Alpine (`x-data` sul contenitore, fronte e retro come due `<img>` sovrapposte in una griglia) con flip CSS 3D (`transform-3d`, `backface-hidden`); nei Leader il fronte è orizzontale e il retro verticale
  - [x] 10.1.6quater Flip diagonale (asse a 45°) invece che sull'asse verticale, perché fronte e retro dei Leader hanno orientamenti diversi: classe `.flip-diagonal` in `resources/css/app.css` con `rotate3d(1, 1, 0, 180deg)`
- [x] 10.1.7 Voce "Carte" nel menu (desktop e responsive)
  - [x] 10.1.7bis (chiuso il 2026-10-27: `:active` su `cards.index` è voluto, sul dettaglio carta la voce "Carte" non deve essere evidenziata perché è un'altra pagina) Scostamenti dal piano (stato 2026-10-19: il primo punto è risolto, nel blocco responsive "Carte" e "Ultime uscite" sono ora visibili anche agli ospiti; resta aperto solo `:active`): nel blocco responsive i link "Carte" e "Ultime uscite" stanno dentro `@if (Auth::check())`, quindi **gli ospiti da telefono non li vedono** (il piano li voleva visibili a tutti, in entrambi i blocchi); `:active` usa `routeIs('cards.index')` invece di `cards.*`, quindi sul dettaglio carta la voce non si evidenzia
- [x] Modifica fuori piano (2026-10-07): ordinamento di default delle carte con `App\Models\Builders\CardBuilder` (override di `get()`, agganciato da `Card::newEloquentBuilder`) e colonna `position` su `card_aspect` (migration `2026_10_07_062654_add_position_to_card_aspect_table`, popolata da `syncAspectsAndTraits` nel job di import; `Card::aspects()` ordina per `position`)
  - [x] Il default si applica ora a `/carte`: tolti gli `orderBy` da `CardController::index` (2026-10-08)
  - [x] Passo "tipo specifico" di `CardBuilder` corretto con i nomi italiani (`Unità`, `Miglioria`, `Evento`) (2026-10-08)
  - [x] Carte riscansionate dopo la migration su `position` (2026-10-08)
- [x] 10.4 Pagina "Ultime uscite" (`cards.new-releases`, `/nuove-uscite`) + voce di navigazione "Ultime uscite" (desktop per tutti, responsive solo per i loggati, vedi 10.1.7bis). Fatto: `CardController::newReleases`, vista `cards/new-releases.blade.php` (datepicker Bladewind con limiti dalle date di rilascio min/max, gruppi per data, `<x-card>` riusato), `CardBuilder::withDefaultOrder()`. Scostamento dal piano: `since` è in formato `gg/mm/aaaa` (`date_format:d/m/Y`, convertito nel controller), non `YYYY-MM-DD`. Suddivisione per data di rilascio: paginare la query e raggruppare la collezione della pagina in PHP (`groupBy` sulla collezione, non `GROUP BY` in SQL); `CardBuilder::withDefaultOrder()` pubblico per l'ordine dentro ogni data (vedi Step 10.4.1)
  - [x] 10.4bis (risolto: nel pacchetto installato, bladewindui/ui 5.1.0, la direttiva `@bladewindScripts` esiste ed è registrata da `BladewindCoreServiceProvider`, e ora sta nel `<head>` di `layouts/app.blade.php`; verificato sul codice, non nel browser) Storico: `layouts/app.blade.php` chiudeva il body con `@bladewindScripts`, ma questa direttiva non esiste nel pacchetto installato (Blade la stampa come testo) e `public/vendor/bladewind/js/helpers.js` non viene mai caricato: da qui gli errori `domEl is not defined` e `positionSuffix is not defined` del datepicker. Sostituirla con `<script src="{{ asset('vendor/bladewind/js/helpers.js') }}"></script>` nel `<head>` (prima degli script inline dei componenti); controllare anche `layouts/guest.blade.php`
  - [x] 10.4ter (risolto: `layouts/app.blade.php` sincronizza la classe `dark` su `<html>` con `matchMedia`, in uno script nel `<head>`) Storico: Il CSS di Bladewind applica il tema scuro con la classe `.dark`, mentre il progetto usa il `dark:` di Tailwind 4 basato su `prefers-color-scheme` (nessun `@custom-variant` in `app.css`, nessuna classe `dark` su `<html>`): i componenti Bladewind (datepicker compreso) restano chiari. Sincronizzare la classe `dark` su `<html>` con `matchMedia` in uno script nel `<head>`
  - [x] 10.4quater (risolto in modo diverso dal suggerito: il datepicker resta `format="dd/mm/yyyy"`, il controller valida `date_format:d/m/Y` e converte con `Carbon::createFromFormat`; nessun `range`; c'è il pulsante "Cerca") Storico: Datepicker: `format="dd/mm/yyyy"` invia al server `01/07/2026`, che MariaDB non legge come data (confronto sbagliato = tutte le carte) e che `date` di Laravel interpreta come mese/giorno; `range="true"` (e anche `range="false"`, stringa truthy) invia un intervallo `da - a`, mentre `since` è una data sola; manca un pulsante di invio. Usare `format="yyyy-mm-dd"`, nessun attributo `range`, `date_format:Y-m-d` nella validazione e un `<x-primary-button>`
- [x] 10.5 Allineare `resources/views/emails/md-new-cards.blade.php` (ex `emails/new-cards`): rotte `cards.new-releases` e `cards.show`, `since` in formato `gg/mm/aaaa` con `$cards?->first()?->release_date?->format('d/m/Y') ?? Carbon::today()->format('d/m/Y')` (nessun errore con collezione vuota). Differenza dal piano: il testo dello Step 10.5.1 diceva `toDateString()`, superato dal formato `gg/mm/aaaa` di `/nuove-uscite`; verificare comunque l'anteprima `/render-mail/new-cards` nel browser (2026-10-09)
  - [x] 10.4quinquies (chiuso il 2026-10-27: `Log::debug` tolto, avviso con `$cards->total()` e `$dates` = date distinte di tutte le pagine, `count('release_date')` senza `orderBy`) `CardController::newReleases` aveva ancora un `Log::debug(...)` con l'intero paginatore (residuo di debug, come 10.1.2bis); nell'avviso di `cards/new-releases` `$cards->count()` conta solo la pagina corrente (usare `$cards->total()`) e `$groups->count()` conta solo le date della pagina corrente: il numero di date in cui sono uscite carte va calcolato con una query distinta su tutte le pagine (`$releaseDates`, passato alla vista)
> 10.2 Statistiche mazzo: **spostato nel piano 07** (Step 7.8, vedi sezione 07). Il numero 10.2 resta vuoto per non rinumerare gli step successivi.

## 06 — Mazzi, dominio (Fase 7a) ✅ `implementationPlan-V-06-ricostruzioneMazziDominio.md` (Step 7.1–7.5 applicati il 2026-10-09; restano da scrivere i test dei validator, 7.5bis)
- [x] 7.1 Enum `DeckFormat` + cast su `Deck`
- [x] 7.2 `Deck::leader()`/`base()` sostituite da `leaders()`/`baseCard()` (`belongsToMany` su `deck_cards` filtrate per `cards.type`)
- [x] 7.3 Validator per formato in `app/Services/DeckValidation/` (interfaccia `DeckFormatValidator`). Scostamenti dal piano, tutti voluti: il minimo di carte è controllato sulla somma delle copie escluse leader e base (50 per Premier/Eternal, 80 per Twin Suns; +10 se la base è `JTL24`, Data Vault); `unique_card` non limita le copie nel mazzo (solo quelle in gioco), quindi il limite è `max_copies ?? 3` ovunque tranne Twin Suns (`max_copies ?? 1`); Premier controlla la rotazione con `Expansion::validExpansions()` (ultime due rotazioni distinte, solo espansioni confermate) invece del TODO del piano
  - [x] 7.3.4 Twin Suns: l'allineamento dei leader non richiede nessuna colonna nuova (il TODO del piano è superato): `Card::secondaryAspect()` restituisce l'aspetto Eroismo/Malvagità e il validator dà errore se i due leader ne hanno due diversi (stesso aspetto, uno solo o nessuno = legale). Step 7.3.4 del piano riscritto di conseguenza (2026-10-09)
- [x] 7.4 `DeckFormatValidatorFactory`
- [x] 7.5 `DeckPolicy` (`view` accetta anche gli ospiti; scoperta in automatico da Laravel)
  - [ ] 7.5bis Test Pest dei validator (nessun test sui mazzi esiste ancora): carte contate per copie, leader/base mancanti (nessuna eccezione senza base), rotazione di Premier, aspetti secondari di Twin Suns nei quattro casi
  - [x] 7.5ter `'JTL24'` (Data Vault) è scritto a mano nei tre validator: deciso il 2026-10-27 di lasciarlo così per ora

## 07 — Mazzi, pagine (Fase 7b) ✅ `implementationPlan-V-07-ricostruzioneMazziPagine.md` (applicato; stato riletto sul codice il 2026-10-19: restano solo le verifiche a mano e i punti segnati sotto)
- [x] 7.6.0 Migration `add_unique_user_name_version_to_decks_table` (`2026_10_09_124754`): `UNIQUE(user_id, name, version)`
- [x] 7.6.1 Rotte mazzi applicate (`decks.index/create/store`, gruppo `mazzo/modifica/...`, `decks.versions`, `decks.show` con `whereNumber('version')`). Le rotte degli step successivi ci sono tutte (riletto il 2026-10-19): `decks.statistics`, `decks.search-cards`, `decks.export`, `decks.import`/`decks.import.store`, più `decks.copy` e `decks.gap`
- [x] 7.6.2 `DeckController` applicato (risoluzione deck, `Gate::authorize`, versione nell'URL, `syncCards` batch, `addCard` che somma, `createVersion`, `versions`). Aggiunti e corretti in seguito (riletto il 2026-10-19): `statistics`, `searchCards`, `export`, `copy`, `gap`; `index` e `show` corretti (7.6.5.2, 7.6.5.3)
- [x] 7.6.3 Viste mazzi create (`decks/index`, `create`, `show`, `edit`, `versions`); correzioni e parti mancanti nei punti seguenti
  - [x] Fix dell'utente (2026-10-11): placeholder "Nessun leader." e "Nessuna base." in `decks/index` per i mazzi senza leader o base
- [x] 7.6.4 Decisioni chiuse: rotte contestuali all'utente, show per chi possiede/pubblico, edit per proprietario, versioning snapshot on-demand, salvataggio batch primario
- [x] 7.6.5 Correzioni al codice applicato (bug trovati il 2026-10-11; tutte applicate e riverificate sul codice il 2026-10-19)
  - [x] 7.6.5.1 (applicato dall'utente il 2026-10-11: `$deck->baseCard->first()` in `decks/index` e `decks/show`; in `show` la base ora ha `h-40` come i leader, verificato il 2026-10-19) Storico: `Deck::baseCard()` è una collection: in `decks/index` e `decks/show` usare `$deck->baseCard->first()`. Il fix dei placeholder non basta: `$deck->baseCard?->front_art_path` lancia `Property [front_art_path] does not exist on this collection instance` anche per un mazzo senza base (la collection non è `null`, è vuota), quindi la lista `/mazzi` e ogni pagina `show` danno errore
  - [x] 7.6.5.2 (applicato: `whereDoesntHave('nextVersion')` con `where` raggruppato; coperto da `DeckPagesTest`) `index` mostra ogni versione come mazzo a sé: solo l'ultima (`whereDoesntHave('nextVersion')`, `orWhere` raggruppato)
  - [x] 7.6.5.3 (applicato: `$isLatest`, avviso di sola lettura, "Modifica mazzo" solo sull'ultima versione, pulsanti "Esporta TXT"/"Esporta JSON"; il link "Statistiche" non serve più: le statistiche sono incorporate a fondo pagina e la rotta `decks.statistics` rimanda a `#statistiche`) `show`: `$isLatest`, avviso di sola lettura sulle versioni precedenti, "Modifica mazzo" solo sull'ultima, link "Statistiche" con la versione, pulsanti di export
  - [x] 7.6.5.4 (applicato: nell'header di `decks/index`, accanto a "Nuovo mazzo") Pulsante "Importa mazzo" in `decks/index`
  - [x] 7.6.5.5 Decisioni: `<x-deck-card-row>` rimandato al piano 08 (Step 8.3.4), riga inline in `edit`; validazione di formato sempre informativa
- [ ] 7.6.7 (applicato, solo verifica manuale 7.6.7.4) Lista mazzi: copertina con leader e base sovrapposti (richiesta del 2026-10-11): componente `resources/views/components/deck-cover.blade.php` (leader davanti, base dietro spostata del 25% in alto e a destra; Twin Suns = metà sinistra dei due leader affiancate), `decks/index` con una sola colonna "Leader / Base" (`colspan` 5). Nella lista sostituisce la cella base di 7.6.5.1; `show` usa ancora `->first()`
  - [x] 7.6.7.1 Componente `<x-deck-cover>` (fatto; scostamento dal piano: la base sta dietro in alto a sinistra e il leader davanti in basso a destra; Twin Suns riconosciuto da `leaders->count() >= 2`, placeholder "Nessun leader."/"Nessuna base.")
  - [x] 7.6.7.2 `decks/index.blade.php`: intestazione e riga con `<x-deck-cover :deck="$deck" />`
  - [ ] 7.6.7.4 Verifica a mano su `/mazzi` (Premier/Eternal, Twin Suns, senza base, senza leader, tema scuro, mobile)
- [x] 7.6.6 (applicato dall'utente il 2026-10-12) Ricerca carte nella pagina di modifica (oggi `decks/edit` non permette di aggiungere nessuna carta: c'è solo un commento): rotta `decks.search-cards`, `DeckController::searchCards` (JSON, `CardSearch`, max 20 risultati, solo tipi da mazzo), `edit.blade.php` completo con componente Alpine `deckEditor` (leader e base sostituiti al cambio) e avviso `import-warnings`
- [ ] 7.6.6bis Ricerca carte dell'editor con `<x-cards-filter>`, paginata (30), e avviso di modifiche non salvate (richiesta del 2026-10-12; da applicare nell'ordine 1 → 5)
  - [x] 7.6.6bis.1 `CardSearch::FILTER_KEYS` e `CardSearch::filterOptions()` (opzioni e limiti del filtro); `CardController::index` li usa (refactor puro, `/carte` invariata) e perde i `use` di `Aspect`, `CardTrait`, `Expansion`
  - [x] 7.6.6bis.2 `cards-filter` incorporabile: prop `embedded` e `open`, `{{ $attributes }}` sul `<form>`, "Azzera" come pulsante con evento `cards-filter-reset` ascoltato da `range-slider` e `multi-datalist`
  - [x] 7.6.6bis.3 `DeckController::edit` passa le opzioni del filtro (tipi solo da mazzo); `searchCards` con tutti i filtri di `/carte`, `nome` non più obbligatorio, `paginate(30)`, risposta `{data, current_page, last_page, total}`
  - [x] 7.6.6bis.4 `decks/edit`: `x-data` sul `<div>` esterno, card "Aggiungi carte" con `<x-cards-filter>` fuori dal form del mazzo, risultati scorrevoli, "N di TOT carte", "Carica altre carte"; `search(form)` con `FormData` e `criteria`
  - [x] 7.6.6bis.5 `beforeunload` in `deckEditor` (`snapshot()`, `dirty`, `init()`, `submitting` da `@submit` sul form di salvataggio) e indicatore "Modifiche non salvate"
  - [ ] 7.6.6bis.6 Verifica a mano (`/carte` com'era, filtri nell'editor, più di 30 risultati, Azzera, avviso su chiusura/link, nessun avviso dopo il salvataggio)
- [x] 7.6.6ter Bug da verificare (rilevato rileggendo il codice il 2026-10-19): `DeckController::searchCards` usa `Card::with('aspect')`, ma `Card` ha solo la relazione `aspects()`; con almeno un risultato l'eager load dovrebbe lanciare un'eccezione (relazione inesistente) e i test 'card search paginates by 30...' e 'card search applies the catalogue filters' di `DeckPagesTest` dovrebbero fallire. Correzione: `Card::with('aspects')` (serve anche per `aspect_order_key`). Risolto, non era più un bug: `DeckController::searchCards` usa già `Card::with('aspects')` (riletto il 2026-10-26) e i test di `DeckPagesTest` passano tutti
- [ ] 7.7 (applicato, resta solo 7.7.0bis) Export/Import mazzi (formati ripresi dalla vecchia SWUDB: JSON ufficiale `{ESP}_{numero}`, TXT a sezioni, link swudb.com)
  - [x] 7.7.1 `DeckExporter` (`toText`, `toJson`)
  - [x] 7.7.2 `DeckImporter` (`fromContent` JSON/TXT, `fromUrl`; fail-soft con `notFound`, sideboard ignorata, solo https e host pubblici, nessun redirect; i tre casi limite dell'URL: host non raggiungibile, redirect, content-type inatteso)
  - [x] 7.7.3 (rotte `decks.export` `/mazzo/{utente}/{mazzo}/esporta/{txt|json}/{versione?}`, `decks.import` e `decks.import.store` su `/mazzi/importa`; vista `decks/import` con tab file/URL) `DeckController::export` + rotta `decks.export`; `DeckImportController` + rotte `decks.import`/`decks.import.store` + vista `decks/import`
  - [ ] 7.7.0bis Da verificare su un export reale: chiave `secondleader` (Twin Suns) e `secondLeader` nel JSON nativo di swudb.com; id con o senza zeri iniziali
  - Limite noto: i nomi in DB sono in italiano, quindi l'import TXT per nome funziona solo con file esportati da questo sito; per file con nomi inglesi si usa il JSON (id)
- [x] Modifica fuori piano (rilevata il 2026-10-19): copia di un mazzo visibile come nuovo mazzo privato dell'utente loggato (`POST /mazzi/copia/{utente}/{mazzo}/{versione?}`, `decks.copy`, `DeckController::copy`; nome `copia di <nome>` con suffisso `(2)`, `(3)`... se esiste già, versione 1); pulsante "Copia come nuovo mazzo" in `decks/show` per chi non può modificare il mazzo o guarda una versione vecchia; coperta da `DeckPagesTest`
- [x] 7.8 Statistiche mazzo incorporate e migliorie UI show/edit:
  - [x] 7.8.1 Calcolo statistiche in `DeckController::getDeckStatisticsData(Deck $deck)` (curva costi per tipo, potenza e vita medie per costo, aspetti con multi-colori, rarità con palette ufficiale, tratti, leader e base esclusi)
  - [x] 7.8.2 Rotta `decks.statistics` reindirizza a `decks.show` con fragment `#statistiche`
  - [x] 7.8.3 Statistiche incorporate a fondo pagina sia in `decks/show` sia in `decks/edit` tramite partial `resources/views/decks/partials/statistics.blade.php` (Chart.js via CDN); rimossa la vista autonoma `decks/statistics`
  - [x] 7.8.4 Pagina `show`: unità raggruppate assieme per preservare l'ordinamento naturale di montaggio del mazzo, con indicatore di arena (`Terrestre` / `Spaziale`) su ciascuna riga
  - [x] 7.8.5 Pagina `edit`: link alle carte nella lista, tipo carta allineato con colonna fissa (`w-28`), rimosso pulsante salva ridondante in alto (indicatore modifiche non salvate spostato in basso)
  - [x] 7.8.6 Anteprima carta (desktop hover con overlay fluttuante e mobile touch con popup modal) su entrambe le pagine (`decks/partials/card-preview.blade.php`)
  - [x] 7.8.7 Grafici avanzati statistiche: rimossa quantità carte, grafico line/diffusione potenza e vita medie per costo (rosso/blu), curva dei costi con 3 toggle a `<x-badge>` (Unità, Miglioria, Evento), grafico a torta degli aspetti con fette zebrate multi-aspetto e grafico a torta delle rarità con colori specificati
  - [x] 7.8.8 Test `DeckStatisticsTest` aggiornati e passati (8/8)
- [x] 7.9 (applicato, riletto il 2026-10-19: `tests/Feature/Decks/DeckPagesTest.php`, `DeckStatisticsTest.php`, `tests/Feature/Services/DeckExporterTest.php`, `DeckImporterTest.php` e gli helper in `tests/Pest.php`; restano fuori i test dei validator, 7.5bis) Test Pest dei mazzi: helper `makeCard`/`makeDeck` in `tests/Pest.php`, `DeckExporterTest`, `DeckImporterTest` (con i tre casi limite dell'URL), `DeckPagesTest` (lista, show, edit, ricerca, versioni, export, import, regressioni di 7.6.5), `DeckStatisticsTest` (medie pesate per copie)

## 08 — Collezione (Fase 8) ✅ `implementationPlan-V-08-ricostruzioneCollezione.md` (completato il 2026-10-17, verifiche a mano comprese)
- [x] 8.1 Migration (`2026_10_14_132738_create_collection_cards_table`), modello `CollectionCard`, `Card::collectionCards()` (riletti sul codice il 2026-10-17)
- [x] 8.2 Pagina `/collezione` (corretta dall'utente il 2026-10-17 e riletta sul codice; storico: la prima versione dava `Undefined variable $aspects`); verifica a mano fatta dall'utente (checklist in fondo allo Step 8.2.3 del piano)
  - [x] 8.2.2 `CollectionController::index`: filtrare con `CardSearch::FILTER_KEYS` (non più `nome/espansione/tipo`), passare `filters` + `$search->filterOptions()` e fare `with(['aspects','traits'])`; in `update` aggiungere `max:65535` e `(int)` sul confronto con 0
  - [x] 8.2.3 `collection/index.blade.php` era la copia di `cards/index` (titolo "Carte", niente quantità per variante, niente script): dà `Undefined variable $aspects`. Vista completa nel piano
- [x] 8.3.1–8.3.3 `DeckGapCalculator` (per l'utente che guarda) e metodo `gap` (riletti sul codice il 2026-10-17; 8.3.2 e 8.3.3 avevano due bug, ora risolti):
  - [x] 8.3.2 (corretta dall'utente il 2026-10-17, verificata sul codice) Storico: rotta `decks.gap` messa per errore dentro il gruppo `mazzo/modifica/{username}/{deckname}` (URL reale `/mazzo/modifica/.../mazzi/{deck}/delta`): toglierla di lì e registrarla come `/mazzo/{username}/{deckname}/delta/{version?}` nel gruppo `auth` + `verified` in fondo a `routes/web.php`
  - [x] 8.3.3 `DeckController::gap` (applicato, con `$summary`). Storico: `Gate::authorize`, mazzo da risolvere con `resolveDeck($username, $deckname, $version)` invece del binding per id, aggiunto `$summary` (totali in copie)
- [x] 8.3.4 Vista `decks/gap` con toggle carte mancanti / carte presenti (applicata, riletta sul codice e verificata a mano il 2026-10-17)
  - [x] 8.3.4.1 Componente `<x-deck-card-row>` di sola lettura (`resources/views/components/deck-card-row.blade.php`; la riga di `decks/edit` resta inline, il componente "estratto" del vecchio piano non esisteva)
  - [x] 8.3.4.2 Vista `decks/gap.blade.php`: riepilogo in copie, toggle `?mostra=mancanti|presenti`, sezioni con `<x-badge>` (rosso mancanti, ambra impegnate altrove, verde presenti)
  - [x] 8.3.4.3 Link "Carte mancanti / presenti" in `decks/edit` e "Carte mancanti" (solo `@auth`) in `decks/show`
  - [x] 8.3.4.4 Verifica a mano (checklist nel piano), fatta dall'utente il 2026-10-17
  - [x] 8.3.4bis `\Log::debug` tolto da `components/badge.blade.php` dall'utente (2026-10-17, verificato sul codice); storico: scriveva una riga di log per ogni badge renderizzato

- [ ] Test Pest di `CollectionController` e `DeckGapCalculator` (non previsti dal piano; nessun file di test esiste, verificato il 2026-10-19)

## 09 — Bot Telegram, webhook (Fase 9b) ✅ `implementationPlan-V-09-ricostruzioneBotTelegramWebhook.md`
- [x] 9.3 Controller, rotta, esenzione CSRF in `bootstrap/app.php`, registrazione webhook via curl (applicato e verificato sul codice il 2026-10-18; `setWebhook` ha risposto ok). Nota: `routes/web.php` richiede `use App\Http\Controllers\TelegramController;`, che lo snippet del piano non riportava (aggiunto dall'utente)
- [x] 9.6 `NotifyAdminJob` (creato; nessun codice lo mette ancora in coda)
- [ ] 9.7 Test Pest di `TelegramController` (secret errato/assente → 403, `/scan` da chat non admin, `/search` senza risultati e con fallback sul link) con `Http::fake`: non previsto dal piano, opzionale

## 10 — API pubblica (Fase 11) ✅ `implementationPlan-V-10-ricostruzioneApiPubblica.md` (rinominato il 2026-10-26)
- [x] 11.1 Sanctum (`install:api`): `laravel/sanctum` in `composer.json`, `config/sanctum.php`, `routes/api.php` registrato in `bootstrap/app.php`, migration `2026_10_19_093052_create_personal_access_tokens_table` (verificato sul codice il 2026-10-19; l'esecuzione di `migrate` non è verificata)
- [x] 11.2 Endpoint pubblici `/api/cards/search`, `/api/cards/{expansion}/{number}`, `/api/decks/{userName}/{deckName}` (verificati sul codice il 2026-10-19). Scostamenti dal piano originale, tutti voluti: `throttle:60,1` sull'intero gruppo di rotte (non solo sulla ricerca); `search()` usa `CardSearch::FILTER_KEYS` e `->withQueryString()`; il mazzo restituito è l'ultima versione pubblica (`whereDoesntHave('nextVersion')`). URL stabile dei mazzi risolto: `{userName}/{deckName}` (Step 11.2.4)
  - [x] Bug trovati e corretti dall'utente il 2026-10-19: mancavano gli `use` di `CardResource` e `DeckResource` nei due controller (errore 500 su ogni endpoint), `search()` usava i vecchi nomi dei filtri (ignorati in silenzio), `show` dei mazzi non sceglieva la versione
- [x] 11.4 API Resources (`CardResource`, `DeckResource`; quest'ultima espone anche `version`)
- [x] 11.5 Test Pest dell'API (`tests/Feature/Api/PublicApiTest.php`): file scritto (14 test, Step 11.5.1–11.5.5); ultima esecuzione (2026-10-26) 14 su 14 verdi dopo 11.5.8; 11.5.6: esito di `composer test` annotato nel README (2026-10-26), piano rinominato in `implementationPlan-V-10-ricostruzioneApiPubblica.md` (2026-10-26); 11.5.7 (errori sempre in JSON sotto `/api`) è opzionale
  - [x] 11.5.8 (applicato dall'utente il 2026-10-26, verificato sul codice e dalla suite: 111 passati; `aspectOrderKey()` presente con i due rami per driver. Il blocco `orderByRaw` duplicato in `applyDefaultOrder()` è stato tolto dall'utente il 2026-10-26) Storico: Bug `CardBuilder::applyDefaultOrder()`: SQL solo MariaDB (`GROUP_CONCAT ... ORDER BY ... SEPARATOR`, `LPAD`) che SQLite dei test non capisce; correzione nel piano 10 (metodo `aspectOrderKey()` con ramo per driver). Da applicare nel codice, poi rilanciare la suite intera. Confermato dal log del 2026-10-23: la stessa causa fa fallire 26 test in tutto (`PublicApiTest` 7, `DeckPagesTest` 4: 'removing a card detaches it', 'card search paginates by 30', 'card search applies the catalogue filters', 'a logged user imports a json file'; `CardSearchTest` 7, `DeckExporterTest` 1, `DeckImporterTest` 7), quindi la correzione sblocca quasi tutta la suite
- [ ] 11.3 Endpoint autenticati con token Sanctum: rimandato (non necessario al day 1). `User` non ha ancora il trait `HasApiTokens`

## 11 — Build su GitHub Actions (Fase 12) ✅ `implementationPlan-V-11-githubActionBuildGhcr.md` (completato il 2026-10-26; comprende le fix di produzione al compose già applicate)
- [x] Deploy automatizzato del branch `laravel` (vecchia SWUDB): merge → la pipeline fa il resto. Per il branch `new` la pipeline è `.github/workflows/deploy-unlimiteddb.yml`, aggiunta da questo piano (2026-10-26)
- [x] `new-site.sh` sul server aggiornato per generare anche il servizio `worker`
- [x] `docker-compose.dev.yml`: immagine unica `unlimiteddb:dev` condivisa da `app` (con `build: .`) e `worker` (solo `image:`), così in locale si costruisce una volta sola
- [x] `docker-compose.yml` (produzione): stesso schema, immagine unica condivisa da `app` (con `build: .`) e `worker` (solo `image:`); lo Step 12.4 di `implementationPlan-V-11-githubActionBuildGhcr.md` l'ha sostituita con `ghcr.io/rickymandich/swudb:new` (verificato sulla VM il 2026-10-26: `app` e `worker` partiti da quell'immagine)
- [x] Fix produzione (ex 12.0.1): `supervisor` installato nell'immagine (`apk add ... supervisor` nel `Dockerfile`); il container `unlimiteddb_worker` andava in loop con `supervisord: not found` (da riverificare sulla VM dopo il prossimo build)
- [x] Fix produzione (ex 12.0.2): nel servizio `nginx` di `docker-compose.yml` `build_assets` è montato su `/var/www/html/public/build:ro` (era `public_build`) → CSS/JS (`/build/assets/*`) e `site.webmanifest` (`/build/icons/*`) (da riverificare sulla VM)
- [x] 12.0 Pulizia sulla VM (build appese, `docker system df`) (2026-10-26: nessuna build in corso; cache di build 14,5 GB, 12,7 GB recuperabili)
- [x] 12.1 `~/scripts/deploy-image.sh` generico sulla VM (nessun codice di un sito specifico; argomenti `<sito> [branch]`) (2026-10-26: `bash -n` senza errori)
- [x] 12.2 Hook del sito nella repo: `deploy/pre-deploy.sh` e `deploy/post-deploy.sh` (`migrate --force`, `queue:restart`, reload di nginx)
- [x] 12.3 Chiave SSH dedicata (forced command su `deploy-image.sh unlimiteddb new`) e secret `SSH_PRIVATE_KEY_UNLIMITEDDB`
  - [x] 12.3bis La chiave privata è stata stampata nel terminale (e incollata in una chat): rigenerarla (`rm ~/.ssh/deploy_unlimiteddb{,.pub}`, rifare il blocco dello Step 12.3 senza `cat` a schermo) e aggiornare il secret su GitHub
- [x] 12.4 `docker-compose.yml`: `image: ghcr.io/rickymandich/swudb:new` su `app` e `worker` (il `worker` senza `build`)
- [x] 12.5 Workflow `.github/workflows/deploy-unlimiteddb.yml`
- [x] 12.6 Commit e push su `new` (fatti dalla VM con `cmt`, commit `8c526107`)
- [x] 12.7 Primo run e accesso a ghcr.io dalla VM (2026-10-26: `app` e `worker` ricreati con `ghcr.io/rickymandich/swudb:new`, nessun errore di pull)
- [x] 12.8 Verifica post-deploy e `docker builder prune` (2026-10-26: `docker compose ps` tutti Up, 2 processi `queue:work`, `/` risponde 302 verso `/dashboard` e `/favicon.ico` 200; hook, asset e prune controllati dall'utente)
- [x] 12.9 Rollback provato (tag `sha-<commit>`)
- [x] 12.10 README (sezione Deploy), `todo.md` e rinomina del piano in `implementationPlan-V-11-githubActionBuildGhcr.md` (README, `todo.md` e indice dei piani aggiornati il 2026-10-26; piano rinominato dall'utente)

## 12 — Import carte per pagina (prerequisito di 13.4) ✅ `implementationPlan-V-12-importCartePerPagina.md` (completato il 2026-10-27) (ex piano trasversale `importCartePerPagina`, numerato 12 il 2026-10-26; step da 0 a 8, senza "Fase")
- [x] Step 1 (verificato sul codice il 2026-10-27) `NewCardsEmail` e `AdminScanReportEmail` con `$total` opzionale, template con "…e altre N" (con riga vuota prima del testo)
- [x] Step 2 (verificato il 2026-10-27; scostamento: il messaggio è sempre "Cards scan queued from page N.", anche per la pagina 1) `cards:scan --page=N`
- [x] Step 3 (verificato il 2026-10-27; scostamenti in coda a questa sezione) `ImportCardsFromSwuApiJob` riscritto: un job per pagina, stato in cache, carte nuove ed errori ricavati dal DB (`created_at`), lock, `failed()`, `publishedAt` tollerante, `whereHas` al posto di `User::role('admin')`, `SystemError` del download con `source_url` e `side`
- [x] Step 4 (verificato il 2026-10-27) `CardImageDownloader` con timeout e retry
- [x] Step 5 (chiuso il 2026-10-27: `DB_QUEUE_RETRY_AFTER=300` aggiunto a `.env.example`, `.env` locale e VM a posto per dichiarazione dell'utente) `worker.conf` (`--memory=100 --timeout=240`), stesso comando nel worker di `docker-compose.dev.yml` (5.1bis), `DB_QUEUE_RETRY_AFTER=300` (`.env.example`, `.env` locale e della VM), `LOG_LEVEL=info` per l'import
- [x] Step 6 (verificato il 2026-10-27: suite a 122 passati) test Pest (fixture con `publishedAt`, 7 test nuovi)
- [x] Scostamenti del codice dal piano (2026-10-27): (a) `PAGE_SIZE` è 100 invece di 40, **voluto dall'utente**; (b) il filtro `filters[variantOf][id][$null]` è stato rimesso attivo dall'utente (verificato sul codice: le varianti restano escluse, come da piano); (c) il log per carta riporta `{$card->id}` invece di espansione e numero (innocuo)
- [x] Commenti del job aggiornati a `PAGE_SIZE` = 100 (docblock di classe e commento sopra `$tries`; i commenti contano come documentazione, la logica non è stata toccata)
- [x] Step 7 verifica con un import completo in locale (`memoria_picco_mb` piatto, una sola mail con elenco di 100 carte; prova della ripresa). Evidenza nel log del 2026-10-27: scan `93953789-…` terminato alle 23:05:25, 32 pagine, **3135 carte elaborate, 3135 nuove, 0 problemi**, picco di memoria 80 MB all'ultima pagina (sotto il limite `--memory=100` del worker). Mail con l'elenco delle carte e ripresa con `--page=N` provate dall'utente (2026-10-27)
- [x] Step 8 README, `todo.md`, indice e rinomina in `implementationPlan-V-12-importCartePerPagina.md` (2026-10-27); chiude 5.5.6–5.5.8 del piano 03

## 13 — Messa in produzione, scambio degli ambienti (Fase 13) `implementationPlan-13-ricostruzioneDeploy.md` (ex piano 12, rinumerato il 2026-10-26)
- [ ] 13.1 Host parametrico: `SITE_HOST` nel `.env` della VM e nel compose, `server_name _;` in nginx, `.env.example`
- [ ] 13.2 Rilevamento sulla VM (conferma dei nomi letti dalla repo `PROJECT/SWUDB`: router `SWUDB`, subnet 172.23.0.0/24, DB `my_swudb`, nessun worker; cron sul vecchio sito; `TELEGRAM_MESSAGE_PREFIX` diverso nei due `.env`; DNS di `unlimiteddb-old`)
- [ ] 13.3 Backup (dump dei due database, `.env`, storage del vecchio sito)
- [ ] 13.4 Reset pulito del nuovo sito (deciso il 2026-10-21): `migrate:fresh --seed --force`, immagini cancellate, reimport completo con `cards:scan`, verifiche e **espansioni da confermare a mano** da `/admin/espansioni` (senza `confirmed` nessun mazzo Premier è valido); prerequisiti: `SEED_ADMIN_*` nel `.env` della VM e `implementationPlan-V-12-importCartePerPagina.md` applicato e provato (fatto il 2026-10-27) (memoria esaurita dopo ~180 pagine, 2026-10-18). Utenti e mazzi del vecchio sito **non** migrati (eventuali utenti a mano in futuro)
- [ ] 13.5 Cutover: Action vecchia off → vecchio su `unlimiteddb-old` → nuovo su `unlimiteddb` → webhook Telegram
- [ ] 13.6 Verifica post-cutover (webhook, coda, scan schedulato, immagini, icone, mail, vecchio sito)
- [ ] 13.7 Rollback (solo routing, database invariati)
- [ ] 13.8 Dismissione del vecchio sito (dopo qualche settimana)
- [ ] 13.9 README, `todo.md`, indice e rinomina del piano in `implementationPlan-V-13-ricostruzioneDeploy.md`

## 14 — Varianti delle carte e Set Promozionali (prerequisito di 13.4) `implementationPlan-14-variantiCarte.md` (aggiornato il 2026-10-28 con divisione promo stile SWUDB; **Step 1–5 applicati il 2026-10-28 ma con errori**: suite a 51 falliti e 76 passati, pagine dei mazzi in 500; correzioni in 14a e 14b qui sotto; Step 6–8 non eseguiti; da chiudere prima di 13.4)
- [ ] Step 0 Verifica preliminare dell'API (facoltativa)
- [ ] Step 1 Schema e modelli, applicati: migration `2026_10_28_170709_add_variants_and_drop_collection_variant` (`original_card_id`, `variant_type`, `variant_code`, indice normale su `expansion`+`number`, `collection_cards` senza `variant` con unique `(user_id, card_id)`; `cards.id` resta `VARCHAR(20)`, nessun suffisso cid), `Card` (`originalCard()`, `variants()`, `originals()`), `Expansion` (`mainExpansion()`, `subExpansions()`). Da correggere con 14a: manca `use Illuminate\Database\Eloquent\Builder;` in `Card.php` (TypeError su `originals()`, causa della maggior parte dei test rossi) e nella migration manca `dropUnique(['expansion', 'number'])`
- [ ] Step 2 (applicati 2.1, 2.3, 2.4, 2.5 con due `+` residui nella vista collezione, 2.6; 2.2 invariato per scelta: `/nuove-uscite` mostra tutte le carte, varianti comprese; 2.5 `DeckGapCalculator` con somma delle varianti: piano 14b, da applicare) Liste solo originali e compatibilità mazzi: `CardSearch` (`apply`, `filterOptions`, `getBounds`), `CardController::newReleases`, `TelegramController`, validazione mazzi e collezione (`whereNull('original_card_id')`), `DeckImporter` con supporto a codici SWUDB (`SOROP_1`, `SORPR_2` ecc.) e riconduzione all'originale; controllo punti non letti (2.8)
- [ ] Step 3 (3.2 e 3.3 applicati; **3.1 applicato per errore in `DeckController::show`** al posto di `CardController::show`, le pagine dei mazzi danno 500: 14a Step 2) Scheda carta e API: `CardController::show` (variante → redirect 301 all'originale), sezione "Altre versioni" in `cards/show` con badge espansione/tipo, `CardResource` (`variant_type`, `variant_code`, `original`, `variants`) e `Api\CardController::show`
- [ ] Step 4 (applicati 4.1–4.6, con `Fase` nel messaggio Telegram e nel log per pagina aggiunta dall'utente fuori piano; 4.7: `cards:scan --phase` non viene passato al job, 14a Step 3; default di `$phase` per i job in coda, 14a Step 4) Import a due fasi con normalizzazione stile SWUDB (`resolveExpansionCode()` che mappa le promo dei primi set su `SORPR`, `SOROP`, `SORSH`, `SHDPQ` ecc., `createExpansionIfMissing()` con `group_main_expansion`, fallback `hasFoil` se `variantTypes` è vuoto, fallback su espansione seriale se null, interruttore `SWU_VARIANT_IMAGES`)
- [ ] Step 5 (scritti, da correggere con 14a Step 7: `fakeVariantEntry()` non definita, test 'pagina' e 'riprende' da adeguare alla seconda fase, `use` mancanti in `CardVariantsTest`) Test Pest: mock `fakeSwuHttp()` a due fasi, test di assegnazione sotto-espansioni promo stile SWUDB, test catalogo, test import mazzi promo SWUDB in `CardVariantsTest.php`; suite intera verde
- [ ] Step 6 Applicazione in locale (migrate, cache:forget cards_bounds, scan varianti con tinker di verifica conteggio per espansione promo)
- [ ] Step 7 Rilascio sulla VM (verifica spazio disco, interruttore immagini, `cache:forget cards_bounds`, scan varianti); aggiornare Step 13.4 con totale ~10.286 carte
- [ ] Step 8 README, `todo.md`, indice e rinomina in `implementationPlan-V-14-variantiCarte.md`
- Fuori piano, da decidere: ristampe (`reprintOf`) ancora doppie in lista; badge "N versioni" su `<x-card>`; `sort[0]=id:asc` nell'API (il collegamento `collection_cards.variant` → carte-variante è stato risolto nello Step 2.5: ogni versione è una riga di collezione)
- Import locale in corso (scan `b5170eb9`, pagina 22/32 alle 19:33 del 2026-10-28) col **job della versione precedente** (nei log manca "Fase"): DB da considerare provvisorio, vedi 14a Step 8; il reset pulito di 13.4 lo rifarà

## 14-Correzione — `implementationPlan-14-variantiCarte-Correzione.md` (2026-10-29: riunisce 14a e 14b, che restano come storico). Già fatti dall'utente: import `Builder` in `Card`, `show()` nei controller giusti, `use` in `CardVariantsTest`, `+` tolti dalla vista; unico `(expansion, number)` mantenuto per scelta (niente `dropUnique`). Restano: `cards:scan --phase` passato al job, default di `$phase`, `fakeVariantEntry()` e adeguamento dei test 'pagina'/'riprende', test nuovi, `DeckGapCalculator` con varianti, `down()` della migration senza `unique`, verifica suite. Elenco storico di 14a:
- [ ] Step 1 `Card.php`: `use Illuminate\Database\Eloquent\Builder;`
- [ ] Step 2 Ripristino di `DeckController::show` e `CardController::show` (con redirect 301 delle varianti)
- [ ] Step 3 `cards:scan --phase` passato al job (con validazione del valore)
- [ ] Step 4 `ImportCardsFromSwuApiJob`: `$phase` con default di proprietà (job già in coda)
- [ ] Step 5 Unico `(expansion, number)`: nuova migration (o `dropUnique` nella migration applicata prima di `migrate:fresh`) e `down()` coerente
- [ ] Step 6 Vista `collection/index`: due `+` rimasti
- [ ] Step 7 Test: `fakeVariantEntry()`, 'pagina' (3 richieste), 'riprende' (solo fase originali), 3 test nuovi (`--phase`, fase sconosciuta, job senza `phase`), `use` in `CardVariantsTest`; suite verde da host
- [ ] Step 8 Import locale: lasciar finire gli originali o riprendere con `--page=N` dopo il riavvio del worker; verifica dei segnalini fuori da `T*`
- [ ] Step 9 Verifica finale, README riallineato (solo a codice funzionante), rinomina in `implementationPlan-V-14a-correzioniVarianti.md`

## 14b — Carte mancanti con varianti `implementationPlan-14b-deltaCollezioneVarianti.md` (scritto il 2026-10-28)
- [ ] Step 1 `DeckGapCalculator`: richiesto e posseduto per carta originale (le copie di tutte le varianti si sommano), `JOIN cards` con `COALESCE(original_card_id, id)`
- [ ] Step 2 Test `DeckGapCalculatorTest` (variante + originale, solo variante, copie impegnate altrove, altro utente)
- [ ] Step 4 README (delta per carta originale) e rinomina in `implementationPlan-V-14b-deltaCollezioneVarianti.md`

## Debito tecnico trovato il 2026-10-03 (confronto codice/piani, senza piano dedicato)
- [X] `TelegramService`: il prefisso dei messaggi è configurabile (`TELEGRAM_MESSAGE_PREFIX`, default vuoto); impostare `"dev: "` solo negli `.env` di sviluppo/test
- [ ] Seeder admin da variabili d'ambiente: `config/seed.php` e `DatabaseSeeder` ci sono; restano `SEED_ADMIN_*` e `TELEGRAM_MESSAGE_PREFIX` in `.env.example` (`DB_QUEUE_RETRY_AFTER` aggiunto il 2026-10-27) (la riga `$user->save();` inutile è già sparita) e il cambio della vecchia password se l'utente esiste su un DB raggiungibile da fuori (resta nella cronologia Git)
- [X] `layouts/navigation.blade.php`, blocco responsive: tag di chiusura del link "Log viewer" corretto
- [X] Scheduler in produzione: `schedule:work` come programma di `docker/supervisor/worker.conf` nel container `worker` (da verificare dopo il deploy; in dev volutamente assente, lo scan si lancia a mano)
- [X] `docker-compose.dev.yml`: `DB_PASSWORD=${DB_PASSWORD}` ora in entrambi i servizi `app` e `worker`

## Dark Mode UI (piano trasversale già completato; il "12" nel nome del file è storico) `implementationPlan-V-12-darkModeUi.md`
- [x] 12.1 Layouts
- [x] 12.2 Navigazione
- [x] 12.3 Componenti e Viste base
- [x] 12.4 Aggiornamento colori (solo per il dark mode)

## Bug fix 2026-10-14
- [x] `decks.edit` andava in errore (`array_slice()` su Collection) perché `$traits` del filtro carte sovrascriveva i tratti del mazzo: le statistiche ora espongono `deckTraits` come Collection (controller, partial `statistics`, test di regressione in `DeckStatisticsTest`)

## Fix visivo collezione 2026-10-19
- [x] `collection.index`: i campi delle copie possedute erano coperti dalle carte successive (slot del componente `card` dentro la griglia a 2 colonne e dentro il link); ora `components/card.blade.php` rende lo slot sotto il link, a tutta larghezza (`article` flex-col), solo se non vuoto
- [x] `collection.index`: il bordo giallo (valore negativo/non valido corretto a 0) e rosso (salvataggio fallito) ora sono impostati con `style.borderColor` inline, perché le classi `dark:border-gray-*` dell'input coprivano le utility `border-yellow-500`/`border-red-500`

## Backlog (non pianificato in dettaglio)
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (Aggro/Control/Budget/Meta)
- Modalità offline/PWA
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica probabilità ipergeometrica nelle statistiche mazzo
- Apertura digitale dei booster pack
