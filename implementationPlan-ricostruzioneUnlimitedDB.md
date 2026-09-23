# Implementation Plan — Ricostruzione UnlimitedDB (esercizio guidato)

> Guida passo-passo da eseguire manualmente. Le fasi già completate sono riassunte in breve; le fasi/step da fare restano nel dettaglio completo: modelli, migration (con ogni colonna e il motivo), pagine, job/service.
>
> Ambiente: `C:\Users\RickyMandich\PROJECT\unlimiteddb`, Laravel 12.12, PHP 8.2.29 (cmd.exe), MariaDB, Pest, Breeze, deploy Docker+Traefik su VM Oracle (`*.mandich.dev`).
>
> **Convenzione di codice**: PHPDoc bilingue (EN tecnico + IT descrittivo) su ogni metodo non ovvio:
> ```php
> /**
>  * English technical description of the method
>  * Descrizione italiana "alla buona" del metodo
>  *
>  * @param Type $parameter Description of parameter
>  * @return ReturnType Description of return value
>  */
> ```
>

---

## ✅ Fase 1 — Setup progetto (completata)
Breeze e `spatie/laravel-permission` installati; `.env` configurato (MariaDB, `QUEUE_CONNECTION=database`); tabelle `jobs`/`failed_jobs` e tabelle Spatie migrate; `.env-overrides` agganciato in `bootstrap/app.php`.

## ✅ Fase 2 — Ambiente Docker locale (completata)
`.dockerignore`/`.gitignore` con esclusione `bootstrap/cache/*.php`; `Dockerfile`, `entrypoint.sh`, nginx conf, `init.sql`, `docker-compose.dev.yml` allineati a quanto genera `new-site.sh` in produzione; verificato su `http://localhost:66`.

---

## Schema del database (riferimento per tutte le fasi seguenti)

> Questa sezione raccoglie **tutte** le tabelle applicative decise finora, con ogni colonna e il perché. Le fasi sotto rimandano qui invece di ripetere lo schema.

### `expansions`
| Colonna | Tipo | Note |
|---|---|---|
| `expansion` | string, **PK** | Codice naturale (es. `SOR`, `SHD`). Chiave primaria naturale, non un id surrogato: è già la chiave con cui gioco/community/API riconoscono l'espansione — un id numerico sarebbe una duplicazione senza vantaggi. |
| `legal_date` | date, nullable | Da quando le carte dell'espansione sono legali in torneo (diverso da `cards.release_date`, vedi sotto). |
| `rotation` | string | Etichetta della finestra di rotazione (Premier ammette solo le ultime due). Stringa perché è un'etichetta, non un booleano. |
| `confirmed` | boolean, default `false` | Un admin ha verificato/corretto i dati di questa espansione (vedi Fase 6). |
| `group_main_expansion` | string, nullable, **self-FK** su `expansions.expansion` | `null` = standalone; valore uguale al proprio codice = è lei la principale del gruppo; altro codice = dipende da quella. Propedeutico alla futura apertura digitale dei booster. |
| `created_at`/`updated_at` | timestamp | |

**Nota token**: nel vecchio sistema i segnalini (token) di un'espansione vivono sotto un codice con prefisso `T` (es. token di `SOR` → espansione `TSOR`), non sotto il codice dell'espansione originale. Vanno trattate come righe `expansions` a sé stanti — tienilo a mente quando scrivi l'import (Fase 4) e i filtri (Step 10.1), altrimenti "espansione" e "token di quell'espansione" si confondono nelle liste.

