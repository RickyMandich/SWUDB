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
- [x] Libreria UI Bladewind (`bladewindui/ui`, `bladewindui/table`) in uso, con i componenti pubblicati in `resources/views/components/bladewind` (la vista `cards/show` usa `<x-bladewind.card>` e `<x-bladewind.description-list>`); il README la riporta nello stack
- [x] Bladewind aggiunto alle convenzioni per le view (indice, sezione "Componenti Bladewind")
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

## 05 — Catalogo pubblico e UI (Fase 10) `implementationPlan-05-V-ricostruzioneCatalogoPubblico.md` (piano completo; restano solo rifiniture tracciate qui sotto. Il file è `implementationPlan-05-V-...`, non `implementationPlan-V-05-...` come gli altri piani: da rinominare)
- [x] 10.3 `layouts/navigation.blade.php` utilizzabile dagli ospiti (`@if (Auth::check()) ... @else` con link Login/Register, desktop e responsive). Differenza dal piano: il logo punta ancora a `route('dashboard')` (per un ospite porta al login) invece di `url('/')`, da decidere
  - [ ] 10.3bis Nel menu desktop il link "Dashboard" è visibile anche agli ospiti (il piano lo voleva dentro `@auth`; per un ospite porta al login), come il logo
- [x] 10.1.1 `CardSearch`
- [x] 10.1.2 `CardController::index` (differenze dal piano: `with(['aspects','traits'])`, nessun `orderBy` nel controller: l'ordine è il default di `CardBuilder`). `show` ora restituisce la vista `cards.show` (10.1.6)
- [x] 10.1.2bis `CardController::index`: il residuo di debug `->paginate(3)// 24)` è stato sostituito con `->paginate(24)` (2026-10-08)
- [x] 10.1.3 Rotte `cards.index`, `cards.show` (`/carte/{expansion}/{number}`)
- [x] 10.1.4 Cast su `Card` (`release_date`, `unique_card`)
- [x] 10.1.5 Vista lista carte (occhio al bug del campo nome non ripopolato). Fatto: `cards/index.blade.php` con griglia, paginazione e componente `<x-card>` (`components/card.blade.php`, non previsto dal piano: immagine, snippet, tratti, aspetti, costo/potenza/vita, rarità). Il form dei filtri (`<x-cards-filter>`, collassabile con Alpine) è descritto in `implementationPlan-V-filtroCarteGrafica.md` (grafica e backend, voci 10.1.5ter e 10.1.5quater)
- [ ] 10.1.5bis `<x-card>` e `cards/show`: `Storage::url($card->front_art_path)` con `front_art_path` nullo produce un'immagine rotta, il caso senza immagine non è ancora gestito in nessuno dei due file (gli aspetti invece ora usano `<x-badge>`, come voleva il piano)
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
  - [ ] 10.1.6bis Rifiniture rispetto al piano: `Storage::url($card->front_art_path)` con path nullo dà un'immagine rotta; le abilità sono rese con `{!! $card->text !!}` (HTML dell'API, a capo compresi); costo/vita/potenza/arena/artista nulli compaiono come righe vuote; espansione e numero stanno solo nello `snippet` dell'header; `show()` non ha il return type `: View` del piano
  - [x] 10.1.6ter Pulsante "Gira la carta" (compare se `back_art_path` c'è): Alpine (`x-data` sul contenitore, fronte e retro come due `<img>` sovrapposte in una griglia) con flip CSS 3D (`transform-3d`, `backface-hidden`); nei Leader il fronte è orizzontale e il retro verticale
  - [x] 10.1.6quater Flip diagonale (asse a 45°) invece che sull'asse verticale, perché fronte e retro dei Leader hanno orientamenti diversi: classe `.flip-diagonal` in `resources/css/app.css` con `rotate3d(1, 1, 0, 180deg)`
- [x] 10.1.7 Voce "Carte" nel menu (desktop e responsive)
  - [ ] 10.1.7bis Scostamenti dal piano: nel blocco responsive i link "Carte" e "Ultime uscite" stanno dentro `@if (Auth::check())`, quindi **gli ospiti da telefono non li vedono** (il piano li voleva visibili a tutti, in entrambi i blocchi); `:active` usa `routeIs('cards.index')` invece di `cards.*`, quindi sul dettaglio carta la voce non si evidenzia
- [x] Modifica fuori piano (2026-10-07): ordinamento di default delle carte con `App\Models\Builders\CardBuilder` (override di `get()`, agganciato da `Card::newEloquentBuilder`) e colonna `position` su `card_aspect` (migration `2026_10_07_062654_add_position_to_card_aspect_table`, popolata da `syncAspectsAndTraits` nel job di import; `Card::aspects()` ordina per `position`)
  - [x] Il default si applica ora a `/carte`: tolti gli `orderBy` da `CardController::index` (2026-10-08)
  - [x] Passo "tipo specifico" di `CardBuilder` corretto con i nomi italiani (`Unità`, `Miglioria`, `Evento`) (2026-10-08)
  - [x] Carte riscansionate dopo la migration su `position` (2026-10-08)
- [x] 10.4 Pagina "Ultime uscite" (`cards.new-releases`, `/nuove-uscite`) + voce di navigazione "Ultime uscite" (desktop per tutti, responsive solo per i loggati, vedi 10.1.7bis). Fatto: `CardController::newReleases`, vista `cards/new-releases.blade.php` (datepicker Bladewind con limiti dalle date di rilascio min/max, gruppi per data, `<x-card>` riusato), `CardBuilder::withDefaultOrder()`. Scostamento dal piano: `since` è in formato `gg/mm/aaaa` (`date_format:d/m/Y`, convertito nel controller), non `YYYY-MM-DD`. Suddivisione per data di rilascio: paginare la query e raggruppare la collezione della pagina in PHP (`groupBy` sulla collezione, non `GROUP BY` in SQL); `CardBuilder::withDefaultOrder()` pubblico per l'ordine dentro ogni data (vedi Step 10.4.1)
  - [x] 10.4bis (risolto: nel pacchetto installato, bladewindui/ui 5.1.0, la direttiva `@bladewindScripts` esiste ed è registrata da `BladewindCoreServiceProvider`, e ora sta nel `<head>` di `layouts/app.blade.php`; verificato sul codice, non nel browser) Storico: `layouts/app.blade.php` chiudeva il body con `@bladewindScripts`, ma questa direttiva non esiste nel pacchetto installato (Blade la stampa come testo) e `public/vendor/bladewind/js/helpers.js` non viene mai caricato: da qui gli errori `domEl is not defined` e `positionSuffix is not defined` del datepicker. Sostituirla con `<script src="{{ asset('vendor/bladewind/js/helpers.js') }}"></script>` nel `<head>` (prima degli script inline dei componenti); controllare anche `layouts/guest.blade.php`
  - [x] 10.4ter (risolto: `layouts/app.blade.php` sincronizza la classe `dark` su `<html>` con `matchMedia`, in uno script nel `<head>`) Storico: Il CSS di Bladewind applica il tema scuro con la classe `.dark`, mentre il progetto usa il `dark:` di Tailwind 4 basato su `prefers-color-scheme` (nessun `@custom-variant` in `app.css`, nessuna classe `dark` su `<html>`): i componenti Bladewind (datepicker compreso) restano chiari. Sincronizzare la classe `dark` su `<html>` con `matchMedia` in uno script nel `<head>`
  - [x] 10.4quater (risolto in modo diverso dal suggerito: il datepicker resta `format="dd/mm/yyyy"`, il controller valida `date_format:d/m/Y` e converte con `Carbon::createFromFormat`; nessun `range`; c'è il pulsante "Cerca") Storico: Datepicker: `format="dd/mm/yyyy"` invia al server `01/07/2026`, che MariaDB non legge come data (confronto sbagliato = tutte le carte) e che `date` di Laravel interpreta come mese/giorno; `range="true"` (e anche `range="false"`, stringa truthy) invia un intervallo `da - a`, mentre `since` è una data sola; manca un pulsante di invio. Usare `format="yyyy-mm-dd"`, nessun attributo `range`, `date_format:Y-m-d` nella validazione e un `<x-primary-button>`
- [x] 10.5 Allineare `resources/views/emails/md-new-cards.blade.php` (ex `emails/new-cards`): rotte `cards.new-releases` e `cards.show`, `since` in formato `gg/mm/aaaa` con `$cards?->first()?->release_date?->format('d/m/Y') ?? Carbon::today()->format('d/m/Y')` (nessun errore con collezione vuota). Differenza dal piano: il testo dello Step 10.5.1 diceva `toDateString()`, superato dal formato `gg/mm/aaaa` di `/nuove-uscite`; verificare comunque l'anteprima `/render-mail/new-cards` nel browser (2026-10-09)
  - [ ] 10.4quinquies `CardController::newReleases` ha ancora un `Log::debug(...)` con l'intero paginatore (residuo di debug, come 10.1.2bis); nell'avviso di `cards/new-releases` `$cards->count()` e `$groups->count()` contano solo la pagina corrente, non il totale (`$cards->total()`)
> 10.2 Statistiche mazzo: **spostato nel piano 07** (Step 7.8, vedi sezione 07). Il numero 10.2 resta vuoto per non rinumerare gli step successivi.

## 06 — Mazzi, dominio (Fase 7a) ✅ `implementationPlan-V-06-ricostruzioneMazziDominio.md` (Step 7.1–7.5 applicati il 2026-10-09; restano da scrivere i test dei validator, 7.5bis)
- [x] 7.1 Enum `DeckFormat` + cast su `Deck`
- [x] 7.2 `Deck::leader()`/`base()` sostituite da `leaders()`/`baseCard()` (`belongsToMany` su `deck_cards` filtrate per `cards.type`)
- [x] 7.3 Validator per formato in `app/Services/DeckValidation/` (interfaccia `DeckFormatValidator`). Scostamenti dal piano, tutti voluti: il minimo di carte è controllato sulla somma delle copie escluse leader e base (50 per Premier/Eternal, 80 per Twin Suns; +10 se la base è `JTL24`, Data Vault); `unique_card` non limita le copie nel mazzo (solo quelle in gioco), quindi il limite è `max_copies ?? 3` ovunque tranne Twin Suns (`max_copies ?? 1`); Premier controlla la rotazione con `Expansion::validExpansions()` (ultime due rotazioni distinte, solo espansioni confermate) invece del TODO del piano
  - [x] 7.3.4 Twin Suns: l'allineamento dei leader non richiede nessuna colonna nuova (il TODO del piano è superato): `Card::secondaryAspect()` restituisce l'aspetto Eroismo/Malvagità e il validator dà errore se i due leader ne hanno due diversi (stesso aspetto, uno solo o nessuno = legale). Step 7.3.4 del piano riscritto di conseguenza (2026-10-09)
- [x] 7.4 `DeckFormatValidatorFactory`
- [x] 7.5 `DeckPolicy` (`view` accetta anche gli ospiti; scoperta in automatico da Laravel)
  - [ ] 7.5bis Test Pest dei validator (nessun test sui mazzi esiste ancora): carte contate per copie, leader/base mancanti (nessuna eccezione senza base), rotazione di Premier, aspetti secondari di Twin Suns nei quattro casi
  - [ ] 7.5ter `'JTL24'` (Data Vault) è scritto a mano nei tre validator: valutare una costante o un campo sulla carta

## 07 — Mazzi, pagine (Fase 7b) `implementationPlan-07-ricostruzioneMazziPagine.md` (stato verificato sul codice il 2026-10-11; il piano è completo, restano da applicare gli step aperti. Ordine consigliato: 7.8.1–7.8.2 → 7.6.5 → 7.6.6 → 7.6.6bis → 7.7 → 7.8.3–7.8.4 → 7.9)
- [x] 7.6.0 Migration `add_unique_user_name_version_to_decks_table` (`2026_10_09_124754`): `UNIQUE(user_id, name, version)`
- [x] 7.6.1 Rotte mazzi applicate (`decks.index/create/store`, gruppo `mazzo/modifica/...`, `decks.versions`, `decks.show` con `whereNumber('version')`). Mancano solo quelle degli step nuovi: `decks.statistics` (7.8.2), `decks.search-cards` (7.6.6.1), `decks.export` e `decks.import*` (7.7.3)
- [x] 7.6.2 `DeckController` applicato (risoluzione deck, `Gate::authorize`, versione nell'URL, `syncCards` batch, `addCard` che somma, `createVersion`, `versions`). Da aggiungere: `statistics` (7.8.1), `searchCards` (7.6.6.2), `export` (7.7.3); da correggere `index` e `show` (7.6.5.2, 7.6.5.3)
- [x] 7.6.3 Viste mazzi create (`decks/index`, `create`, `show`, `edit`, `versions`); correzioni e parti mancanti nei punti seguenti
  - [x] Fix dell'utente (2026-10-11): placeholder "Nessun leader." e "Nessuna base." in `decks/index` per i mazzi senza leader o base
- [x] 7.6.4 Decisioni chiuse: rotte contestuali all'utente, show per chi possiede/pubblico, edit per proprietario, versioning snapshot on-demand, salvataggio batch primario
- [ ] 7.6.5 Correzioni al codice applicato (bug trovati il 2026-10-11)
  - [x] 7.6.5.1 (applicato dall'utente il 2026-10-11: `$deck->baseCard->first()` in `decks/index` e `decks/show`; in `show` la base ha ancora `h-12` invece di `h-40` come i leader, da allineare) Storico: `Deck::baseCard()` è una collection: in `decks/index` e `decks/show` usare `$deck->baseCard->first()`. Il fix dei placeholder non basta: `$deck->baseCard?->front_art_path` lancia `Property [front_art_path] does not exist on this collection instance` anche per un mazzo senza base (la collection non è `null`, è vuota), quindi la lista `/mazzi` e ogni pagina `show` danno errore
  - [ ] 7.6.5.2 `index` mostra ogni versione come mazzo a sé: solo l'ultima (`whereDoesntHave('nextVersion')`, `orWhere` raggruppato)
  - [ ] 7.6.5.3 `show`: `$isLatest`, avviso di sola lettura sulle versioni precedenti, "Modifica mazzo" solo sull'ultima, link "Statistiche" con la versione, pulsanti di export
  - [ ] 7.6.5.4 Pulsante "Importa mazzo" in `decks/index`
  - [x] 7.6.5.5 Decisioni: `<x-deck-card-row>` rimandato al piano 08 (Step 8.3.4), riga inline in `edit`; validazione di formato sempre informativa
- [ ] 7.6.7 Lista mazzi: copertina con leader e base sovrapposti (richiesta del 2026-10-11): componente `resources/views/components/deck-cover.blade.php` (leader davanti, base dietro spostata del 25% in alto e a destra; Twin Suns = metà sinistra dei due leader affiancate), `decks/index` con una sola colonna "Leader / Base" (`colspan` 5). Nella lista sostituisce la cella base di 7.6.5.1; `show` usa ancora `->first()`
  - [ ] 7.6.7.1 Componente `<x-deck-cover>` (Twin Suns riconosciuto da `leaders->count() >= 2`, placeholder "Nessun leader."/"Nessuna base.")
  - [ ] 7.6.7.2 `decks/index.blade.php`: intestazione e riga con `<x-deck-cover :deck="$deck" />`
  - [ ] 7.6.7.4 Verifica a mano su `/mazzi` (Premier/Eternal, Twin Suns, senza base, senza leader, tema scuro, mobile)
- [x] 7.6.6 (applicato dall'utente il 2026-10-12) Ricerca carte nella pagina di modifica (oggi `decks/edit` non permette di aggiungere nessuna carta: c'è solo un commento): rotta `decks.search-cards`, `DeckController::searchCards` (JSON, `CardSearch`, max 20 risultati, solo tipi da mazzo), `edit.blade.php` completo con componente Alpine `deckEditor` (leader e base sostituiti al cambio) e avviso `import-warnings`
- [ ] 7.6.6bis Ricerca carte dell'editor con `<x-cards-filter>`, paginata (30), e avviso di modifiche non salvate (richiesta del 2026-10-12; da applicare nell'ordine 1 → 5)
  - [x] 7.6.6bis.1 `CardSearch::FILTER_KEYS` e `CardSearch::filterOptions()` (opzioni e limiti del filtro); `CardController::index` li usa (refactor puro, `/carte` invariata) e perde i `use` di `Aspect`, `CardTrait`, `Expansion`
  - [x] 7.6.6bis.2 `cards-filter` incorporabile: prop `embedded` e `open`, `{{ $attributes }}` sul `<form>`, "Azzera" come pulsante con evento `cards-filter-reset` ascoltato da `range-slider` e `multi-datalist`
  - [x] 7.6.6bis.3 `DeckController::edit` passa le opzioni del filtro (tipi solo da mazzo); `searchCards` con tutti i filtri di `/carte`, `nome` non più obbligatorio, `paginate(30)`, risposta `{data, current_page, last_page, total}`
  - [x] 7.6.6bis.4 `decks/edit`: `x-data` sul `<div>` esterno, card "Aggiungi carte" con `<x-cards-filter>` fuori dal form del mazzo, risultati scorrevoli, "N di TOT carte", "Carica altre carte"; `search(form)` con `FormData` e `criteria`
  - [x] 7.6.6bis.5 `beforeunload` in `deckEditor` (`snapshot()`, `dirty`, `init()`, `submitting` da `@submit` sul form di salvataggio) e indicatore "Modifiche non salvate"
  - [ ] 7.6.6bis.6 Verifica a mano (`/carte` com'era, filtri nell'editor, più di 30 risultati, Azzera, avviso su chiusura/link, nessun avviso dopo il salvataggio)
- [ ] 7.7 Export/Import mazzi (formati ripresi dalla vecchia SWUDB: JSON ufficiale `{ESP}_{numero}`, TXT a sezioni, link swudb.com)
  - [ ] 7.7.1 `DeckExporter` (`toText`, `toJson`)
  - [ ] 7.7.2 `DeckImporter` (`fromContent` JSON/TXT, `fromUrl`; fail-soft con `notFound`, sideboard ignorata, solo https e host pubblici, nessun redirect; i tre casi limite dell'URL: host non raggiungibile, redirect, content-type inatteso)
  - [ ] 7.7.3 `DeckController::export` + rotta `decks.export`; `DeckImportController` + rotte `decks.import`/`decks.import.store` + vista `decks/import`
  - [ ] 7.7.0bis Da verificare su un export reale: chiave `secondleader` (Twin Suns) e `secondLeader` nel JSON nativo di swudb.com; id con o senza zeri iniziali
  - Limite noto: i nomi in DB sono in italiano, quindi l'import TXT per nome funziona solo con file esportati da questo sito; per file con nomi inglesi si usa il JSON (id)
- [x] 7.8 Statistiche mazzo incorporate e migliorie UI show/edit:
  - [x] 7.8.1 Calcolo statistiche in `DeckController::getDeckStatisticsData(Deck $deck)` (curva costi per tipo, potenza e vita medie per costo, aspetti con multi-colori, rarità con palette ufficiale, tratti, leader e base esclusi)
  - [x] 7.8.2 Rotta `decks.statistics` reindirizza a `decks.show` con fragment `#statistiche`
  - [x] 7.8.3 Statistiche incorporate a fondo pagina sia in `decks/show` sia in `decks/edit` tramite partial `resources/views/decks/partials/statistics.blade.php` (Chart.js via CDN); rimossa la vista autonoma `decks/statistics`
  - [x] 7.8.4 Pagina `show`: unità raggruppate assieme per preservare l'ordinamento naturale di montaggio del mazzo, con indicatore di arena (`Terrestre` / `Spaziale`) su ciascuna riga
  - [x] 7.8.5 Pagina `edit`: link alle carte nella lista, tipo carta allineato con colonna fissa (`w-28`), rimosso pulsante salva ridondante in alto (indicatore modifiche non salvate spostato in basso)
  - [x] 7.8.6 Anteprima carta (desktop hover con overlay fluttuante e mobile touch con popup modal) su entrambe le pagine (`decks/partials/card-preview.blade.php`)
  - [x] 7.8.7 Grafici avanzati statistiche: rimossa quantità carte, grafico line/diffusione potenza e vita medie per costo (rosso/blu), curva dei costi con 3 toggle a `<x-badge>` (Unità, Miglioria, Evento), grafico a torta degli aspetti con fette zebrate multi-aspetto e grafico a torta delle rarità con colori specificati
  - [x] 7.8.8 Test `DeckStatisticsTest` aggiornati e passati (8/8)
- [ ] 7.9 Test Pest dei mazzi: helper `makeCard`/`makeDeck` in `tests/Pest.php`, `DeckExporterTest`, `DeckImporterTest` (con i tre casi limite dell'URL), `DeckPagesTest` (lista, show, edit, ricerca, versioni, export, import, regressioni di 7.6.5), `DeckStatisticsTest` (medie pesate per copie)

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

## Bug fix 2026-10-14
- [x] `decks.edit` andava in errore (`array_slice()` su Collection) perché `$traits` del filtro carte sovrascriveva i tratti del mazzo: le statistiche ora espongono `deckTraits` come Collection (controller, partial `statistics`, test di regressione in `DeckStatisticsTest`)

## Backlog (non pianificato in dettaglio)
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (Aggro/Control/Budget/Meta)
- Modalità offline/PWA
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica probabilità ipergeometrica nelle statistiche mazzo
- Apertura digitale dei booster pack
