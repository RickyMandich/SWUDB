# Implementation Plan — Ricostruzione UnlimitedDB (indice)

> Il piano unico è stato diviso in parti **numerate nell'ordine in cui vanno eseguite**. Questo file è l'indice e raccoglie i riferimenti comuni a tutte le parti (schema del database, convenzioni per le view, note di analisi);
> non contiene step da eseguire.
>
> Ambiente: `C:\Users\RickyMandich\PROJECT\unlimiteddb`, Laravel 12, PHP 8.2 (cmd.exe), MariaDB, Pest, Breeze, deploy Docker + Traefik su VM Oracle (`*.mandich.dev`).
> Progresso puntuale: [`todo.md`](todo.md). Stato del codice: [`README.md`](README.md).

## Come si usa

### Ordine di esecuzione
Eseguire i piani in ordine di numero. Ogni piano elenca in testa i propri prerequisiti. Quando un piano è completato si rinomina da `implementationPlan-NN-...md` a `implementationPlan-V-NN-...md`
(i file `implementationPlan-V-*.md` sono ignorati da Git), e si spunta `todo.md`.

| N. | File | Contenuto | Stato |
|---|---|---|---|
| 00 | [`implementationPlan-V-00-ricostruzioneFondamenta.md`](implementationPlan-V-00-ricostruzioneFondamenta.md) | Fasi 1–4bis: setup, Docker, auth, Resend, import carte, worker | ✅ completato |
| 01 | [`implementationPlan-V-01-ricostruzioneTelegramService.md`](implementationPlan-V-01-ricostruzioneTelegramService.md) | Fase 9a: `TelegramService` | ✅ completato |
| 02 | [`implementationPlan-V-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-V-02-ricostruzioneAllineamentoSchema.md) | Fase 4ter: pivot con `card_id`, relazioni, `publishedAt`, test del job | ✅ completato |
| 03 | [`implementationPlan-03-ricostruzioneAdminErrori.md`](implementationPlan-03-ricostruzioneAdminErrori.md) | Fase 5: viste admin e email di scan | **quasi completato**: viste, controller e job applicati, restano gli Step 5.5.5 e 5.5.6 (mail agli admin con `Throwable` invece di `SystemError`, test del job da riallineare) |
| 04 | [`implementationPlan-04-ricostruzioneAdminEspansioni.md`](implementationPlan-04-ricostruzioneAdminEspansioni.md) | Fase 6: pagina admin espansioni | da fare |
| 05 | [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md) | Fase 10: ricerca carte, dettaglio, nuove uscite, navigazione per ospiti (Step 10.2 dopo il 07) | da fare |
| 06 | [`implementationPlan-06-ricostruzioneMazziDominio.md`](implementationPlan-06-ricostruzioneMazziDominio.md) | Fase 7a: enum, relazioni, validator, policy dei mazzi | da fare |
| 07 | [`implementationPlan-07-ricostruzioneMazziPagine.md`](implementationPlan-07-ricostruzioneMazziPagine.md) | Fase 7b: pagine mazzi, export/import | da fare |
| 08 | [`implementationPlan-08-ricostruzioneCollezione.md`](implementationPlan-08-ricostruzioneCollezione.md) | Fase 8: collezione, carte mancanti/presenti | da fare |
| 09 | [`implementationPlan-09-ricostruzioneBotTelegramWebhook.md`](implementationPlan-09-ricostruzioneBotTelegramWebhook.md) | Fase 9b: webhook, comandi, `NotifyAdminJob` | da fare |
| 10 | [`implementationPlan-10-ricostruzioneApiPubblica.md`](implementationPlan-10-ricostruzioneApiPubblica.md) | Fase 11: Sanctum, API pubblica, Resources | da fare |
| 11 | [`implementationPlan-11-ricostruzioneDeploy.md`](implementationPlan-11-ricostruzioneDeploy.md) | Fase 12: verifica post-deploy e redeploy pulito | da fare |

### Piani trasversali (non numerati)
| File | Contenuto | Stato |
|---|---|---|
| [`implementationPlan-githubActionBuildGhcr.md`](implementationPlan-githubActionBuildGhcr.md) | Build dell'immagine su GitHub Actions (ghcr.io) e deploy con pull sulla VM, branch `new` | da fare |
| `implementationPlan-V-autoRebuildAssetsOnDockerBuild.md`, `implementationPlan-V-fixCardAspectMigration.md`, `implementationPlan-V-updateFakeCardEntryFromJson.md`, `implementationPlan-V-updateLogoComponent.md` | modifiche puntuali già applicate | ✅ completati |

### Struttura dei file (per l'indice dell'editor Markdown)
Ogni parte usa la stessa gerarchia di titoli: `#` titolo del piano, `##` fase, `###` step, `####` sotto-punto operativo. Ogni cosa da fare è un titolo numerato (`Step N.M`, `N.M.k`), quindi un editor con outline la indicizza da solo.

### Convenzione di codice
PHPDoc bilingue (EN tecnico + IT descrittivo) su ogni metodo non ovvio:
```php
/**
 * English technical description of the method
 * Descrizione italiana "alla buona" del metodo
 *
 * @param Type $parameter Description of parameter
 * @return ReturnType Description of return value
 */
```

## Schema del database (riferimento per tutte le parti)
Tabelle applicative decise, con ogni colonna e il perché. Le parti rimandano qui invece di ripetere lo schema. Dove il codice attuale differisce, è indicato.

### `expansions`
| Colonna | Tipo | Note |
|---|---|---|
| `expansion` | string(10), **PK** | Codice naturale (es. `SOR`, `SHD`): è già la chiave con cui gioco, community e API riconoscono l'espansione, un id numerico sarebbe una duplicazione. |
| `legal_date` | date, nullable | Da quando le carte dell'espansione sono legali in torneo (diverso da `cards.release_date`). |
| `rotation` | string(1), default `'0'` | Etichetta della finestra di rotazione (Premier ammette solo le ultime due). |
| `confirmed` | boolean, default `false` | Un admin ha verificato/corretto i dati (piano 04). |
| `group_main_expansion` | string(10), nullable, **self-FK** | `null` = standalone; uguale al proprio codice = è la principale del gruppo; altro codice = dipende da quella. Propedeutico all'apertura digitale dei booster. |
| `created_at`/`updated_at` | timestamp | |

**Nota token**: i segnalini di un'espansione vivono sotto un codice con prefisso `T` (token di `SOR` → `TSOR`) e sono righe `expansions` a sé stanti: tenerlo a mente in import e filtri.

### `cards`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | string(20), **PK** | `"{expansion}{number}"` (es. `JTL256`), generato dal modello `Card` (hook `creating`), non da una colonna generata del DB (Eloquent non rilegge i valori generati). È la PK su schema ed Eloquent (`$primaryKey = 'id'`, `$incrementing = false`, `$keyType = 'string'`) e il riferimento usato dalle pivot. |
| `expansion` | string(10), FK → `expansions.expansion` | `UNIQUE` insieme a `number`. |
| `number` | unsigned integer | |
| `cid` | string, **unique** | `cardUid` dell'API ufficiale: chiave di lookup dell'upsert e identificativo pubblico stabile. **Non** è la PK né una FK delle pivot. |
| `unique_card` | boolean, default `false` | Regola "Unica" del gioco. |
| `name` | string | Nome della carta. |
| `title` | string, nullable | Sottotitolo. |
| `type` | enum SQL (`Unit`, `Upgrade`, `Event`, `Leader`, `Base`, `CreditToken`, `ForceToken`, `TokenUnit`, `TokenUpgrade`) | Secondo l'API SWU. Opzionale un enum PHP `CardType` con cast, stesso pattern di `DeckFormat`. |
| `rarity` | enum SQL (`Comune`, `Non Comune`, `Rara`, `Leggendaria`, `Speciale`) | Valori **in italiano**, come li restituisce l'API ufficiale con `locale=it` (l'import li salva così com'è). |
| `cost`, `health`, `power` | unsigned tinyint, nullable | `health` e `power` solo per le unità. |
| `text` | text, nullable | Testo delle abilità. |
| `arena` | string, nullable | Arena in cui si gioca un'unità. |
| `artist` | string, nullable | |
| `front_art_path`, `back_art_path` | string, nullable | Path **relativo** nel disk `public` (`cards/{expansion}/{number}-front.{ext}`): le immagini sono scaricate in locale durante l'import. |
| `max_copies` | unsigned tinyint, nullable | Copie massime in un mazzo, valorizzato solo se non è lo standard (eccezione JTL #256 = 15). |
| `release_date` | date, nullable | Quando **questa carta** è uscita, serve alla pagina "Nuove uscite". |
| `created_at`/`updated_at` | timestamp | |

### `aspects` + `card_aspect`
`aspects`: `id`, `name`, `color`, `slug`, `order`, timestamps. `card_aspect`: `card_id` (FK `cards.id`), `aspect_id` (FK `aspects.id`), PK composta, timestamps.
Tabella dedicata invece di una colonna JSON: filtro per aspetto con una join indicizzata, colore/slug/ordine centralizzati per la UI.

### `traits` + `card_trait`
`traits`: `name` (string, **PK**), timestamps. `card_trait`: `card_id` (FK `cards.id`), `trait_name` (FK `traits.name`), PK composta, timestamps.
Il modello si chiama `App\Models\CardTrait` perché `Trait` è una parola riservata di PHP (`$primaryKey = 'name'`, `$incrementing = false`, `$keyType = 'string'`); la tabella resta `traits`.

> **Stato del codice**: le tre pivot (`card_aspect`, `card_trait`, `deck_cards`) hanno la colonna `card_id` (piano 02, applicato). Le pivot `card_aspect` e `card_trait` hanno PK composta `card_id`+`aspect_id` / `card_id`+`trait_name`; `deck_cards` ha PK `deck_id`+`card_id`.

### `decks`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint, PK | Un mazzo non ha un codice naturale stabile. |
| `user_id` | FK `users.id` | |
| `name` | string | Univoco per utente (piano 07/10: rotte `/mazzo/{username}/{deckname}`). |
| `format` | enum SQL (`premier`, `eternal`, `twin_suns`), default `premier` | Cast a `DeckFormat` (piano 06). |
| `is_public` | boolean, default `false` | |
| `assembled` | boolean, default `false` | Se il mazzo è "montato" fisicamente: serve al calcolo delle carte impegnate altrove (piano 08). |
| `version` | integer, default `1` | |
| `previous_version_id` | nullable, self-FK su `decks.id` (`nullOnDelete`) | Catena reale delle versioni. |
| `created_at`/`updated_at` | timestamp | |

Niente `leader_card_id`/`base_card_id`: la cardinalità dipende dal formato (Eternal/Premier 1+1, Twin Suns 2 leader + 1 base), il ruolo si deduce da `cards.type` via `deck_cards`.

### `deck_cards`
`deck_id` (FK `decks.id`), `card_id` (FK `cards.id`), `quantity` (unsigned tinyint), timestamps. PK composta `(deck_id, card_id)`. Niente colonna `role`: `cards.type` distingue già `Leader`/`Base`.
Cardinalità e allineamento dei leader li verifica il validator del formato (piano 06), non lo schema.

### `collection_cards` (da creare, piano 08)
`user_id` (FK), `card_id` (FK), `variant` (enum `normal`, `foil`, `hyper`, `prestige`, `hyper_foil`, default `normal`), `quantity` (unsigned smallint), `UNIQUE(user_id, card_id, variant)`. Le varianti di stampa vivono **solo qui**, non nei mazzi.

### `system_errors`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint, PK | |
| `source` | string | Classe/job che ha generato l'errore. |
| `message` | text | Motivo specifico: finisce anche nella mail agli admin. |
| `stack_trace` | text, nullable | |
| `context` | json, nullable | Dati aggiuntivi (payload della carta, messaggio dell'eccezione). |
| `status` | string: `open`/`resolved`/`ignored`, default `open` | Tre stati, non un booleano: "sistemato" e "non è un problema" sono cose diverse. |
| `resolved_at` | nullable timestamp | Valorizzato solo quando lo stato è `resolved`. |
| `created_at`/`updated_at` | timestamp | |

## Convenzioni per le view (riferimento per tutte le parti)
Stack: Blade puro (niente Livewire/Inertia/Vue: la lentezza della vecchia versione era un bug preciso di Livewire), Tailwind con classi utility, Alpine.js solo per interattività leggera.

### Corrispondenza cartella, vista e rotta
Sempre la stessa tripla: `view('admin.users.index')` ⇄ `resources/views/admin/users/index.blade.php` ⇄ nome rotta `admin.users.index`. La cartella prende il primo segmento di rotta:
`decks.*` → `resources/views/decks/`, `collection.*` → `collection/`, `cards.*` → `cards/`. Il namespace `Admin\` dei controller si riflette solo sotto `admin.`/`admin/`.

### Layout
Due soli layout radice (Breeze), non crearne altri: `<x-app-layout>` per ogni pagina con navigazione, pubblica o autenticata (titolo nello slot `header`, come `dashboard.blade.php`),
`<x-guest-layout>` solo per le pagine di autenticazione. `<x-app-layout>` è utilizzabile dagli ospiti solo dopo lo Step 10.3 del piano 05 (la navigazione oggi legge `Auth::user()` senza controlli).
Pubblico vs autenticato non è una cartella diversa: è solo il gruppo di middleware della rotta.

### Componenti condivisi già presenti
`<x-primary-button>`, `<x-secondary-button>`, `<x-danger-button>`, `<x-text-input>`, `<x-input-label>`, `<x-input-error>`, `<x-modal>`, `<x-dropdown>`, `<x-dropdown-link>`, `<x-nav-link>`, `<x-responsive-nav-link>`, `<x-flash-message>`, `<x-badge>`, `<x-application-logo>`: usarli sempre, niente `<input>`/`<button>` nudi.
Ogni nuova sezione con pagina indice va aggiunta a **entrambi** i blocchi (desktop e responsive) di `layouts/navigation.blade.php`.
Le icone del sito sono incluse da `layouts/favicons.blade.php`.

### Componenti estratti al piano 03
- `<x-flash-message>`: file `components/flash-message.blade.php` (il refuso `flash-massage` è stato corretto). Supporta `session('status_level')` (`success`/`error`/`warning`, altrimenti blu).
- `<x-badge :color="...">`: usato per lo stato degli errori (`SystemError::statusColor()`); da riusare per gli aspetti carta.

### Stile Tailwind
- Contenitore pagina: `<div class="max-w-{2xl|4xl|5xl} mx-auto py-6">` (cambia solo la larghezza).
- Titolo pagina nello slot `header`: `font-semibold text-xl text-gray-800 leading-tight`.
- Tabelle: `w-full text-left border-collapse`, celle con `px-3 py-2 border-b`, dentro `<div class="overflow-x-auto">`; lo stesso stile su tutte le tabelle admin.
- Form: `@csrf` subito dopo l'apertura, `@method(...)` subito dopo `@csrf`; errori con `<x-input-error :messages="$errors->get('campo')" class="mt-2" />` sotto ogni campo.
- Non passare mai a una vista una variabile chiamata `$errors`: sovrascrive quella di Laravel (bug corretto al piano 03, Step 5.4.8).

### Viste mail
Pipeline separata: **non** estendono `<x-app-layout>`, usano i componenti Markdown di Laravel (`<x-mail::message>`, `<x-mail::button>`, `<x-mail::table>`).

### Grafici
Unica eccezione al "solo Blade + Alpine": Chart.js via CDN nella pagina statistiche mazzo, con `<canvas>` e `@json(...)`.

## Note di analisi (perché queste scelte)
- **Queue reali invece di fireAndForget**: niente thread simulati via HTTP ricorsivo per aggirare l'assenza di code su Altervista. https://laravel.com/docs/12.x/queues
- **Permessi granulari (Spatie)**: permessi singoli, i ruoli sono scorciatoie per assegnarne un gruppo insieme. https://spatie.be/docs/laravel-permission/v6/introduction
- **Enum + Strategy per i formati mazzo**: aggiungere un formato richiede solo una nuova classe.
- **Blade + Alpine invece di Livewire**: evita per design il rischio della vecchia versione (catalogo intero come proprietà pubblica Livewire).
- **`.env-overrides`**: pattern SWUDB per tracciare `APP_VERSION` in Git.
- **Verifica email nativa**: sostituisce i token custom con `MustVerifyEmail` + middleware `verified`.
- **`system_errors` con 3 stati**: la correttezza dell'import la verifica Pest, non un controllo post-hoc in produzione.
- **Nessuna colonna `deck_cards.role`**: dedotta da `cards.type`.
- **`decks.assembled`**: serve a calcolare non solo cosa manca ma anche cosa si possiede ed è impegnato in un altro mazzo montato.
- **Immagini scaricate in locale**: il sito non dipende dal CDN ufficiale a runtime.
- **Icone generate dall'immagine Docker** a partire da `public/icon-mine.svg`: modificare l'SVG e ricostruire basta, niente rigenerazione manuale.
- **Un'immagine Docker sola per `app` e `worker`**: stesso codice, comando diverso (`php-fpm` / `queue:work`); in dev `image: unlimiteddb:dev` (build solo su `app`), in produzione `ghcr.io/rickymandich/swudb:new` (piano `githubActionBuildGhcr`).
- **Log viewer** (`opcodesio/log-viewer`): pannello dei log dietro il permesso `log.viewer`, linkato dal menu utente.
- Il file `implementationPlan-ricostruzioneUnlimitedDB.pdf` è una vecchia esportazione del piano unico e non è più aggiornato (è in `.gitignore`): da rigenerare da questo indice se serve.

## Backlog (non pianificato in dettaglio)
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (Aggro/Control/Budget/Meta)
- Modalità offline/PWA per consultazione carte
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Probabilità ipergeometrica di pescare una carta nelle statistiche mazzo (piano 05, Step 10.2)
- Apertura digitale dei booster pack (propedeutico `expansions.group_main_expansion`)

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
| Filesystem/Storage | https://laravel.com/docs/12.x/filesystem |
| Mail | https://laravel.com/docs/12.x/mail |
| Resend (mailer Laravel) | https://laravel.com/docs/12.x/mail#resend-driver |
| Resend + Laravel | https://resend.com/docs/send-with-laravel |
| Resend domini/DNS | https://resend.com/docs/dashboard/domains/introduction |
| Testing (Pest) | https://laravel.com/docs/12.x/testing |
| Spatie Laravel-permission | https://spatie.be/docs/laravel-permission/v6/introduction |
| Verifica email | https://laravel.com/docs/12.x/verification |
| Sanctum (API auth) | https://laravel.com/docs/12.x/sanctum |
| API Resources | https://laravel.com/docs/12.x/eloquent-resources |
| Rate limiting rotte | https://laravel.com/docs/12.x/routing#rate-limiting |