### `cards`
| Colonna | Tipo | Note |
|---|---|---|
| `expansion` | string, FK → `expansions.expansion`, **PK composita** con `number` | Riflette come le carte sono identificate nel gioco stesso (numero all'interno del set). |
| `number` | unsigned integer, **PK composita** | |
| `cid` | string, **unique** | Id naturale della carta secondo l'API ufficiale — usalo come riferimento nelle tabelle pivot (`card_aspect`, `deck_cards`, `collection_cards`) invece della coppia composita, molto più semplice nelle join. Nel payload reale dell'API il campo si chiama `cardUid` (non `cid`), vedi mapping in Fase 4/Step 4.5. **Decisione presa**: Eloquent non supporta nativamente PK composite, quindi sul modello `Card` `cid` è dichiarato come `$primaryKey` (`$incrementing = false`, `$keyType = 'string'`) al posto della coppia `(expansion, number)` — che resta comunque la PK reale a livello di schema SQL/migration, `cid` è solo la PK "pratica" lato Eloquent. |
| `unique_card` | boolean, default `false` | Rinominata da `unica` (evita ambiguità col termine "unique" usato anche per il vincolo SQL sulla colonna `cid`). Indica la regola "Unica" del gioco (una sola copia in gioco nello stesso momento). |
| `name` | string | Nome della carta. |
| `title` | string, nullable | Sottotitolo carta. |
| `type` | enum SQL nativo (`Unit`, `Upgrade`, `Event`, `Leader`, `Base`, `CreditToken`, `ForceToken`, `TokenUnit`, `TokenUpgrade`) | Tipo di carta secondo l'API ufficiale SWU — già applicato in migration. Se ti serve anche type-safety lato PHP (autocompletamento, `match` esaustivo) invece del solo vincolo a DB, valuta un enum backed `App\Enums\CardType` con cast sul modello `Card`, stesso pattern di `DeckFormat` (Step 7.1) — opzionale, dimmelo se lo vuoi e lo aggiungo come step. |
| `rarity` | string | Rarity della carta: "comune", "non comune", "rara", "leggendaria", "speciale" (in migration è già un enum SQL nativo `Common`/`Uncommon`/`Rare`/`Legendary`/`Special`, aggiornato qui di conseguenza). |
| `cost` | unsigned tinyint, nullable | Costo totale della carta in risorse per essere giocata. |
| `health` | unsigned tinyint, nullable | Punti ferita della carta, presente solo se è un'unità. |
| `power` | unsigned tinyint, nullable | Forza della carta, presente solo se è un'unità. |
| `text` | text | Testo delle abilità della carta. |
| `arena` | string, nullable | Se è un'unità, l'arena in cui viene giocata. |
| `artist` | string, nullable | Artista che ha realizzato l'illustrazione della carta. |
| `front_art_path`, `back_art_path` | string, nullable | Path **relativo** nel disk `public` di Laravel (fisicamente `storage/app/public/...`), es. `cards/{expansion}/{number}-front.{ext}` — non l'URL diretto dell'API ufficiale: le immagini vengono scaricate in locale durante l'import (Step 4.6), così il sito non dipende dalla disponibilità del CDN ufficiale a runtime. L'estensione `{ext}` si determina al momento del download (content-type), non è detto sia sempre `.png`. |
| `max_copies` | unsigned tinyint, nullable, default `null` | Quante copie di questa carta il giocatore può avere in un deck (valorizzato solo se non è il valore standard). |
| `release_date` | date, nullable | Quando **questa carta** è uscita al pubblico — diverso da `expansions.legal_date` (uscita di una carta vs legalità di un'intera espansione), serve per la pagina "Nuove uscite" (Step 10.4). |
| `created_at`/`updated_at` | timestamp | `updated_at` utile per capire quando una carta è stata corretta dall'ultimo scan. |

### `aspects` + `card_aspect` (pivot)
`aspects`: `id`, `name`, `color`, `slug`, `order` (per l'ordinamento in UI), timestamps.
`card_aspect`: `cid` (FK `cards.cid`), `aspect_id` (FK `aspects.id`).
Tabella dedicata invece di una colonna `json` su `cards`: permette di filtrare per aspetto con una join indicizzata e centralizza colore/slug/ordine per la UI in un unico posto.

### `traits` + `card_trait` (pivot)
`traits`: `name` (string, **PK**), timestamps. Niente id surrogato né slug/color/order: il nome del tratto è già univoco e leggibile, stessa logica della PK naturale di `expansions`.
`card_trait`: `cid` (FK `cards.cid`), `trait_name` (FK `traits.name`).
Stesso motivo di `aspects`: la colonna `cards.traits` (stringa libera) sparisce, sostituita da questa tabella + pivot — permette filtri/elaborazioni per singolo tratto con una join indicizzata invece di fare parsing di una stringa (richiesto esplicitamente: elaborazioni sulla base dei tratti).

**Nota naming**: `Trait` è una parola riservata del linguaggio PHP (il costrutto `trait` per il riuso di codice tra classi) — non può essere usata da sola come nome di classe Eloquent. La tabella SQL può restare `traits` senza problemi (non è un identificatore PHP), il modello si chiama `App\Models\CardTrait` (nessun conflitto reale: il conflitto è solo sul nome nudo `Trait`) — con `$incrementing = false`, `$keyType = 'string'`, `$primaryKey = 'name'` dato che la PK non è un `id` auto-increment.

### `decks`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint, PK auto-increment | Qui un id surrogato ha senso: un mazzo non ha un codice naturale stabile come le carte/espansioni. |
| `user_id` | FK `users.id` | |
| `name` | string | |
| `format` | string, cast a `DeckFormat` enum | `premier`/`eternal`/`twin_suns`. |
| `is_public` | boolean, default `false` | |
| `assembled` | boolean, default `false` | Se il mazzo è "montato" fisicamente ora — serve al calcolo delle carte impegnate altrove (Step 8.3). |
| `version` | unsigned integer, default `1` | |
| `previous_version_id` | nullable, self-FK su `decks.id` | Catena reale delle versioni (self-FK), non un'inferenza sul nome come nella vecchia versione. |
| `created_at`/`updated_at` | timestamp | |

**Niente `leader_cid`/`base_cid` qui**: la cardinalità di leader/base dipende dal formato (Eternal/Premier: 1+1; Twin Suns: 2 leader+1 base, con vincolo di allineamento tra i due leader) — vedi `deck_cards` sotto (il ruolo si deduce da `cards.type`, non serve una colonna dedicata).

### `deck_cards`
`deck_id` (FK `decks.id`), `cid` (FK `cards.cid`), `quantity` (unsigned tinyint). PK composita `(deck_id, cid)`, già così in migration.
Niente colonna `role`: `cards.type` distingue già `Leader`/`Base` dagli altri tipi, quindi il ruolo di una riga in un mazzo si ottiene con un join su `cards.type` invece di duplicare l'informazione. Un mazzo Eternal/Premier ha una riga con `cid` di tipo `Leader` e una di tipo `Base`; Twin Suns ne ha due di tipo `Leader` e una di tipo `Base`. La cardinalità e il vincolo sull'allineamento li verifica il `DeckFormatValidator` del formato (Step 7.3), non lo schema.

### `collection_cards`
`user_id` (FK `users.id`), `cid` (FK `cards.cid`), `variant` (enum: `normal`, `foil`, `hyper`, `prestige`, `hyper_foil`, default `normal`), `quantity` (unsigned smallint). Chiave univoca composita `(user_id, cid, variant)`. Le varianti di stampa vivono **solo qui**, non nei mazzi (vedi discussione sulla vecchia `compositions`).

### `system_errors`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint, PK | |
| `source` | string | Classe/job che ha generato l'errore. |
| `message` | text | Motivo specifico (es. "carta {cid} già presente", "campo `cost` mancante nella risposta API") — nella vecchia versione questo dettaglio finiva anche nella mail agli admin, mantienilo. |
| `stack_trace` | text, nullable | Stack trace completo dell'errore. |
| `context` | json, nullable | Dati aggiuntivi (es. payload della carta che ha causato il problema). |
| `status` | string: `open`/`resolved`/`ignored`, default `open` | **Tre stati, non un booleano**: la vecchia versione aveva sia "segna come risolto" che "segna come ignorato" (`todo.md`) — un semplice `resolved` booleano perderebbe la distinzione tra "sistemato" e "non è un problema, ignoralo". |
| `resolved_at` | nullable timestamp | |
| `created_at`/`updated_at` | timestamp | |

---

## Fase 3 — Autenticazione e permessi

### ✅ Fatto
Breeze (Blade) installato; `HasRoles` su `User`; `PermissionSeeder` con `cards.import`, `cards.manage`, `decks.manage-any`, `collections.manage-any`, `users.manage`, `bot.notifications.receive`, ruolo `admin`.

### 🔧 Da fare

**Step 3.1 — Verifica email nativa**
```php
// app/Models/User.php
use Illuminate\Contracts\Auth\MustVerifyEmail;
class User extends Authenticatable implements MustVerifyEmail {}
```
```php
Route::middleware(['auth', 'verified'])->group(function () { /* rotte che richiedono email confermata */ });
```
Breeze genera già viste/rotte di verifica. Riferimento: https://laravel.com/docs/12.x/verification

**Step 3.2 — Pagina admin gestione utenti**
Obiettivo: una pagina `/admin/utenti` dove un admin vede tutti gli utenti e può assegnargli/togliergli ruoli e permessi, coerente con "miglioramento pagina utenti per la gestione di admin" della vecchia versione. Permesso già seedato: `users.manage` (Step Fase 3 ✅ Fatto), ruolo `admin` già creato con tutti i permessi.

1. **Controller**
   ```
   php artisan make:controller Admin/UserManagementController
   ```
   Crea `app/Http/Controllers/Admin/UserManagementController.php` con tre metodi:
   ```php
   namespace App\Http\Controllers\Admin;

   use App\Http\Controllers\Controller;
   use App\Models\User;
   use Illuminate\Http\RedirectResponse;
   use Illuminate\Http\Request;
   use Illuminate\View\View;
   use Spatie\Permission\Models\Permission;
   use Spatie\Permission\Models\Role;

   class UserManagementController extends Controller
   {
       /**
        * Lists every user with their assigned roles, for the admin overview table
        * Elenca tutti gli utenti con i ruoli assegnati, per la tabella di riepilogo admin
        */
       public function index(): View
       {
           $users = User::with('roles')->orderBy('name')->paginate(20);

           return view('admin.users.index', compact('users'));
       }

       /**
        * Shows the edit form for a single user: every role/permission plus which ones are currently assigned
        * Mostra il form di modifica di un utente: tutti i ruoli/permessi disponibili e quali sono già assegnati
        */
       public function edit(User $user): View
       {
           $roles = Role::orderBy('name')->get();
           $permissions = Permission::orderBy('name')->get();

           return view('admin.users.edit', compact('user', 'roles', 'permissions'));
       }

       /**
        * Overwrites the user's roles and direct permissions with whatever was checked in the form
        * Sovrascrive ruoli e permessi diretti dell'utente con quanto selezionato nel form
        */
       public function update(Request $request, User $user): RedirectResponse
       {
           $validated = $request->validate([
               'roles' => ['array'],
               'roles.*' => ['string', 'exists:roles,name'],
               'permissions' => ['array'],
               'permissions.*' => ['string', 'exists:permissions,name'],
           ]);

           $user->syncRoles($validated['roles'] ?? []);
           $user->syncPermissions($validated['permissions'] ?? []);

           return redirect()->route('admin.users.index')->with('status', 'Utente aggiornato.');
       }
   }
   ```
   `syncRoles`/`syncPermissions` (metodi di `HasRoles`, già sul model `User`) sostituiscono l'intero set con quello passato — così una checkbox deselezionata nel form revoca automaticamente, senza dover chiamare `revokePermissionTo` a mano riga per riga.

2. **Rotte** — in `routes/web.php`, sotto le rotte già esistenti (`require __DIR__.'/auth.php';` resta l'ultima riga):
   ```php
   use App\Http\Controllers\Admin\UserManagementController;

   Route::middleware(['auth', 'verified', 'permission:users.manage'])
       ->prefix('admin')
       ->name('admin.')
       ->group(function () {
           Route::get('/utenti', [UserManagementController::class, 'index'])->name('users.index');
           Route::get('/utenti/{user}/modifica', [UserManagementController::class, 'edit'])->name('users.edit');
           Route::put('/utenti/{user}', [UserManagementController::class, 'update'])->name('users.update');
       });
   ```
   Il middleware `permission:users.manage` **non funziona senza un passaggio in più**: da Laravel 11 in poi Spatie non registra più da solo l'alias `permission` nel router (lo faceva nelle versioni per Laravel ≤10, quando esisteva ancora `Kernel.php`). Va registrato a mano, una volta sola per tutto il progetto, in `bootstrap/app.php`:
   ```php
   use Illuminate\Foundation\Configuration\Middleware;
   use Spatie\Permission\Middleware\PermissionMiddleware;
   use Spatie\Permission\Middleware\RoleMiddleware;
   use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

   ->withMiddleware(function (Middleware $middleware): void {
       $middleware->alias([
           'role' => RoleMiddleware::class,
           'permission' => PermissionMiddleware::class,
           'role_or_permission' => RoleOrPermissionMiddleware::class,
       ]);
   })
   ```
   Senza questo, qualunque rotta con `permission:...`/`role:...` lancia `BindingResolutionException` ("Target class [permission] does not exist") invece di dare un 403 pulito — sintomo tipico: l'errore arriva dal Container, non da un `403 Forbidden` gestito.

3. **Vista lista** — `resources/views/admin/users/index.blade.php` (estende il layout Breeze con `x-app-layout`, gia' cablato correttamente da Breeze tramite `app/View/Components/AppLayout.php` → `layouts.app`, nessuna preparazione necessaria):
   ```blade
   <x-app-layout>
       <div class="max-w-4xl mx-auto py-6">
           <h1 class="text-xl font-semibold mb-4">Gestione utenti</h1>
           @if (session('status'))
               <div class="mb-4 text-green-600">{{ session('status') }}</div>
           @endif
           <table class="w-full text-left border-collapse">
               <thead>
                   <tr>
                       <th>Nome</th>
                       <th>Email</th>
                       <th>Ruoli</th>
                       <th></th>
                   </tr>
               </thead>
               <tbody>
                   @foreach ($users as $user)
                       <tr>
                           <td>{{ $user->name }}</td>
                           <td>{{ $user->email }}</td>
                           <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                           <td><a href="{{ route('admin.users.edit', $user) }}">Modifica</a></td>
                       </tr>
                   @endforeach
               </tbody>
           </table>
           {{ $users->links() }}
       </div>
   </x-app-layout>
   ```

4. **Vista modifica** — `resources/views/admin/users/edit.blade.php`, checkbox per ogni ruolo e ogni permesso, pre-selezionati se già assegnati:
   ```blade
   <x-app-layout>
       <div class="max-w-2xl mx-auto py-6">
           <h1 class="text-xl font-semibold mb-4">Modifica {{ $user->name }}</h1>
           <form method="POST" action="{{ route('admin.users.update', $user) }}">
               @csrf
               @method('PUT')

               <h2 class="font-medium mt-4">Ruoli</h2>
               @foreach ($roles as $role)
                   <label class="block">
                       <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                           @checked($user->hasRole($role->name))>
                       {{ $role->name }}
                   </label>
               @endforeach

               <h2 class="font-medium mt-4">Permessi diretti</h2>
               @foreach ($permissions as $permission)
                   <label class="block">
                       <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                           @checked($user->hasDirectPermission($permission->name))>
                       {{ $permission->name }}
                   </label>
               @endforeach

               <button type="submit" class="mt-4">Salva</button>
           </form>
       </div>
   </x-app-layout>
   ```
   `hasDirectPermission` (non `hasPermissionTo`) mostra solo i permessi assegnati **direttamente** all'utente, escludendo quelli ereditati da un ruolo — così la checkbox "Permessi diretti" non si sovrappone visivamente ai permessi già dati dal ruolo `admin`.

5. **Link in navigazione** — nel componente di navigazione di Breeze (`resources/views/layouts/navigation.blade.php`), aggiungi una voce visibile solo a chi ha il permesso:
   ```blade
   @can('users.manage')
       <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
           {{ __('Gestione utenti') }}
       </x-nav-link>
   @endcan
   ```
   `@can('users.manage')` funziona senza altro setup perché Spatie registra i permessi come Gate di Laravel automaticamente (il trait `HasRoles` sul model `User` collega `can()`/`@can` ai permessi Spatie).

☐ Fase 3 completata

---

## Fase 3.5 — Configurazione email transazionale (Resend)

> **Contesto** (da `mandich-dev-infra`): Resend scelto come provider per tutti i siti `*.mandich.dev` al posto di un setup self-hosted, perché il piano gratuito Oracle Cloud blocca la porta 25 in uscita (sblocco riservato ai piani a pagamento). **Obiettivo di questa fase**: un solo dominio verificato su Resend (`mandich.dev`, non un sottodominio per sito) condiviso da tutti i siti, per restare nel piano gratuito Resend senza dover creare un account/dominio separato per ognuno. Questa fase precede l'uso delle mail già previsto in Fase 4 (Step 4.5): senza un mailer configurato, `NewCardsEmail`/`AdminScanReportEmail` finirebbero solo nei log (`MAIL_MAILER=log` attuale).

**Step 3.5.1 — Verifica dominio `mandich.dev` su Resend (una tantum, condivisa tra tutti i siti)**
- Su [resend.com/domains](https://resend.com/domains) aggiungi il dominio **`mandich.dev`** (l'apice, non `unlimiteddb.mandich.dev`) — se è già stato verificato per un altro sito della VM, salta questo step: la verifica vale per l'intero dominio, ogni sito potrà inviare da qualsiasi indirizzo `@mandich.dev` senza registrarsi di nuovo.
- Resend genera i record DNS da aggiungere (tipicamente: 1 TXT per SPF, 2-3 CNAME/TXT per DKIM, opzionale TXT per DMARC) — vanno creati su Cloudflare, dove è già gestito il DNS di `mandich.dev` (stesso posto del record wildcard usato da Traefik).
- Attendi la verifica (di norma minuti, fino a 72h): la dashboard segna il dominio come "Verified" prima di poter inviare.

**Step 3.5.2 — API Key Resend**
- Se non esiste già una key riutilizzabile per i siti `*.mandich.dev`, creane una in [resend.com/api-keys](https://resend.com/api-keys) con permesso **"Sending access"** (non serve full access), eventualmente ristretta al dominio `mandich.dev`.
- Salvala solo nel gestore password / negli `.env` dei singoli ambienti — non versionarla mai (né in `.env-overrides`, che è tracciato in Git).

**Step 3.5.3 — Pacchetto Resend per Laravel**
```
composer require resend/resend-php
```
`config/mail.php` (mailer `resend` con `'transport' => 'resend'`) e `config/services.php` (`'resend' => ['key' => env('RESEND_API_KEY')]`) sono già presenti nello scaffold Laravel 12 di questo progetto — nessuna modifica di codice necessaria oltre all'installazione del pacchetto.

**Step 3.5.4 — Variabili d'ambiente (locale e produzione)**
In locale (`.env`) e sul server (`.env` del sito su `~/sites/SWUDB/.env`, dato che `new-site.sh` legge/crea il `.env` direttamente sulla VM e non lo committa):
```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxx
MAIL_FROM_ADDRESS="unlimiteddb@mandich.dev"
MAIL_FROM_NAME="UnlimitedDB"
```
Scegli un indirizzo `MAIL_FROM_ADDRESS` specifico per il sito, così chi riceve la mail capisce subito il mittente (es. `unlimiteddb@mandich.dev`, non un indirizzo generico condiviso tipo `noreply@mandich.dev`) — con il dominio verificato basta questo, non serve ulteriore configurazione Resend per usare indirizzi diversi da sito a sito. Le variabili `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD`/`MAIL_SCHEME` restano in `.env.example` come riferimento per lo sviluppo locale (mailpit/log), ma non servono più con `resend`. Dopo aver aggiornato il `.env` sul server, riavvia il container app (`SWUDB_app`) perché rilegga le variabili.

**Step 3.5.5 — Verifica invio**
```
php artisan tinker
>>> Mail::raw('Test invio da UnlimitedDB', fn ($m) => $m->to('tuamail@esempio.com')->subject('Test Resend'));
```
Controlla l'esito sia nella dashboard Resend ([resend.com/emails](https://resend.com/emails), log di invio con stato delivered/bounced) sia nella casella di posta di destinazione.

☐ Fase 3.5 completata

---

## Fase 4 — Catalogo carte e import via queue

### ✅ Fatto
Modelli `Expansion`/`Card`; migration `expansions`/`cards`; `ImportCardsFromSwuApiJob` (scheletro); comando `cards:scan`; `Schedule::command(...)` in `routes/console.php`; worker testato in locale.

### 🔧 Da fare

**Step 4.1 — Bug bloccante**
Manca `use Illuminate\Support\Facades\Schedule;` in `routes/console.php`.

**Step 4.2 — Applica lo schema `cards`/`expansions`** ✅ quasi tutto fatto, manca solo un pezzo
Controllate le migration reali: `expansions` e `cards` sono già in snake_case, `release_date`/`legal_date` sono già due colonne distinte, `group_main_expansion` c'è già (con self-FK). **Manca però la foreign key `cards.expansion → expansions.expansion`**: nella migration `create_cards_table.php` la colonna `expansion` è dichiarata come semplice `$table->string('expansion', 10);`, senza vincolo di integrità referenziale verso `expansions`. Aggiungi, subito prima della `$table->primary(['expansion', 'number']);`:
```php
$table->foreign('expansion')->references('expansion')->on('expansions')->cascadeOnDelete();
```
Senza questo vincolo, un `Card::create()` con un codice espansione inesistente (typo, espansione non ancora importata) verrebbe accettato silenziosamente dal database invece di fallire subito — esattamente il tipo di errore che poi si scopre tardi, in produzione, invece che al momento dell'import.

**Step 4.3 — Aspetti e tratti** ✅ Fatto
`Aspect`/`CardTrait` e le pivot `card_aspect`/`card_trait` esistono già con PK/FK corrette (vedi schema sopra); colonna `cards.traits` già rimossa.

**Step 4.4 — Relazioni nei modelli** ✅ Fatto
`Card::aspects()`, `Card::traits()`, `Card::expansionModel()`, `Expansion::cards()`, `Expansion::mainExpansion()`/`subExpansions()` già scritte e con le chiavi giuste.

**Step 4.5 — `ImportCardsFromSwuApiJob`**
Endpoint ufficiali (da `documentation.md`/`todo.md` della vecchia versione):
```
GET https://admin.starwarsunlimited.com/api/card/{cid}?locale=it
GET https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][$null]=true&fields[0]=cardUid&fields[1]=cardNumber&fields[2]=title&fields[3]=subtitle&fields[4]=unique&fields[5]=cost&fields[6]=hp&fields[7]=power&fields[8]=text&fields[9]=artist&pagination[page]={page}&pagination[pageSize]=10
```
Rispetto alla prima bozza, la query usa `fields[]` per elencare esplicitamente solo gli scalari che servono (`cardUid`, `cardNumber`, `title`, `subtitle`, `unique`, `cost`, `hp`, `power`, `text`, `artist`) invece di farseli restituire tutti — più efficiente, meno banda per pagina. **Nota importante**: `fields[]` filtra solo i campi scalari "piatti"; le relazioni (`type`, `rarity`, `expansion`, `arenas`, `traits`, `aspects`, `artFront`/`artBack`/`artThumbnail`, `localizations`, `variantTypes`, `variantOf`, `reprintOf`) vengono comunque restituite per intero — non esiste un `populate` da limitare separatamente, quindi il payload reale è comunque corposo (vedi mapping sotto).

Requisiti raccolti da `todo.md` (vecchia versione, da riportare):
- **un solo messaggio Telegram per scan**, aggiornato nel tempo con `TelegramService::editMessage()` (Fase 9) invece di spammare un messaggio per evento
- **verifica che la carta non sia già presente** prima di considerarla "nuova" (per l'email agli utenti)
- a fine scan: **email a tutti gli utenti** con le carte aggiunte (`NewCardsEmail`, coda); **email agli admin** con le carte che hanno lanciato errori o erano già presenti (`AdminScanReportEmail`)
- ogni riga fallita → `SystemError::create([...])` invece di interrompere l'intero scan (fail-soft)

**Prerequisito** ✅ fatto — risposta reale verificata (`pagination[page]=0&pagination[pageSize]=1`, una carta di test: Luke Skywalker leader, SOR #005). Nota: con `page=0` nella richiesta, `meta.pagination.page` torna comunque `1` — l'API tratta `0` come "prima pagina", quindi lo `$page` del job può continuare a partire da `1` come nello scheletro sotto, nessuna modifica necessaria lì.

**Mapping campo API → colonna `cards` (confermato sui dati reali, non più placeholder)**:

| Campo risposta API | Percorso | Colonna `cards` | Note |
|---|---|---|---|
| `cardUid` | scalare, root | `cid` | Id naturale univoco — **non** `cid`, il nome vero è `cardUid` (`validationId` in fondo al payload ha lo stesso valore, ridondante, ignoralo). |
| `cardNumber` | scalare, root | `number` | |
| `title` | scalare, root | `name` | Controintuitivo: il "titolo" dell'API è il **nome** della carta (es. "Luke Skywalker"). |
| `subtitle` | scalare, root | `title` | E il "sottotitolo" dell'API è la colonna `title` del DB (es. "Amico Fidato"). |
| `unique` | scalare, root | `unique_card` | |
| `cost` | scalare, root | `cost` | |
| `hp` | scalare, root | `health` | |
| `power` | scalare, root | `power` | |
| `text` | scalare, root | `text` | Testo semplice; esiste anche `textStyled` dentro `localizations[]` (HTML con `<img>` per le icone) ma non serve per la colonna testo semplice. |
| `artist` | scalare, root | `artist` | |
| `type.data.attributes.value` | relazione | `type` | Usa `value` (es. `"Leader"`), non `name`: `value` è già nel formato inglese che combacia con l'enum SQL nativo; `name`/`name` localizzato (`"Leader"` in IT coincide qui ma non è garantito per altri tipi). |
| `rarity.data.attributes.englishName` | relazione | `rarity` | Usa `englishName` (es. `"Special"`), non `name` (che è localizzato in italiano, es. `"Speciale"`) — combacia direttamente con l'enum `Common`/`Uncommon`/`Rare`/`Legendary`/`Special`, niente da tradurre a mano. |
| `expansion.data.attributes.code` | relazione | `expansion` (FK) | Es. `"SOR"` — è il codice naturale, non il `name` esteso (`"Scintilla di Ribellione"`). |
| `arenas.data[0].attributes.name` | relazione (array) | `arena` | Prendi il primo elemento se presente (nei dati osservati è sempre 0 o 1 elemento); nome localizzato IT (es. `"Terrestre"`) va bene così, la colonna è solo per display. |
| `traits.data[].attributes.name` | relazione (array) | pivot `card_trait` | Itera e fai upsert su `traits` + pivot, come già previsto. |
| `aspects.data[].attributes.name`/`color` | relazione (array) | pivot `card_aspect` | Itera e fai upsert su `aspects` (con `color`) + pivot. |
| `artFront.data.attributes.url` (fallback `.formats.card.url` se `url` root assente) | relazione | `front_art_path` (via download, Step 4.6) | **Decisione aggiornata** (la bozza iniziale diceva il contrario): la differenza di peso tra l'originale in `url` root e il formato `card` è risultata trascurabile nei test, quindi si preferisce la qualità maggiore dell'originale, con `formats.card` tenuto solo come fallback se `url` manca. |
| `artBack.data.attributes.url` (fallback `.formats.card.url`) | relazione | `back_art_path` | Stesso criterio di `artFront`. |

**Campi che lo schema `cards` prevede ma che questa risposta non contiene affatto**:
- `max_copies`: nessun campo `maxCopies`/simile nel payload — resta `null` di default per import automatico (valorizzabile solo a mano, come già previsto dalla colonna nullable, per le carte con limite non standard). **Eccezione nota e voluta**: la carta JTL #256 ha un limite fisso di 15 copie che non tornerà mai al valore standard, per questo è scritta come caso hardcoded direttamente nel job invece che gestita a mano via UI admin — è l'unica eccezione di questo tipo prevista al momento; se in futuro ne emergono altre valuta una tabella/config dedicata invece di continuare ad aggiungere `if` nel job.
- `release_date`: nessun campo `releaseDate`/simile nel payload, solo `createdAt`/`updatedAt`/`publishedAt` (metadati del CMS). **Decisione presa**: per le *carte* va bene usare `publishedAt` come `release_date` — a differenza di `expansions.legal_date` (vedi nota sotto sulla creazione automatica delle espansioni), qui rappresenta ragionevolmente quando la carta è stata resa pubblica, che è esattamente ciò che serve alla pagina "Nuove uscite" (Step 10.4).

Lo scheletro sotto è già aggiornato con questi nomi di campo reali.

```php
namespace App\Jobs;

use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\Card;
use App\Models\SystemError;
use App\Models\User;
use App\Services\CardImageDownloader;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    public function handle(TelegramService $telegram, CardImageDownloader $imageDownloader): void
    {
        $adminChatId = config('services.telegram.admin_chat_id');
        $progress = $telegram->sendMessage($adminChatId, 'Scan avviato...');

        $newCards = collect();
        $errors = collect();
        $page = 1;
        $lastPage = 1;

        do {
            $response = Http::get('https://admin.starwarsunlimited.com/api/card-list', [
                'locale' => 'it',
                'filters[variantOf][id][$null]' => 'true',
                'fields' => ['cardUid', 'cardNumber', 'title', 'subtitle', 'unique', 'cost', 'hp', 'power', 'text', 'artist'],
                'pagination[page]' => $page,
                'pagination[pageSize]' => 10,
            ]);

            if ($response->failed()) {
                SystemError::create([
                    'source' => self::class,
                    'message' => "Pagina {$page}: richiesta API fallita ({$response->status()})",
                    'context' => ['page' => $page, 'body' => $response->body()],
                ]);
                break; // l'intera pagina non e' recuperabile, non ha senso continuare a paginare
            }

            $payload = $response->json();
            $lastPage = $payload['meta']['pagination']['pageCount'] ?? $page;

            foreach ($payload['data'] ?? [] as $cardEntry) {
                $cardData = $cardEntry['attributes'] ?? [];
                $cid = $cardData['cardUid'] ?? null;

                try {
                    if (! $cid) {
                        throw new \RuntimeException('cardUid mancante nel payload');
                    }

                    $existed = Card::where('cid', $cid)->exists();

                    $card = Card::updateOrCreate(
                        ['cid' => $cid],
                        [
                            'expansion' => $cardData['expansion']['data']['attributes']['code'] ?? null,
                            'number' => $cardData['cardNumber'],
                            'unique_card' => $cardData['unique'] ?? false,
                            'name' => $cardData['title'],
                            'title' => $cardData['subtitle'] ?? null,
                            'type' => $cardData['type']['data']['attributes']['value'] ?? null,
                            'rarity' => $cardData['rarity']['data']['attributes']['englishName'] ?? null,
                            'cost' => $cardData['cost'] ?? null,
                            'health' => $cardData['hp'] ?? null,
                            'power' => $cardData['power'] ?? null,
                            'text' => $cardData['text'] ?? '',
                            'arena' => $cardData['arenas']['data'][0]['attributes']['name'] ?? null,
                            'artist' => $cardData['artist'] ?? null,
                            // max_copies e release_date non presenti in questa risposta: restano null,
                            // valorizzabili solo a mano finche' non si verifica l'endpoint /api/card/{cid}.
                        ]
                    );

                    if (! $existed) {
                        $newCards->push($card);
                    } else {
                        $errors->push("Carta {$cid} gia' presente, dati aggiornati");
                    }

                    // Aspetti e tratti: upsert + sync sulla pivot, non solo creazione
                    $aspectNames = collect($cardData['aspects']['data'] ?? [])->pluck('attributes.name');
                    $aspectIds = $aspectNames->map(fn ($name) => \App\Models\Aspect::firstOrCreate(['name' => $name])->id);
                    $card->aspects()->sync($aspectIds);

                    $traitNames = collect($cardData['traits']['data'] ?? [])->pluck('attributes.name');
                    $traitNames->each(fn ($name) => \App\Models\CardTrait::firstOrCreate(['name' => $name]));
                    $card->traits()->sync($traitNames);

                    $frontUrl = $cardData['artFront']['data']['attributes']['formats']['card']['url']
                        ?? $cardData['artFront']['data']['attributes']['url']
                        ?? null;
                    if ($frontUrl && ! $card->front_art_path) {
                        $path = $imageDownloader->download($frontUrl, $card->expansion, $card->number, 'front');
                        $path ? $card->update(['front_art_path' => $path]) : SystemError::create([
                            'source' => CardImageDownloader::class,
                            'message' => "Download immagine fronte fallito per {$cid}",
                        ]);
                    }
                    // stesso pattern per back_art_path, leggendo artBack.data.attributes.formats.card.url
                } catch (\Throwable $e) {
                    SystemError::create([
                        'source' => self::class,
                        'message' => "Errore su carta {$cid} " . ($cid ? '' : '(cid mancante)') . ": {$e->getMessage()}",
                        'stack_trace' => $e->getTraceAsString(),
                        'context' => ['raw' => $cardData],
                    ]);
                    $errors->push($e->getMessage());
                    continue; // fail-soft: una carta rotta non ferma lo scan
                }
            }

            $telegram->editMessage($adminChatId, $progress->messageId, "Scan in corso: pagina {$page}/{$lastPage}...");
            $page++;
        } while ($page <= $lastPage);

        if ($newCards->isNotEmpty()) {
            Mail::to(User::all())->queue(new NewCardsEmail($newCards));
        }
        if ($errors->isNotEmpty()) {
            $admins = User::role('admin')->get();
            Mail::to($admins)->queue(new AdminScanReportEmail($errors));
        }

        $telegram->editMessage(
            $adminChatId,
            $progress->messageId,
            "Scan completato: {$newCards->count()} nuove carte, {$errors->count()} problemi."
        );
    }
}
```
Note sul codice sopra:
- `TelegramActionResult::$messageId` (Fase 9, Step 9.2) e' quello che permette di modificare lo stesso messaggio invece di mandarne uno nuovo ad ogni pagina.
- Il `break` sulla richiesta fallita (non il singolo `continue` per carta) e' intenzionale: se l'intera pagina non risponde, insistere sulle pagine successive non ha senso.
- `Mail::to($admins)` usa `User::role('admin')` (metodo di Spatie `HasRoles`), non `permission:` diretto, perche' l'email va a chi ha il ruolo `admin`, non a chiunque abbia un permesso specifico.
- Mailable `NewCardsEmail`/`AdminScanReportEmail` vanno create insieme a questo step (istruzioni dettagliate nello Step 4.5bis subito sotto), non prima: senza carte da mostrare non hanno contenuto da progettare.
- Ogni riga di `data[]` è avvolta in `{id, attributes: {...}}` (formato Strapi classico) — per questo il ciclo `foreach` estrae prima `$cardEntry['attributes']`, non lavora direttamente su `$cardEntry`. Le relazioni dentro `attributes` seguono lo stesso pattern annidato un livello più giù (`attributes.expansion.data.attributes.code`), da qui i percorsi lunghi nel mapping sopra.
- `$card->aspects()->sync($aspectIds)`/`$card->traits()->sync($traitNames)` sostituiscono un eventuale riferimento a colonne dirette: aggiornano la pivot ad ogni scan, così se una carta cambia aspetto/tratto tra un errata e l'altro il dato resta coerente (non solo alla prima creazione).
- **Creazione automatica dell'`Expansion` se non esiste ancora** (necessaria perché altrimenti la FK `cards.expansion → expansions.expansion` farebbe fallire l'insert): `Expansion::firstOrCreate(['expansion' => $code], ['legal_date' => $expansionData['publishedAt'] ?? null, 'rotation' => Expansion::max('rotation')])`. `legal_date` da `publishedAt` e `rotation` copiato dal massimo esistente sono **placeholder deliberatamente approssimativi**, non i dati reali (`publishedAt` è quando l'espansione è stata pubblicata, non quando diventa legale in torneo) — restano corretti a mano in Fase 6, per questo `expansions.confirmed` resta `false` di default finché un admin non li verifica.
Riferimento: https://laravel.com/docs/12.x/queues#creating-jobs, https://laravel.com/docs/12.x/mail

**Step 4.5bis — Creazione delle Mailable `NewCardsEmail` e `AdminScanReportEmail`**
Vanno create prima di poter eseguire il job di Step 4.5 così com'è: sono già referenziate (`use App\Mail\...`) ma la cartella `app/Mail/` non esiste ancora nel progetto.

Come funzionano le Mailable in Laravel 12 (sintassi "nuova", quella corretta da usare qui):
- Una Mailable è una classe che rappresenta una mail: che dati contiene e come viene renderizzata. Non la invii costruendola e basta: la passi a `Mail::to($destinatari)->queue(new TuaMailable($dati))` (già scritto così nel job di Step 4.5).
- Tre metodi da implementare (scheletro già generato dal comando artisan sotto, li trovi vuoti/con placeholder da riempire):
  - `envelope(): Envelope` — oggetto della mail (`return new Envelope(subject: '...')`). Il mittente non va specificato qui: usa già `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` da `.env` (Fase 3.5).
  - `content(): Content` — quale vista Markdown renderizzare e con quali variabili (`return new Content(markdown: 'emails.new-cards', with: ['cards' => $this->cards])`).
  - `attachments(): array` — lasciala vuota (`return [];`), non servono allegati.
- Per essere accodabile (`->queue()`, non `->send()`), la classe deve `implement ShouldQueue` e usare i trait `Queueable` + `SerializesModels` (quest'ultimo serve perché passi una `Collection` di modelli Eloquent nel costruttore, non solo scalari).
- I dati passati al costruttore vanno dichiarati proprietà pubbliche (es. `public function __construct(public readonly Collection $cards) {}`) — sono quelle che poi passi a `content(with: [...])` per renderle disponibili nella vista Blade come `$cards`.

Comandi da eseguire (uno per ciascuna mail, generano sia la classe sia il template Markdown insieme):
```
php artisan make:mail NewCardsEmail --markdown=emails.new-cards
php artisan make:mail AdminScanReportEmail --markdown=emails.admin-scan-report
```
Questo crea:
- `app/Mail/NewCardsEmail.php` / `app/Mail/AdminScanReportEmail.php` — le classi, già con lo scheletro `envelope()`/`content()`/`attachments()` da riempire secondo i punti sopra.
- `resources/views/emails/new-cards.blade.php` / `resources/views/emails/admin-scan-report.blade.php` — i template, già con i tag base `<x-mail::message>` (le Markdown Mailable di Laravel usano componenti Blade dedicati: `<x-mail::button>`, `<x-mail::table>`, ecc. — elenco completo: https://laravel.com/docs/12.x/mail#writing-markdown-messages).

Contenuto atteso di ciascun template (in base ai requisiti già raccolti per lo scan, vedi sopra in Step 4.5):
- **`new-cards.blade.php`**: riceve `$cards` (la Collection di `Card` appena create, passata dal job). Per ognuna mostra almeno nome, espansione+numero, e se disponibile l'immagine fronte (`asset('storage/'.$card->front_art_path)`) — un elenco puntato o una `<x-mail::table>` vanno benissimo, non serve altro.
- **`admin-scan-report.blade.php`**: riceve `$errors` (la Collection di stringhe passata dal job, un mix di veri errori e "carta già presente"). Un elenco puntato delle stringhe basta così com'è; se in futuro vuoi linkare ai `SystemError` corrispondenti (la rotta `errors.show` di Step 5.3 esiste già per questo), il job dovrebbe passare una Collection di modelli `SystemError` invece di semplici stringhe — non necessario ora, valutalo solo se ti serve davvero.

Per personalizzare i colori del layout email di default, `php artisan vendor:publish --tag=laravel-mail` pubblica il CSS in `resources/views/vendor/mail/` — opzionale, salta questo passaggio se lo stile di default va bene.

Per vedere il rendering senza inviare davvero: in locale lascia `MAIL_MAILER=log` (prima di passare a `resend` in produzione come da Fase 3.5) e leggi l'HTML già renderizzato dentro `storage/logs/laravel.log` dopo aver fatto partire lo scan; nei test Pest (Step 4.7) `Mail::fake()` invece verifica solo che la mail sia stata accodata (`assertQueued`), senza renderizzarla.

**Step 4.6 — Download locale delle immagini carta**
Invece di salvare l'URL dell'API in `front_art_path`/`back_art_path`, scarica l'immagine e salva il path locale:

1. `php artisan storage:link` (una tantum) — crea il symlink `public/storage` verso `storage/app/public`, necessario per rendere le immagini raggiungibili via browser.
2. Crea `app/Services/CardImageDownloader.php`:
```php
class CardImageDownloader
{
    /**
     * Downloads a card image from the given URL and stores it on the public disk
     * Scarica l'immagine di una carta dall'URL indicato e la salva sul disk pubblico
     *
     * @param string $sourceUrl URL originale dell'immagine (dall'API SWU)
     * @param string $expansion Codice espansione, per il path di destinazione
     * @param int $number Numero carta, per il path di destinazione
     * @param string $side 'front' o 'back', per differenziare il nome file
     * @return string|null Path relativo salvato (es. "cards/SOR/001-front.webp"), null se il download fallisce
     */
    public function download(string $sourceUrl, string $expansion, int $number, string $side): ?string
    {
        // 1. Http::get($sourceUrl) -> se fallisce, ritorna null (il chiamante logga il SystemError)
        // 2. determina l'estensione dal Content-Type della risposta (es. image/webp -> .webp), non dall'URL
        // 3. $path = "cards/{$expansion}/{$number}-{$side}.{$ext}"
        // 4. Storage::disk('public')->put($path, $response->body())
        // 5. return $path
    }
}
```
3. Nel job di import (Step 4.5), per ogni carta: se non esiste già un file a quel path (evita ri-download inutili ad ogni scan settimanale, le immagini di una carta pubblicata non cambiano), chiama `CardImageDownloader::download()` per front e back; se il download fallisce, registra un `SystemError` (`source: CardImageDownloader`) e lascia il campo `null`/il valore precedente invece di far fallire l'intera riga.
4. Per mostrare l'immagine in una view: `Storage::disk('public')->url($card->front_art_path)` (o l'helper `asset('storage/'.$card->front_art_path)`).
5. Aggiungi a `.gitignore`: `/storage/app/public/cards` — sono file scaricabili di nuovo da un nuovo scan, non ha senso versionarli (e sarebbero comunque tanti file binari).

Riferimento: https://laravel.com/docs/12.x/filesystem

**Step 4.7 — Copertura Pest**
File `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`:
```php
use App\Jobs\ImportCardsFromSwuApiJob;
use App\Mail\NewCardsEmail;
use App\Mail\AdminScanReportEmail;
use App\Models\Card;
use App\Models\SystemError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

// Fixture minima ma fedele alla struttura reale confermata su test.json (Strapi: data[].attributes,
// relazioni annidate come attributes.expansion.data.attributes.code). Un helper tipo cardFixture(['cardUid' => ...])
// che parta da questo scheletro e sovrascriva solo i campi che cambiano evita di ripeterlo in ogni test.
function fakeCardEntry(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 7,
        'attributes' => [
            'cardUid' => '2579145458',
            'cardNumber' => 5,
            'title' => 'Luke Skywalker',
            'subtitle' => 'Amico Fidato',
            'unique' => true,
            'cost' => 6,
            'hp' => 7,
            'power' => 4,
            'text' => 'Testo di prova',
            'artist' => 'Borja Pindado',
            'type' => ['data' => ['attributes' => ['name' => 'Leader', 'value' => 'Leader']]],
            'rarity' => ['data' => ['attributes' => ['name' => 'Speciale', 'englishName' => 'Special']]],
            'expansion' => ['data' => ['attributes' => ['code' => 'SOR', 'name' => 'Scintilla di Ribellione']]],
            'arenas' => ['data' => [['attributes' => ['name' => 'Terrestre']]]],
            'traits' => ['data' => [['attributes' => ['name' => 'Forza']], ['attributes' => ['name' => 'Ribelle']]]],
            'aspects' => ['data' => [['attributes' => ['name' => 'Vigilanza', 'color' => '#4073d4']]]],
            'artFront' => ['data' => ['attributes' => ['url' => 'https://cdn.example/front.png', 'formats' => ['card' => ['url' => 'https://cdn.example/front-card.png']]]]],
            'artBack' => ['data' => ['attributes' => ['url' => 'https://cdn.example/back.png', 'formats' => ['card' => ['url' => 'https://cdn.example/back-card.png']]]]],
        ],
    ], $overrides);
}

it('crea le carte nuove ricevute dall\'API', function () {
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [
                fakeCardEntry(),
                fakeCardEntry(['attributes' => ['cardUid' => '9999999999', 'cardNumber' => 6, 'title' => 'Leia Organa']]),
            ],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(\App\Services\TelegramService::class), app(\App\Services\CardImageDownloader::class));

    expect(Card::count())->toBe(2);
    Mail::assertQueued(NewCardsEmail::class);
});

it('pagina correttamente su piu\' pagine', function () {
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::sequence()
            ->push(['data' => [fakeCardEntry()], 'meta' => ['pagination' => ['pageCount' => 2]]])
            ->push(['data' => [fakeCardEntry(['attributes' => ['cardUid' => '1111111111', 'cardNumber' => 12]])], 'meta' => ['pagination' => ['pageCount' => 2]]]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(\App\Services\TelegramService::class), app(\App\Services\CardImageDownloader::class));

    Http::assertSentCount(2);
    expect(Card::count())->toBe(2);
});

it('registra un SystemError su dati malformati invece di fermare lo scan', function () {
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [['id' => 1, 'attributes' => ['cardUid' => null /* campo obbligatorio mancante, forza l\'eccezione */]]],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(\App\Services\TelegramService::class), app(\App\Services\CardImageDownloader::class));

    expect(SystemError::count())->toBeGreaterThan(0);
});

it('invia la mail agli admin quando ci sono errori o carte gia\' presenti', function () {
    Card::factory()->create(['cid' => '2579145458']); // gia' presente, l'API la rispedisce
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob())->handle(app(\App\Services\TelegramService::class), app(\App\Services\CardImageDownloader::class));

    Mail::assertQueued(AdminScanReportEmail::class);
});
```
`Http::fake()` intercetta le chiamate a `admin.starwarsunlimited.com` senza uscire in rete davvero; `Mail::fake()` verifica solo che la mail sia stata accodata (`assertQueued`), senza inviarla. Questa e' la verifica di correttezza della logica di import, contro dati controllati — non un controllo post-hoc sulla produzione.

☐ Fase 4 completata

---

## Fase 5 — Log errori scan (`system_errors`)

**Step 5.1 — Migration e modello** ✅ Fatto
`system_errors` migration e model `SystemError` gia' presenti e corretti (`$fillable`/`$casts` inclusi).

**Step 5.2 — Permesso**
In `database/seeders/PermissionSeeder.php`, aggiungi `'system.manage-errors'` all'array `$permissions` (viene automaticamente dato al ruolo `admin` dalla riga `$admin->givePermissionTo($permissions);` gia' presente). Poi ri-esegui il seeder: `php artisan db:seed --class=PermissionSeeder`.

**Step 5.3 — Pagina admin `/admin/errori`**
Stesso pattern architetturale di Step 3.2 (controller + route group `permission:` + viste Blade), applicato a `SystemError`.

1. **Controller**
   ```
   php artisan make:controller Admin/SystemErrorController
   ```
   ```php
   namespace App\Http\Controllers\Admin;

   use App\Http\Controllers\Controller;
   use App\Models\SystemError;
   use Illuminate\Http\RedirectResponse;
   use Illuminate\Http\Request;
   use Illuminate\View\View;

   class SystemErrorController extends Controller
   {
       public function index(Request $request): View
       {
           $status = $request->query('status'); // 'open' | 'resolved' | 'ignored' | null (tutti)

           $errors = SystemError::when($status, fn ($q) => $q->where('status', $status))
               ->latest()
               ->paginate(30)
               ->withQueryString(); // mantiene il filtro ?status= nei link di paginazione

           return view('admin.errors.index', compact('errors', 'status'));
       }

       public function show(SystemError $systemError): View
       {
           return view('admin.errors.show', compact('systemError'));
       }

       /**
        * Updates the status of a single error (resolved/ignored) from a row-level button
        * Aggiorna lo stato di un singolo errore (risolto/ignorato) da un pulsante riga-per-riga
        */
       public function update(Request $request, SystemError $systemError): RedirectResponse
       {
           $validated = $request->validate(['status' => ['required', 'in:open,resolved,ignored']]);

           $systemError->update([
               'status' => $validated['status'],
               'resolved_at' => $validated['status'] === 'open' ? null : now(),
           ]);

           return back()->with('status', 'Errore aggiornato.');
       }

       /**
        * Bulk action: applies the same status to every selected error id at once
        * Azione bulk: applica lo stesso stato a tutti gli id di errore selezionati insieme
        */
       public function bulkUpdate(Request $request): RedirectResponse
       {
           $validated = $request->validate([
               'ids' => ['required', 'array'],
               'ids.*' => ['integer', 'exists:system_errors,id'],
               'status' => ['required', 'in:open,resolved,ignored'],
           ]);

           SystemError::whereIn('id', $validated['ids'])->update([
               'status' => $validated['status'],
               'resolved_at' => $validated['status'] === 'open' ? null : now(),
           ]);

           return back()->with('status', count($validated['ids']).' errori aggiornati.');
       }
   }
   ```

2. **Rotte** — stesso blocco `Route::middleware(['auth', 'verified', 'permission:system.manage-errors'])->prefix('admin')->name('admin.')->group(...)` di Step 3.2 (puoi estendere lo stesso gruppo se preferisci, cambiando il permesso richiesto a livello di singola rotta con `->middleware('permission:system.manage-errors')` sulla singola route invece che sul gruppo intero, visto che qui il permesso e' diverso da `users.manage`):
   ```php
   use App\Http\Controllers\Admin\SystemErrorController;

   Route::middleware(['auth', 'verified', 'permission:system.manage-errors'])
       ->prefix('admin')
       ->name('admin.')
       ->group(function () {
           Route::get('/errori', [SystemErrorController::class, 'index'])->name('errors.index');
           Route::get('/errori/{systemError}', [SystemErrorController::class, 'show'])->name('errors.show');
           Route::patch('/errori/{systemError}', [SystemErrorController::class, 'update'])->name('errors.update');
           Route::patch('/errori/bulk', [SystemErrorController::class, 'bulkUpdate'])->name('errors.bulk-update');
       });
   ```
   La rotta `errors.show` e' quella a cui punta il link nella mail `AdminScanReportEmail` (Step 4.5): `route('admin.errors.show', $systemError)`.

3. **Vista lista** — `resources/views/admin/errors/index.blade.php`: filtro per stato via link GET (`?status=open` ecc.), checkbox riga-per-riga dentro un unico `<form>` che invia a `errors.bulk-update`, pulsanti singoli "Risolto"/"Ignora" che inviano un piccolo form PATCH per riga verso `errors.update`. Ogni riga mostra `source`, `message`, `status` (badge colorato), link a `errors.show`.

4. **Vista dettaglio** — `resources/views/admin/errors/show.blade.php`: mostra `message`, `stack_trace` in un `<pre>`, e `context` formattato con `<pre>{{ json_encode($systemError->context, JSON_PRETTY_PRINT) }}</pre>` (il cast `context => array` gia' presente sul model lo restituisce come array PHP, va ri-serializzato per la vista).

☐ Fase 5 completata

---

## Fase 6 — Admin: gestione espansioni e rotazioni

> Dalla vecchia versione (`todo.md`): "creare pagina admin di gestione espansioni e rotazioni". Necessaria perché `rotation`/`legal_date`/`confirmed`/`group_main_expansion` sono dati a cura manuale dell'admin (schema `expansions`).

**Step 6.1 — Permesso**
Stessa procedura di Step 5.2: aggiungi `'expansions.manage'` all'array `$permissions` in `PermissionSeeder`, poi `php artisan db:seed --class=PermissionSeeder`.

**Step 6.2 — Pagina admin `/admin/espansioni`**
A differenza di Step 3.2/5.3 (liste con edit su pagina separata), qui ha senso un **form inline per riga** dato che sono poche decine di espansioni e i campi da editare sono solo 3+1 checkbox.

1. **Controller**
   ```
   php artisan make:controller Admin/ExpansionController
   ```
   ```php
   namespace App\Http\Controllers\Admin;

   use App\Http\Controllers\Controller;
   use App\Models\Expansion;
   use Illuminate\Http\RedirectResponse;
   use Illuminate\Http\Request;
   use Illuminate\View\View;

   class ExpansionController extends Controller
   {
       public function index(): View
       {
           // include anche le espansioni token (T*, vedi nota schema) — nessun filtro, e' voluto
           $expansions = Expansion::orderBy('expansion')->get();

           return view('admin.expansions.index', compact('expansions'));
       }

       public function update(Request $request, Expansion $expansion): RedirectResponse
       {
           $validated = $request->validate([
               'legal_date' => ['nullable', 'date'],
               'rotation' => ['required', 'string', 'max:1'],
               'group_main_expansion' => ['nullable', 'string', 'exists:expansions,expansion'],
               'confirmed' => ['boolean'],
           ]);
           $validated['confirmed'] = $request->boolean('confirmed'); // checkbox non spuntata non arriva nel payload

           $expansion->update($validated);

           return back()->with('status', "Espansione {$expansion->expansion} aggiornata.");
       }
   }
   ```
   `exists:expansions,expansion` valida che `group_main_expansion` punti a un'espansione realmente esistente, coerente col vincolo di FK gia' presente in migration — cosi' un errore di digitazione nel form viene bloccato dalla validazione con un messaggio chiaro, invece di far fallire la query con un errore SQL generico.

2. **Rotte**
   ```php
   use App\Http\Controllers\Admin\ExpansionController;

   Route::middleware(['auth', 'verified', 'permission:expansions.manage'])
       ->prefix('admin')
       ->name('admin.')
       ->group(function () {
           Route::get('/espansioni', [ExpansionController::class, 'index'])->name('expansions.index');
           Route::put('/espansioni/{expansion}', [ExpansionController::class, 'update'])->name('expansions.update');
       });
   ```
   Nota: `{expansion}` nella rotta fa route-model-binding sulla colonna `expansion` (la PK del modello) automaticamente, perche' `Expansion::$primaryKey = 'expansion'` è gia' impostato nel model — non serve `Route::bind()` o `{expansion:expansion}` espliciti.

3. **Vista** — `resources/views/admin/expansions/index.blade.php`: una tabella con una riga `<form>` per espansione (submit automatico on-change via poco Alpine.js, o un pulsante "Salva" per riga se preferisci evitare JS):
   ```blade
   <x-app-layout>
       <div class="max-w-5xl mx-auto py-6">
           <h1 class="text-xl font-semibold mb-4">Gestione espansioni</h1>
           @if (session('status'))
               <div class="mb-4 text-green-600">{{ session('status') }}</div>
           @endif
           <table class="w-full text-left border-collapse">
               <thead>
                   <tr>
                       <th>Codice</th><th>Legal date</th><th>Rotation</th><th>Gruppo</th><th>Confermata</th><th></th>
                   </tr>
               </thead>
               <tbody>
                   @foreach ($expansions as $expansion)
                       <tr>
                           <form method="POST" action="{{ route('admin.expansions.update', $expansion) }}">
                               @csrf
                               @method('PUT')
                               <td>{{ $expansion->expansion }}</td>
                               <td><input type="date" name="legal_date" value="{{ $expansion->legal_date?->format('Y-m-d') }}"></td>
                               <td><input type="text" name="rotation" value="{{ $expansion->rotation }}" maxlength="1" class="w-10"></td>
                               <td>
                                   <select name="group_main_expansion">
                                       <option value="">—</option>
                                       @foreach ($expansions as $option)
                                           <option value="{{ $option->expansion }}" @selected($expansion->group_main_expansion === $option->expansion)>{{ $option->expansion }}</option>
                                       @endforeach
                                   </select>
                               </td>
                               <td><input type="checkbox" name="confirmed" value="1" @checked($expansion->confirmed)></td>
                               <td><button type="submit">Salva</button></td>
                           </form>
                       </tr>
                   @endforeach
               </tbody>
           </table>
       </div>
   </x-app-layout>
   ```
   Nota HTML: un `<form>` non puo' avvolgere direttamente celle `<td>` in modo valido secondo lo standard, ma tutti i browser lo renderizzano comunque correttamente; se preferisci markup strettamente valido, sposta il `<form>` fuori dalla `<tr>` e collega gli input con l'attributo `form="id-univoco"` invece di annidarli.

☐ Fase 6 completata

---

## Fase 7 — Gestione mazzi multi-formato

**Step 7.2 (van fatti insieme, vedi sotto per i bug da correggere) — Modelli e migration** ⚠️ parzialmente fatto
Migration `decks`/`deck_cards` gia' presenti e corrette. Modello `DeckCard extends Pivot` gia' corretto. Il modello `Deck` esiste ma va corretto su due punti:

1. **`Deck::cards()` usa la chiave pivot sbagliata** (`card_id` invece di `cid`) e non passa da `DeckCard`. Sostituisci:
   ```php
   public function cards()
   {
       return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'cid', 'id', 'cid')
           ->using(DeckCard::class)
           ->withPivot('quantity')
           ->withTimestamps();
   }
   ```
2. **`Deck::leader()`/`Deck::base()` sono concettualmente sbagliate**: interrogano `Card` con `hasMany`/`hasOne`, ma le carte non hanno una colonna `deck_id` — il collegamento passa dalla pivot `deck_cards`, non da una FK diretta su `cards`. Sostituisci con due relazioni derivate da `cards.type` (coerente con la decisione di non avere `deck_cards.role`, vedi schema sopra):
   ```php
   public function leaders()
   {
       return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'cid', 'id', 'cid')
           ->using(DeckCard::class)
           ->withPivot('quantity')
           ->where('cards.type', 'Leader'); // Twin Suns ne ammette 2, Eternal/Premier 1 — la cardinalita' la controlla il validator (Step 7.3), non questa relazione
   }

   public function baseCard()
   {
       return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'cid', 'id', 'cid')
           ->using(DeckCard::class)
           ->withPivot('quantity')
           ->where('cards.type', 'Base');
   }
   ```
   Sono comunque relazioni `belongsToMany` vere (non semplici query), quindi restano eager-loadabili con `Deck::with('leaders', 'baseCard')->get()`. `previousVersion()`/`nextVersion()` gia' presenti e corrette, nessuna modifica.

**Step 7.1 — Enum `DeckFormat` e cast**
```php
// app/Enums/DeckFormat.php
namespace App\Enums;

enum DeckFormat: string
{
    case Premier = 'premier';
    case Eternal = 'eternal';
    case TwinSuns = 'twin_suns';
}
```
Poi, in `Deck.php`, aggiungi il cast (manca ancora):
```php
protected $casts = [
    'format' => \App\Enums\DeckFormat::class,
];
```
Da qui in poi `$deck->format` restituisce un'istanza dell'enum (`DeckFormat::Premier`), non una stringa — utile per lo `match` del validator (Step 7.3) e della factory (Step 7.4). Riferimento: https://laravel.com/docs/12.x/eloquent-mutators#enum-casting

**Step 7.3 — Validator per formato**
```php
// app/Services/DeckValidation/DeckFormatValidator.php
namespace App\Services\DeckValidation;

use App\Models\Deck;

interface DeckFormatValidator
{
    /**
     * Validates a deck against this format's rules, returning a list of human-readable errors
     * Valida un mazzo secondo le regole di questo formato, restituendo una lista di errori leggibili
     *
     * @return array<int, string> Vuoto se il mazzo e' valido
     */
    public function validate(Deck $deck): array;
}
```
Esempio completo per Premier (`app/Services/DeckValidation/PremierFormatValidator.php`), gli altri due seguono lo stesso schema cambiando solo i numeri/vincoli:
```php
namespace App\Services\DeckValidation;

use App\Models\Deck;

class PremierFormatValidator implements DeckFormatValidator
{
    public function validate(Deck $deck): array
    {
        $errors = [];

        if ($deck->leaders()->count() !== 1) {
            $errors[] = 'Il formato Premier richiede esattamente 1 leader.';
        }
        if ($deck->baseCard()->count() !== 1) {
            $errors[] = 'Il formato Premier richiede esattamente 1 base.';
        }

        foreach ($deck->cards as $card) {
            $limit = $card->max_copies ?? 3; // 3 e' il limite standard SWU, max_copies sovrascrive per le eccezioni
            if ($card->unique_card) {
                $limit = 1;
            }
            if ($card->pivot->quantity > $limit) {
                $errors[] = "Troppe copie di {$card->name} ({$card->pivot->quantity}/{$limit}).";
            }
        }

        // TODO: controllo rotazione (solo le ultime 2 expansions.rotation) quando Fase 6 e' popolata di dati reali

        return $errors;
    }
}
```
`EternalFormatValidator`: stesso controllo leader/base (1+1) ma **senza** il controllo di rotazione (Eternal ammette tutte le espansioni). `TwinSunsFormatValidator`: `leaders()->count() !== 2` invece di `!== 1`, **piu'** un controllo di allineamento tra i due leader (serve sapere quale colonna/relazione rappresenta l'allineamento della carta — non ancora nello schema `cards` attuale, da aggiungere quando importi i dati reali e vedi come l'API la espone).

**Step 7.4 — Factory**
```php
namespace App\Services\DeckValidation;

use App\Enums\DeckFormat;

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
Uso tipico nel controller (Step 7.6): `DeckFormatValidatorFactory::make($deck->format)->validate($deck)`.

**Step 7.5 — Policy**
```
php artisan make:policy DeckPolicy --model=Deck
```
```php
namespace App\Policies;

use App\Models\Deck;
use App\Models\User;

class DeckPolicy
{
    public function update(User $user, Deck $deck): bool
    {
        return $user->id === $deck->user_id || $user->can('decks.manage-any');
    }

    public function delete(User $user, Deck $deck): bool
    {
        return $this->update($user, $deck);
    }

    public function view(User $user, Deck $deck): bool
    {
        return $deck->is_public || $user->id === $deck->user_id || $user->can('decks.manage-any');
    }
}
```
Laravel registra automaticamente `DeckPolicy` per il model `Deck` (naming convention, nessuna registrazione manuale in Laravel 11+). Nel controller: `$this->authorize('update', $deck);` oppure `@can('update', $deck)` in Blade.

**Step 7.6 — Pagine mazzi**
```php
// routes/web.php
use App\Http\Controllers\DeckController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mazzi/crea', [DeckController::class, 'create'])->name('decks.create');
    Route::post('/mazzi', [DeckController::class, 'store'])->name('decks.store');
    Route::get('/mazzi/{deck}', [DeckController::class, 'edit'])->name('decks.edit');
    Route::post('/mazzi/{deck}/carte', [DeckController::class, 'addCard'])->name('decks.add-card');
    Route::delete('/mazzi/{deck}/carte/{card}', [DeckController::class, 'removeCard'])->name('decks.remove-card');
    Route::patch('/mazzi/{deck}/assembla', [DeckController::class, 'toggleAssembled'])->name('decks.toggle-assembled');
});
Route::get('/mazzi', [DeckController::class, 'index'])->name('decks.index'); // pubblica: mazzi pubblici + propri se loggato
Route::get('/mazzi/{deck}/versioni', [DeckController::class, 'versions'])->name('decks.versions');
```
Logica chiave dei metodi non banali:
```php
public function addCard(Request $request, Deck $deck): RedirectResponse
{
    $this->authorize('update', $deck);
    $validated = $request->validate([
        'cid' => ['required', 'exists:cards,cid'],
        'quantity' => ['required', 'integer', 'min:1'],
    ]);

    $deck->cards()->syncWithoutDetaching([
        $validated['cid'] => ['quantity' => $validated['quantity']],
    ]);

    $errors = DeckFormatValidatorFactory::make($deck->format)->validate($deck->fresh());

    return back()->with('deck-errors', $errors); // mostrati come warning non bloccanti nella view, la carta resta comunque aggiunta
}
```
La validazione e' **informativa** (mostra errori) non bloccante sull'inserimento: la vecchia versione permetteva di costruire un mazzo incompleto e vederne gli errori, non impediva il salvataggio riga per riga. `index()` filtra `Deck::where('is_public', true)->orWhere('user_id', auth()->id())` con eager load `with('leaders', 'baseCard')` per mostrare l'anteprima nella lista senza N+1 query.

**Step 7.7 — Export/Import mazzi**
```php
// app/Services/DeckExporter.php
class DeckExporter
{
    public function toText(Deck $deck): string
    {
        // Formato ufficiale SWU: verifica la sintassi esatta su un file esportato da swudb.com o dall'app ufficiale
        // prima di fissarla qui — indicativamente "Leader: {name}", "Base: {name}", poi "{quantity}x {name}" per il resto
    }

    public function toJson(Deck $deck): string
    {
        return json_encode([
            'name' => $deck->name,
            'format' => $deck->format->value,
            'cards' => $deck->cards->map(fn ($c) => ['cid' => $c->cid, 'quantity' => $c->pivot->quantity])->all(),
        ]);
    }
}
```
```php
// app/Services/DeckImporter.php
class DeckImporter
{
    /**
     * @return array{deck: ?Deck, notFound: array<int, string>} Il mazzo creato (null se l'input non era valido) e i riferimenti carta non trovati
     */
    public function fromJson(string $json, User $owner): array
    {
        // decode -> per ogni riga: Card::where('cid', ...)->first(); se null, aggiungi a notFound invece di interrompere
        // stesso principio fail-soft del job di import (Step 4.5): una carta non trovata non deve far fallire l'intero import
    }

    public function fromUrl(string $url, User $owner): array
    {
        // Http::get($url) con timeout esplicito; gestisci: url non raggiungibile (->failed()), redirect, content-type inatteso (verifica prima di fare json_decode)
        // questo e' il punto che nella vecchia versione aveva il bug segnalato in todo.md — i tre casi limite sopra vanno testati esplicitamente in Pest
    }
}
```
Entrambe le classi vivono in `app/Services/` (non in un controller) proprio per essere testabili in Pest senza passare da una request HTTP finta.

☐ Fase 7 completata

---

## Fase 8 — Gestione collezione

**Step 8.1 — Modello e migration**
```
php artisan make:model CollectionCard -m
```
```php
Schema::create('collection_cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('cid');
    $table->foreign('cid')->references('cid')->on('cards')->cascadeOnDelete();
    $table->enum('variant', ['normal', 'foil', 'hyper', 'prestige', 'hyper_foil'])->default('normal');
    $table->unsignedSmallInteger('quantity');
    $table->timestamps();

    $table->unique(['user_id', 'cid', 'variant']);
});
```
```php
class CollectionCard extends Model
{
    protected $fillable = ['user_id', 'cid', 'variant', 'quantity'];

    public function card()
    {
        return $this->belongsTo(Card::class, 'cid', 'cid');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 8.2 — Pagina `/collezione`**
Riusa `CardSearch` (Step 10.1) per la ricerca, poi un controllo quantita' per variante salvato via fetch senza reload:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/collezione', [CollectionController::class, 'index'])->name('collection.index');
    Route::patch('/collezione', [CollectionController::class, 'update'])->name('collection.update');
});
```
```php
class CollectionController extends Controller
{
    public function index(Request $request, CardSearch $search): View
    {
        $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo']))
            ->with(['collectionCards' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->paginate(24)->withQueryString();

        return view('collection.index', compact('cards'));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cid' => ['required', 'exists:cards,cid'],
            'variant' => ['required', 'in:normal,foil,hyper,prestige,hyper_foil'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $key = ['user_id' => $request->user()->id, 'cid' => $validated['cid'], 'variant' => $validated['variant']];

        if ($validated['quantity'] === 0) {
            CollectionCard::where($key)->delete(); // niente righe a zero: la somma per cid resta corretta senza filtrarle ovunque
        } else {
            CollectionCard::updateOrCreate($key, ['quantity' => $validated['quantity']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
```
Serve anche `Card::collectionCards()` (`hasMany(CollectionCard::class, 'cid', 'cid')`) per l'eager load sopra.

**Step 8.3 — "Carte mancanti per un mazzo"**
Tre informazioni per carta: mancante del tutto, posseduta-ma-impegnata-altrove, disponibile (non mostrata, e' il caso ok):
```php
// app/Services/DeckGapCalculator.php
class DeckGapCalculator
{
    /**
     * @return array{missing: array<string,int>, reservedElsewhere: array<string,int>}
     */
    public function forDeck(Deck $deck): array
    {
        $required = $deck->cards->mapWithKeys(fn ($c) => [$c->cid => $c->pivot->quantity]);

        $owned = CollectionCard::where('user_id', $deck->user_id)
            ->selectRaw('cid, SUM(quantity) as qty')->groupBy('cid')->pluck('qty', 'cid');

        $reserved = DeckCard::whereHas('deck', fn ($q) => $q->where('user_id', $deck->user_id)
                ->where('assembled', true)->where('id', '!=', $deck->id))
            ->selectRaw('cid, SUM(quantity) as qty')->groupBy('cid')->pluck('qty', 'cid');

        $missing = [];
        $reservedElsewhere = [];

        foreach ($required as $cid => $needed) {
            $ownedQty = (int) ($owned[$cid] ?? 0);
            $reservedQty = (int) ($reserved[$cid] ?? 0);
            $freelyAvailable = max(0, $ownedQty - $reservedQty);

            if ($freelyAvailable >= $needed) {
                continue;
            }

            $shortfall = $needed - $freelyAvailable;
            $reservedContribution = min($shortfall, $reservedQty);

            if ($reservedContribution > 0) {
                $reservedElsewhere[$cid] = $reservedContribution;
            }
            if (($trulyMissing = $shortfall - $reservedContribution) > 0) {
                $missing[$cid] = $trulyMissing;
            }
        }

        return compact('missing', 'reservedElsewhere');
    }
}
```
```php
Route::get('/mazzi/{deck}/delta', [DeckController::class, 'gap'])->name('decks.gap');
```
```php
public function gap(Deck $deck, DeckGapCalculator $calculator): View
{
    $this->authorize('view', $deck);
    ['missing' => $missing, 'reservedElsewhere' => $reserved] = $calculator->forDeck($deck);

    return view('decks.gap', [
        'deck' => $deck,
        'missingCards' => Card::whereIn('cid', array_keys($missing))->get()->keyBy('cid'),
        'missingQuantities' => $missing,
        'reservedCards' => Card::whereIn('cid', array_keys($reserved))->get()->keyBy('cid'),
        'reservedQuantities' => $reserved,
    ]);
}
```

☐ Fase 8 completata

---

## Fase 9 — Bot Telegram

**Step 9.1 — Libreria**
Facade `Http` nativa (https://laravel.com/docs/12.x/http-client), niente SDK esterno — la vecchia versione usava `telegram-bot/api`, ma l'ecosistema di wrapper PHP per Telegram di quella fascia è in gran parte poco mantenuto o abbandonato (es. `vjik/telegram-bot-api`).

**Step 9.2 — `TelegramService`**
`app/Services/TelegramService.php`, un solo posto per parlare con l'API Telegram:
```php
final readonly class TelegramActionResult
{
    public function __construct(
        public bool $successful,
        public ?int $messageId = null,
        public ?string $errorDescription = null,
        public array $raw = [],
    ) {}
}

class TelegramService
{
    public function sendMessage(int|string $chatId, string $text, array $options = []): TelegramActionResult {}
    public function sendPhoto(int|string $chatId, string $photoUrl, string $caption = '', array $options = []): TelegramActionResult {}
    public function editMessage(int|string $chatId, int $messageId, string $text): TelegramActionResult {}
    public function deleteMessage(int|string $chatId, int $messageId): TelegramActionResult {}
}
```
Ogni metodo traduce la risposta `{"ok": true/false, "result": {...}, "description": "..."}` di Telegram in un `TelegramActionResult`. Aggiungi altri metodi (`pinMessage`, `answerCallbackQuery`, ecc.) man mano che servono, stessa struttura. `editMessage` è quello usato dal progress-message unico dello scan (Step 4.5).

**Step 9.3 — Webhook**
```
php artisan make:controller TelegramController
```
```php
// routes/web.php — fuori da qualunque gruppo 'auth', Telegram non ha una sessione Laravel
Route::post('/telegram/webhook', [TelegramController::class, 'handle'])->name('telegram.webhook');
```
**Importante — esenzione CSRF**: Telegram invia una POST senza il token CSRF di Laravel, quindi la rotta va esclusa dalla verifica in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['telegram/webhook']);
})
```
Senza questa esenzione ogni update di Telegram riceverebbe un `419`, e il bot sembrerebbe non rispondere mai con un errore poco intuitivo da diagnosticare.
```php
class TelegramController extends Controller
{
    public function handle(Request $request, TelegramService $telegram): Response
    {
        if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== config('services.telegram.webhook_secret')) {
            abort(403);
        }

        $text = trim($request->input('message.text', ''));
        $chatId = $request->input('message.chat.id');
        [$command, $argument] = array_pad(explode(' ', $text, 2), 2, null);

        match ($command) {
            '/scan' => $this->handleScan($chatId, $telegram),
            '/search' => $this->handleSearch($chatId, $argument, $telegram),
            default => $telegram->sendMessage($chatId, 'Comandi disponibili: /scan, /search <nome carta>'),
        };

        return response()->noContent(); // Telegram si aspetta solo un 200, non legge il body
    }

    private function isAdminChat(int|string $chatId): bool
    {
        return (string) $chatId === (string) config('services.telegram.admin_chat_id');
    }

    private function handleScan(int|string $chatId, TelegramService $telegram): void
    {
        if (! $this->isAdminChat($chatId)) {
            $telegram->sendMessage($chatId, 'Comando riservato agli admin.');
            return;
        }
        \App\Jobs\ImportCardsFromSwuApiJob::dispatch();
        $telegram->sendMessage($chatId, 'Scan avviato, ti aggiorno qui.');
    }

    private function handleSearch(int|string $chatId, ?string $query, TelegramService $telegram): void
    {
        if (! $query) {
            $telegram->sendMessage($chatId, 'Uso: /search <nome carta>');
            return;
        }

        $card = \App\Models\Card::where('name', 'like', "%{$query}%")->first();

        if (! $card) {
            $telegram->sendMessage($chatId, "Nessuna carta trovata per {$query}.");
            return;
        }

        $imageUrl = $card->front_art_path ? asset('storage/'.$card->front_art_path) : null; // URL assoluto e pubblico richiesto da Telegram, non il path relativo salvato in DB
        $result = $imageUrl
            ? $telegram->sendPhoto($chatId, $imageUrl, $card->name)
            : $telegram->sendMessage($chatId, $card->name);

        if (! $result->successful) {
            $telegram->sendMessage($chatId, $card->name.' — '.route('cards.show', $card));
        }
    }
}
```
Registrazione webhook (una tantum, da terminale):
```
curl -F "url=https://unlimiteddb.mandich.dev/telegram/webhook" -F "secret_token=IL_TUO_SECRET" https://api.telegram.org/bot<TOKEN>/setWebhook
```

**Step 9.6 — `NotifyAdminJob`**
```php
class NotifyAdminJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $message) {}

    public function handle(TelegramService $telegram): void
    {
        $telegram->sendMessage(config('services.telegram.admin_chat_id'), $this->message);
    }
}
```

☐ Fase 9 completata

---

## Fase 10 — UI/UX e funzioni comuni TCG

**Step 10.1 — Ricerca/filtri carte**
```php
// app/Services/CardSearch.php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class CardSearch
{
    /**
     * Applies GET filters onto a Card query, shared between the /carte page and the public API (Fase 11)
     * Applica i filtri GET su una query di Card, condivisa tra la pagina /carte e l'API pubblica (Fase 11)
     */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
            ->when($filters['espansione'] ?? null, fn ($q, $exp) => $q->where('expansion', $exp))
            ->when($filters['tipo'] ?? null, fn ($q, $tipo) => $q->where('type', $tipo))
            ->when($filters['costo'] ?? null, fn ($q, $costo) => $q->where('cost', $costo))
            ->when($filters['aspetto'] ?? null, fn ($q, $id) => $q->whereHas('aspects', fn ($q2) => $q2->where('aspects.id', $id)))
            ->when($filters['tratto'] ?? null, fn ($q, $nome) => $q->whereHas('traits', fn ($q2) => $q2->where('traits.name', $nome)))
            ->when($filters['unique_card'] ?? null, fn ($q) => $q->where('unique_card', true));
    }
}
```
```php
// app/Http/Controllers/CardController.php
public function index(Request $request, CardSearch $search): View
{
    $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo', 'costo', 'aspetto', 'tratto', 'unique_card']))
        ->paginate(24)->withQueryString();

    return view('cards.index', ['cards' => $cards, 'filters' => $request->all()]);
}
```
Bug specifico segnalato in `todo.md` della vecchia versione da non ripetere: il campo di ricerca deve restare valorizzato dopo un reload con `?nome=...` nell'URL. Con un form server-rendered puro basta `<input name="nome" value="{{ $filters['nome'] ?? '' }}">` (Blade lo rivalorizza automaticamente ad ogni render). Se in futuro aggiungi un filtro live via Alpine/JS (`x-model`), inizializza lo stato JS leggendo lo stesso valore server-side al mount, non da stringa vuota — altrimenti un link condiviso con `?nome=...` mostra i risultati già filtrati ma la casella di ricerca appare vuota: esattamente il bug della vecchia versione.

**Step 10.2 — Statistiche mazzo**
```php
// app/Http/Controllers/DeckController.php
public function statistics(Deck $deck): View
{
    $costCurve = $deck->cards->groupBy('cost')->map(fn ($cards) => $cards->sum(fn ($c) => $c->pivot->quantity));
    $byType = $deck->cards->groupBy('type')->map(fn ($cards) => $cards->sum(fn ($c) => $c->pivot->quantity));
    $traits = $deck->cards->flatMap(fn ($c) => $c->traits->pluck('name'))->countBy();
    $avgPower = $deck->cards->where('type', 'Unit')->avg('power');
    $avgHealth = $deck->cards->where('type', 'Unit')->avg('health');

    return view('decks.statistics', compact('deck', 'costCurve', 'byType', 'traits', 'avgPower', 'avgHealth'));
}
```
Rotta: `Route::get('/mazzi/{deck}/statistiche', [DeckController::class, 'statistics'])->name('decks.statistics');`. Nella vista, Chart.js via CDN (`<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>`) e un `<canvas>` per grafico, alimentato passando i dati con `@json($costCurve)` dentro il tag `<script>` della pagina.

**Step 10.3 — Viste pubbliche/autenticate**
Pubbliche: catalogo carte, mazzi pubblici, nuove uscite. Autenticate (`auth`): creare/modificare mazzi, collezione, export/import.

**Step 10.4 — Pagina "Nuove uscite"**
`GET /nuove-uscite`, parametro opzionale `since` (`YYYY-MM-DD`) — se specificato resta un intervallo **arbitrario** a scelta dell'utente; se assente, default alla data di rilascio più recente (`Card::max('release_date')`), non a un intervallo fisso:
```php
$since = $request->query('since') ?? Card::max('release_date');
Card::where('release_date', '>=', $since)->orderByDesc('release_date')->get();
```
Filtra su `cards.release_date`, non su `expansions.legal_date` (concetti diversi). Interfaccia: `<input type="date">` in un form GET.

☐ Fase 10 completata

---

## Fase 11 — API REST pubblica

**Step 11.1 — Sanctum**
```
composer require laravel/sanctum
php artisan install:api
```
https://laravel.com/docs/12.x/sanctum

**Step 11.2 — Endpoint pubblici**
```php
// routes/api.php
Route::prefix('cards')->group(function () {
    Route::get('/search', [Api\CardController::class, 'search'])->middleware('throttle:60,1');
    Route::get('/{expansion}/{number}', [Api\CardController::class, 'show']);
});
Route::get('/decks/{userName}/{deckName}', [Api\DeckController::class, 'show']);
```
```php
// app/Http/Controllers/Api/CardController.php
class CardController extends Controller
{
    public function show(string $expansion, int $number): CardResource
    {
        $card = Card::where('expansion', $expansion)->where('number', $number)->firstOrFail();

        return new CardResource($card);
    }

    public function search(Request $request, CardSearch $search): AnonymousResourceCollection
    {
        $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo', 'costo', 'aspetto', 'tratto', 'unique_card']))
            ->paginate(24);

        return CardResource::collection($cards);
    }
}
```
```php
// app/Http/Controllers/Api/DeckController.php
class DeckController extends Controller
{
    public function show(string $userName, string $deckName): DeckResource
    {
        $deck = Deck::whereHas('user', fn ($q) => $q->where('name', $userName))
            ->where('name', $deckName)
            ->where('is_public', true)
            ->firstOrFail();

        return new DeckResource($deck);
    }
}
```
**Attenzione**: `firstOrFail()` sopra assume che la coppia (utente, nome mazzo) sia univoca. Se due mazzi pubblici dello stesso utente possono avere lo stesso nome (lo schema attuale non lo vieta), questa query ne prende uno arbitrario. Se vuoi che l'URL sia davvero stabile, aggiungi un vincolo `unique(['user_id', 'name'])` sulla migration `decks`, oppure usa l'`id` del mazzo invece del nome nell'URL pubblico.

`Api\CardController::search()` riusa `CardSearch` (Step 10.1): stessa logica della pagina `/carte`, output diverso (JSON via Resource). Principio generale: le pagine API condividono il backend delle pagine UI dove possibile, un solo posto da mantenere. https://laravel.com/docs/12.x/routing#rate-limiting

**Step 11.3 — Endpoint autenticati**
Token Sanctum per eventuali azioni future (es. sync collezione da app esterna) — non necessario al day 1.

**Step 11.4 — API Resources**
```
php artisan make:resource CardResource
```
```php
class CardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // 'id' interno volutamente escluso: 'cid' e' la chiave pubblica stabile, non ha senso esporre l'id di riga
            'cid' => $this->cid,
            'name' => $this->name,
            'title' => $this->title,
            'type' => $this->type,
            'rarity' => $this->rarity,
            'cost' => $this->cost,
            'power' => $this->power,
            'health' => $this->health,
            'text' => $this->text,
            'expansion' => $this->expansion,
            'number' => $this->number,
            'aspects' => $this->aspects->pluck('name'),
            'traits' => $this->traits->pluck('name'),
            'front_image' => $this->front_art_path ? asset('storage/'.$this->front_art_path) : null,
            'back_image' => $this->back_art_path ? asset('storage/'.$this->back_art_path) : null,
        ];
    }
}
```
Stesso principio per `DeckResource` (da creare con `php artisan make:resource DeckResource`): esponi `name`, `format`, `is_public`, e `cards` come `CardResource::collection($this->cards)` con la quantita' aggiunta manualmente (`$this->cards->map(fn ($c) => [...(new CardResource($c))->resolve(), 'quantity' => $c->pivot->quantity])`), non l'`id` interno del mazzo. https://laravel.com/docs/12.x/eloquent-resources

☐ Fase 11 completata

---

## Fase 12 — Deploy

Automatizzato: basta un merge sul branch `laravel`, la pipeline si occupa del resto (build immagine, deploy sulla VM via `new-site.sh`/Traefik). Nessuno step manuale da eseguire qui.

**Step 12.1 — Verifica post-deploy**
Dopo il merge, controlla che tutto sia partito correttamente:
- webhook Telegram registrato sul dominio giusto (`https://unlimiteddb.mandich.dev/telegram/webhook`, Step 9.3)
- `failed_jobs` vuota (`php artisan queue:failed` sul container, o query diretta)
- lo scan settimanale risulta schedulato (`php artisan schedule:list` mostra `cards:scan` al lunedi')

☐ Fase 12 completata

---

## Backlog — Funzionalità future (da `todo.md`, non pianificate in dettaglio)

Elencate per non perderle, ma fuori dallo scope attuale — da riprendere quando/se deciderai di implementarle:
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (es. "Aggro", "Control", "Budget", "Meta")
- Modalità offline/PWA per consultazione carte
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica di probabilità (ipergeometrica) di pescare una carta che soddisfi certi requisiti, nelle statistiche mazzo (Step 10.2)
- Apertura digitale dei booster pack (per cui è già propedeutico `expansions.group_main_expansion`, vedi schema)

---

## Note di analisi (perché queste scelte)

- **Queue reali invece di fireAndForget**: niente più thread simulati via HTTP ricorsivo per aggirare l'assenza di code su Altervista. https://laravel.com/docs/12.x/queues
- **Permessi granulari (Spatie)**: permessi singoli, i ruoli sono solo scorciatoie per assegnarne un gruppo insieme. https://spatie.be/docs/laravel-permission/v6/introduction
- **Enum + Strategy per i formati mazzo**: aggiungere un formato futuro richiede solo una nuova classe.
- **Blade + Alpine.js invece di Livewire**: la lentezza percepita nella vecchia versione era un bug preciso (catalogo intero come proprietà pubblica Livewire), non un limite del framework — ma Blade+Alpine evita il rischio per design.
- **`.env-overrides`**: pattern SWUDB per tracciare `APP_VERSION` in Git.
- **Verifica email nativa**: sostituisce token custom a 60 caratteri con `MustVerifyEmail` + middleware `verified`.
- **`system_errors` con 3 stati (`open`/`resolved`/`ignored`)**: la correttezza della logica di import la verifica Pest (dati controllati), non un controllo post-hoc sulla produzione — per questo non esiste più una tabella dedicata ai risultati dei test.
- **Nessuna colonna `deck_cards.role`, dedotta da `cards.type`**: `Leader`/`Base` sono già tipi di carta espliciti, duplicarli in una colonna `role` su `deck_cards` sarebbe ridondante — la cardinalità di leader/base dipende comunque dal formato (1 vs 2 leader), gestita dal `DeckFormatValidator` via join su `cards.type`.
- **`decks.assembled`**: necessario per calcolare non solo "cosa manca" ma anche "cosa possiedo ma è impegnato in un altro mazzo montato".
- **Immagini scaricate in locale**: il sito non dipende a runtime dalla disponibilità del CDN ufficiale, tempi di caricamento sotto controllo.

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
| Resend + Laravel (guida ufficiale) | https://resend.com/docs/send-with-laravel |
| Resend domini/DNS | https://resend.com/docs/dashboard/domains/introduction |
| Testing (Pest) | https://laravel.com/docs/12.x/testing |
| Spatie Laravel-permission | https://spatie.be/docs/laravel-permission/v6/introduction |
| Verifica email | https://laravel.com/docs/12.x/verification |
| Sanctum (API auth) | https://laravel.com/docs/12.x/sanctum |
| API Resources | https://laravel.com/docs/12.x/eloquent-resources |
| Rate limiting rotte | https://laravel.com/docs/12.x/routing#rate-limiting |
