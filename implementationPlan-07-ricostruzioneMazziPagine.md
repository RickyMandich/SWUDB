# Implementation plan 07 — Ricostruzione UnlimitedDB · Fase 7b: mazzi, pagine ed export/import

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisito**: [`implementationPlan-V-06-ricostruzioneMazziDominio.md`](implementationPlan-V-06-ricostruzioneMazziDominio.md) (enum, relazioni `leaders()`/`baseCard()`, validator, policy) e, per le pagine con la ricerca carte,
> [`implementationPlan-05-V-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-V-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`).
>
> Stato del codice (2026-10-09): `DeckController` scritto con lo snippet originale dello Step 7.6.2; le correzioni di questa versione del piano (`Gate::authorize`, versione nell'URL, leader e base nel form, `addCard` che somma, unique su `user_id`+`name`+`version`) vanno applicate sopra. Le viste seguono le "Convenzioni per le view" dell'indice e la base di layout dello Step 7.6.3.0.

## Fase 7 — Gestione mazzi multi-formato (pagine)

### Step 7.6 — Pagine mazzi

#### 7.6.0 — Migration: unicità di `user_id` + `name` + `version`
La migration `create_decks_table` non ha vincoli di unicità. Ogni versione di un mazzo condivide utente e nome con il resto della catena, quindi l'identificatore stabile è la terna `user_id`+`name`+`version`, che è anche quello che risolve l'URL `/mazzo/{username}/{deckname}/{version?}`.
La tabella è già nell'ambiente di test: non si modifica la migration esistente, se ne aggiunge una nuova.
```bash
php artisan make:migration add_unique_user_name_version_to_decks_table --table=decks
```
```php
public function up(): void
{
    Schema::table('decks', function (Blueprint $table) {
        $table->unique(['user_id', 'name', 'version']);
    });
}

public function down(): void
{
    Schema::table('decks', function (Blueprint $table) {
        $table->dropUnique(['user_id', 'name', 'version']);
    });
}
```
Se nel DB esistono già righe duplicate la migration fallisce: controllarlo prima con `SELECT user_id, name, version, COUNT(*) FROM decks GROUP BY user_id, name, version HAVING COUNT(*) > 1`.
`Rule::unique('decks')` in `store()` resta, per dare un errore leggibile sul nome prima che sia il database a rifiutare l'inserimento. Un doppio click su "Crea nuova versione" in condizioni di corsa darebbe una `UniqueConstraintViolationException` (500): accettabile, perché il caso normale (secondo click dopo il primo) crea semplicemente la versione successiva.

#### 7.6.1 — Rotte
In `routes/web.php`.
Le rotte usano slug/identificativi leggibili `{username}/{deckname}` anziché ID numerici.
Due utenti diversi possono avere mazzi con lo stesso nome (es. `/mazzo/alice/sabine-aggro` e `/mazzo/bob/sabine-aggro`).

```php
use App\Http\Controllers\DeckController;

// Creazione e lista mazzi
Route::get('/mazzi', [DeckController::class, 'index'])->name('decks.index'); // pubblica: mazzi pubblici + propri se loggato
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/mazzi/crea', [DeckController::class, 'create'])->name('decks.create');
    Route::post('/mazzi', [DeckController::class, 'store'])->name('decks.store');
});

// Modifica mazzo e azioni di deck-building (solo proprietario)
Route::middleware(['auth', 'verified'])->prefix('mazzo/modifica/{username}/{deckname}')->group(function () {
    Route::get('/', [DeckController::class, 'edit'])->name('decks.edit');
    Route::put('/carte', [DeckController::class, 'syncCards'])->name('decks.sync-cards'); // Flusso principale batch: aggiunge, aggiorna e rimuove in un'unica operazione
    Route::post('/carte', [DeckController::class, 'addCard'])->name('decks.add-card'); // Singola aggiunta (fallback)
    Route::delete('/carte/{card}', [DeckController::class, 'removeCard'])->name('decks.remove-card'); // Singola rimozione (fallback)
    Route::patch('/assembla', [DeckController::class, 'toggleAssembled'])->name('decks.toggle-assembled');
    Route::post('/versione', [DeckController::class, 'createVersion'])->name('decks.create-version');
});

// Visualizzazione mazzo (pubblica se is_public, oppure proprietario).
// Registrata DOPO il gruppo `mazzo/modifica/...` e con `{version?}` numerico: così `/mazzo/modifica/alice/2` (mazzo "2" di alice) non viene scambiato per il mazzo "alice" dell'utente "modifica".
Route::get('/mazzo/{username}/{deckname}/versioni', [DeckController::class, 'versions'])->name('decks.versions');
Route::get('/mazzo/{username}/{deckname}/{version?}', [DeckController::class, 'show'])->whereNumber('version')->name('decks.show');
```
`{card}` nella rotta di rimozione fa route-model-binding su `Card` con la PK `id` (default del modello). Il salvataggio batch via `PUT /carte` rappresenta il flusso primario dall'interfaccia grafica.

`decks.show` accetta una versione opzionale: senza `{version}` mostra l'ultima, con `/mazzo/alice/sabine-aggro/2` mostra la v2. `decks.versions` (e, nello Step 7.8, `decks.statistics`) va registrata **prima** di `decks.show`. Le rotte di modifica non hanno la versione: si modifica sempre l'ultima, le precedenti sono snapshot di sola lettura.


#### 7.6.2 — Controller: metodi con logica di business e risoluzione
```
php artisan make:controller DeckController
```
```php
use App\Enums\DeckFormat;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckValidation\DeckFormatValidatorFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Risolve il mazzo dall'utente e dal nome mazzo: l'ultima versione, oppure quella indicata da $version
 */
protected function resolveDeck(string $username, string $deckname, ?int $version = null): Deck
{
    $user = User::where('name', $username)->firstOrFail();

    return Deck::where('user_id', $user->id)
        ->where('name', $deckname)
        ->when($version !== null, fn ($q) => $q->where('version', $version))
        ->latest('version')
        ->firstOrFail();
}

/**
 * Elenca i mazzi pubblici più quelli dell'utente loggato
 */
public function index(): View
{
    $decks = Deck::with('leaders', 'baseCard', 'user') // eager load: anteprima nella lista senza N+1
        ->where('is_public', true)
        ->when(auth()->id(), fn ($q, $userId) => $q->orWhere('user_id', $userId))
        ->latest()
        ->paginate(20);

    return view('decks.index', compact('decks'));
}

public function create(): View
{
    return view('decks.create');
}

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('decks')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
        ],
        'format' => ['required', Rule::enum(DeckFormat::class)],
        'is_public' => ['boolean'],
    ]);

    $deck = Deck::create([
        'user_id' => $request->user()->id,
        'name' => $validated['name'],
        'format' => $validated['format'],
        'is_public' => $request->boolean('is_public'),
        'version' => 1,
    ]);

    return redirect()->route('decks.edit', [
        'username' => $request->user()->name,
        'deckname' => $deck->name,
    ]);
}

/**
 * Visualizzazione in sola lettura (accessibile se mazzo pubblico o se proprietario)
 */
public function show(string $username, string $deckname, ?int $version = null): View
{
    $deck = $this->resolveDeck($username, $deckname, $version);
    Gate::authorize('view', $deck);

    $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);
    $validationErrors = DeckFormatValidatorFactory::make($deck->format)->validate($deck);

    return view('decks.show', compact('deck', 'validationErrors'));
}

/**
 * Pagina di deck-building/modifica (riservata esclusivamente al proprietario)
 */
public function edit(string $username, string $deckname): View
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);

    return view('decks.edit', compact('deck'));
}

/**
 * Salvataggio batch delle carte del mazzo (flusso principale di modifica)
 * Riceve l'elenco completo o delta delle carte, aggiunge/aggiorna quelle con quantity > 0
 * e stacca (detach) quelle rimosse o con quantity = 0.
 * Leader e base sono righe di `deck_cards` come le altre: il form deve inviarle sempre (anche per poterle cambiare),
 * altrimenti `sync()` le stacca dal mazzo.
 */
public function syncCards(Request $request, string $username, string $deckname): RedirectResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $validated = $request->validate([
        'cards' => ['nullable', 'array'],
        'cards.*.card_id' => ['required_with:cards', 'exists:cards,id'],
        'cards.*.quantity' => ['required_with:cards', 'integer', 'min:0'],
    ]);

    $syncData = collect($validated['cards'] ?? [])
        ->filter(fn ($item) => (int) ($item['quantity'] ?? 0) > 0)
        ->keyBy('card_id')
        ->map(fn ($item) => ['quantity' => (int) $item['quantity']])
        ->all();

    $deck->cards()->sync($syncData);

    $errors = DeckFormatValidatorFactory::make($deck->format)->validate($deck->fresh());

    return redirect()->route('decks.edit', [$username, $deckname])
        ->with('status', 'Modifiche al mazzo salvate con successo.')
        ->with('deck-errors', $errors);
}

/**
 * Adds copies of a card: the quantity is added to the one already in the deck, it does not replace it
 * Aggiunge copie di una carta: la quantità si somma a quella già nel mazzo, non la sostituisce
 */
public function addCard(Request $request, string $username, string $deckname): RedirectResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $validated = $request->validate([
        'card_id' => ['required', 'exists:cards,id'],
        'quantity' => ['required', 'integer', 'min:1'],
    ]);

    $current = $deck->cards()->find($validated['card_id'])?->pivot->quantity ?? 0;

    $deck->cards()->syncWithoutDetaching([
        $validated['card_id'] => ['quantity' => $current + $validated['quantity']],
    ]);

    $errors = DeckFormatValidatorFactory::make($deck->format)->validate($deck->fresh());

    return back()->with('deck-errors', $errors);
}

public function removeCard(string $username, string $deckname, Card $card): RedirectResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $deck->cards()->detach($card->id);

    return back();
}

public function toggleAssembled(string $username, string $deckname): RedirectResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $deck->update(['assembled' => ! $deck->assembled]);

    return back();
}

/**
 * Snapshot on-demand: crea una nuova versione (v2, v3...) clonando le carte del mazzo attuale
 */
public function createVersion(string $username, string $deckname): RedirectResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $newDeck = DB::transaction(function () use ($deck) {
        $newVersion = $deck->replicate(['assembled']);
        $newVersion->version = $deck->version + 1;
        $newVersion->previous_version_id = $deck->id;
        $newVersion->save();

        // Snapshot delle sole ~25-30 righe di deck_cards
        foreach ($deck->cards as $card) {
            $newVersion->cards()->attach($card->id, [
                'quantity' => $card->pivot->quantity,
            ]);
        }

        return $newVersion;
    });

    return redirect()->route('decks.edit', [
        'username' => $username,
        'deckname' => $newDeck->name,
    ])->with('status', "Nuova versione v{$newDeck->version} creata con successo.");
}

/**
 * Elenca l'intera catena di versioni a cui appartiene il mazzo, dalla più vecchia
 */
public function versions(string $username, string $deckname): View
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('view', $deck);

    $chain = collect([$deck]);
    for ($d = $deck; $d->previousVersion; $d = $d->previousVersion) {
        $chain->prepend($d->previousVersion);
    }
    for ($d = $deck; $d->nextVersion; $d = $d->nextVersion) {
        $chain->push($d->nextVersion);
    }

    return view('decks.versions', ['deck' => $deck, 'decks' => $chain]);
}
```
La validazione del formato è **informativa, non bloccante**: permette di visualizzare gli errori e le incongruenze senza impedire il salvataggio incrementale della lista.

#### 7.6.3 — Viste

##### 7.6.3.0 — Base di layout (da creare prima delle viste)

Tutte le viste dei mazzi (Step 7.6.3, 7.8.3 e le eventuali pagine dello Step 7.7) passano da una **base di layout comune** costruita con Tailwind e Bladewind UI. La base **non tocca** `layouts/app.blade.php` (condiviso con admin, profilo e catalogo): è un componente Blade anonimo che lo incapsula.

**Regola sui componenti Bladewind: solo classi pubblicate in locale.** I componenti Bladewind sono già pubblicati in `resources/views/components/bladewind` (README, sezione "Stack"), quindi si usano **sempre con il punto** — `<x-bladewind.card>`, `<x-bladewind.alert>`, `<x-bladewind.table>` — e **mai con il namespace del package** (`<x-bladewind::card>`). Motivi: i file locali stanno in `resources/`, quindi Tailwind 4 li scansiona da solo e le loro classi finiscono in `public/build`; quelli sotto `vendor/` no (nello stage `node-builder` ci sono solo `resources/` e `public/`) e comunque non si possono modificare. Nessun nuovo `@source` e nessuna nuova `COPY` nel `Dockerfile`.

File da creare (tutti sotto `resources/views/components/decks/`, quindi nell'area già scansionata):

1. `layout.blade.php` → `<x-decks.layout title="...">`: pagina completa con header, contenitore e messaggi di sessione.

```blade
@props(['title'])

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $title }}
            </h2>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </x-slot>

    <div class="py-8">
        <div {{ $attributes->class(['mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8']) }}>
            @if (session('status'))
                <x-bladewind.alert type="success">{{ session('status') }}</x-bladewind.alert>
            @endif

            @if (!empty(session('deck-errors')))
                <x-bladewind.alert type="warning">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('deck-errors') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-app-layout>
```
   - `status` e `deck-errors` sono le chiavi di sessione impostate dal controller (Step 7.6.2: `syncCards`, `createVersion`): la vista non le ripete.
   - `deck-errors` è non bloccante (warning, non error). Il cast `(array)` presuppone una lista di stringhe: se `validate()` restituisce un'altra struttura, adattare qui il `foreach` e basta.
   - Lo slot `actions` è opzionale e ospita i pulsanti principali della pagina (es. "Modifica mazzo").
   - `<x-app-layout>` funziona anche per gli ospiti (README: navigazione con Login/Register), quindi `index`, `show` e `versions` pubbliche non richiedono nulla in più.
2. `panel.blade.php` → `<x-decks.panel>`: la superficie "card" riusata da ogni sezione, per non ripetere le classi `dark:` su ogni `<x-bladewind.card>`.

```blade
<x-bladewind.card {{ $attributes->class(['bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 border border-gray-200 dark:border-gray-700']) }}>
    {{ $slot }}
</x-bladewind.card>
```
   Se `<x-bladewind.card>` non propaga gli attributi extra al suo elemento radice, passare le classi con la prop `class="..."` come fa già `cards/show.blade.php`.

**Palette e dark mode** (README, "Convenzioni di sviluppo"): la base usa solo la palette del progetto e ogni colore ha la sua variante `dark:`.

| Uso | Light | Dark |
|---|---|---|
| Sfondo pagina | `bg-gray-100` | `dark:bg-gray-900` |
| Superfici (card, header, nav) | `bg-white` | `dark:bg-gray-800` |
| Testo principale | `text-gray-800` / `text-gray-900` | `dark:text-gray-100` / `dark:text-gray-200` |
| Testo secondario | `text-gray-500` | `dark:text-gray-400` |
| Bordi | `border-gray-200` | `dark:border-gray-700` |

Regole per chi scrive le viste:
- **Bladewind non segue la palette da solo**: ogni componente Bladewind riceve le classi `dark:` esplicite (per le card si usa `<x-decks.panel>`; per tabelle, input e select si passano `class="..."` espliciti).
- **Breeze dove c'è, Bladewind dove manca** (README): pulsanti con `<x-primary-button>`, `<x-secondary-button>`, `<x-danger-button>`; campi con `<x-input-label>`, `<x-text-input>`, `<x-input-error>`; badge con `<x-badge>`; Bladewind per `alert`, `table`, `card` e per i widget che Breeze non ha.
- **Nome a rischio confusione**: `<x-card>` è la carta di gioco del catalogo, `<x-bladewind.card>` è il contenitore UI. Non vanno scambiati.
- **Colori degli aspetti**: sono fissi e non cambiano col tema (Vigilanza #4073d4, Eroismo #ffffff, Offensiva #d30808, Malvagità #000000, Autorità #0b992d, Astuzia #eb9f1c). Si applicano con `style="background-color: ..."` (mai con classi Tailwind composte a runtime, che Tailwind non vedrebbe) e con un bordo `border border-gray-300 dark:border-gray-600`, altrimenti Eroismo sparisce sul chiaro e Malvagità sullo scuro.
- **Niente classi dinamiche**: scrivere sempre i nomi di classe completi (`@class([...])` o ternari con classi intere), mai `bg-{{ $colore }}-500`.
- **Asset Bladewind**: verificare in `layouts/app.blade.php` come vengono già caricati CSS/JS di Bladewind (le pagine `cards/show` e `cards/new-releases` li usano già) e **non aggiungere un secondo include** nella base.

##### 7.6.3.1 — Le viste

Tutte usano `<x-decks.layout title="...">` (che incapsula `<x-app-layout>`): il titolo si passa con la prop `title`, i pulsanti principali dell'header con `<x-slot name="actions">`, le sezioni con `<x-decks.panel>`.
- `resources/views/decks/index.blade.php`: lista (`<x-bladewind.table>` con thead/tbody scritti a mano, dentro `<x-decks.panel>`, + paginazione), anteprima di leader e base grazie all'eager load; link a `/mazzo/{username}/{deckname}`.
- `resources/views/decks/create.blade.php`: form nuovo mazzo con `name`, `format` come `<select>` sui `case` di `DeckFormat` e checkbox `is_public`.
- `resources/views/decks/show.blade.php`: vista di **sola lettura** pubblica (o del proprietario):
  - Mostra leader, base, carte raggruppate per tipologia e costo, statistiche rapide.
  - Link verso "Statistiche" (`route('decks.statistics', [$deck->user->name, $deck->name])`, Step 7.8).
  - Eventuali warning di formato (`$validationErrors`) in un `<x-bladewind.alert type="warning">` (non bloccante, come l'alert di `deck-errors` del layout).
  - Se `@can('update', $deck)`: mostra pulsante in evidenza "Modifica mazzo" verso `route('decks.edit', [$deck->user->name, $deck->name])`, nello slot `actions` di `<x-decks.layout>`.
  - Link verso "Cronologia versioni" `route('decks.versions', [$deck->user->name, $deck->name])`.
- `resources/views/decks/edit.blade.php`: pagina di **deck-building interattivo** (riservata al proprietario):
  - **Flusso primario di modifica in batch**: form `<form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}">` con `@method('PUT')`.
    - Consente all'utente di aggiungere e togliere N carte e regolarne le quantità all'interno della stessa schermata prima del salvataggio.
    - Selettore/pulsanti `+` e `-` per ciascuna carta già presente, pulsante di rimozione riga (che rimuove l'elemento dal form o imposta la quantità a 0).
    - Ricerca carte (`CardSearch`, Step 10.1): selezionando una carta dai risultati viene aggiunta una nuova riga alla bozza del mazzo.
    - Tasto primario in evidenza **"Salva modifiche"** / **"Conferma modifiche"** (in testa e in coda alla lista) per inviare l'intero stato delle carte in un'unica richiesta atomica a `decks.sync-cards`.
  - **Leader e base fanno parte del form**: sono righe di `deck_cards` come le altre e `sync()` stacca ciò che non riceve. Vanno mostrati nel form (con `quantity` 1, cambiabili con la ricerca carte filtrata per tipo `Leader`/`Base`) e inviati sempre in `cards[]`; così leader e base restano modificabili dopo la creazione del mazzo.
  - Componente `<x-deck-card-row>` per la riga-carta (riutilizzabile anche in `decks/gap.blade.php`).
  - Errori e avvisi di formato: li mostra già `<x-decks.layout>` (`session('deck-errors')`, alert non bloccante), nessun markup da duplicare nella vista.
  - Pulsante secondario separato per `toggle-assembled` (`PATCH decks.toggle-assembled`).
  - Pulsante secondario **"Crea nuova versione"** che invia una `POST` a `route('decks.create-version', [$deck->user->name, $deck->name])`.
  - Le rotte atomiche singole (`decks.add-card` e `decks.remove-card`) restano implementate nel backend per interoperabilità e fallback, ma la UI è progettata attorno al salvataggio batch.
- `resources/views/decks/versions.blade.php`: elenco cronologico delle versioni con badge della versione (`v1`, `v2`), data di creazione, stato (pubblico/privato, assemblato) e link alla consultazione di quella versione (`route('decks.show', [$deck->user->name, $deck->name, $v->version])`).

#### 7.6.4 — Risoluzione decisioni di architettura mazzi

##### 1. Rotte pulite e controllo degli accessi
- **Formato URL**: `/mazzo/{username}/{deckname}` per la visualizzazione e `/mazzo/modifica/{username}/{deckname}` per l'editing.
- **Supporto multi-utente**: utenti differenti possono avere mazzi con lo stesso nome (es. `Aggro Sabine`), poiché l'identificatore URL è contestualizzato all'`username`. Il database garantisce l'unicità del nome mazzo per singolo utente tramite `Rule::unique('decks')->where('user_id', $user->id)`.
- **Policy di accesso**:
  - `view`: autorizzata se `($deck->is_public || (auth()->check() && auth()->id() === $deck->user_id))`.
  - `update`: autorizzata **esclusivamente** se l'utente possiede il mazzo (`auth()->id() === $deck->user_id`) o possiede il permesso admin `decks.manage-any`.

##### 2. Strategia di versioning (Snapshot on-demand)
- **Nessuna duplicazione nelle modifiche ordinarie**: durante il normale deck-building (aggiungere carte, cambiare quantità, togliere carte), le operazioni avvengono direttamente sulle righe di `deck_cards` del mazzo corrente. Non viene creata nessuna nuova versione ad ogni singola modifica.
- **Nessuna ricostruzione storica complessa**: non si ricorre a complicati registri differenziali/event-sourcing (che renderebbero lento ed ostico ricostruire rimozioni di carte o fare eager loading Eloquent).
- **Snapshot intenzionale esplicito**: quando l'utente vuole congelare una milestone (es. "v1 per il torneo", "v2 con nuove carte"), preme il pulsante "Crea nuova versione". Il sistema duplica il record `Deck` impostando `version = $old->version + 1` e `previous_version_id = $old->id`, e copia le ~25-30 righe di `deck_cards` (operazione da 1 millisecondo e <1KB). Ciascuna versione resta così un'entità indipendente, perfettamente queryabile, esportabile e visualizzabile.

##### 3. Modifica in Batch vs Singola
- Nella pagina di modifica le modifiche avvengono prevalentemente in batch: l'utente sperimenta, aggiunge o toglie liberamente carte, incrementa/decrementa quantità e poi consolida l'intero mazzo premendo "Salva modifiche" (`PUT /carte`).
- I metodi singoli `addCard` e `removeCard` rimangono mantenuti nel controller e nelle rotte per completezza e compatibilità, ma non sono il flusso primario dell'interfaccia. `addCard` **somma** la quantità richiesta a quella già presente (non imposta la quantità finale: per quello c'è `syncCards`).

##### 4. Autorizzazione e versioni nell'URL
- **`Gate::authorize()`, non `$this->authorize()`**: dal Laravel 11 la classe base `Controller` è vuota e non ha più il trait `AuthorizesRequests`. Il modo indicato dalla documentazione di Laravel 12 è `Gate::authorize('update', $deck)` (`use Illuminate\Support\Facades\Gate;`), che lancia `AuthorizationException` (403). Vale per ogni metodo del controller, statistiche comprese.
- **Link alle versioni**: `/mazzo/{username}/{deckname}/{version?}`. Senza versione è l'ultima, con la versione (numerica, `whereNumber`) lo snapshot. La modifica resta solo sull'ultima: sulle versioni precedenti `decks/show` non mostra "Modifica mazzo" ma un avviso di sola lettura con link all'ultima.
- **Unicità**: `UNIQUE(user_id, name, version)` a livello di database (Step 7.6.0).


### Step 7.7 — Export/Import mazzi

#### 7.7.1 — `app/Services/DeckExporter.php`
```bash
php artisan make:class Services/DeckExporter
```
Le classi stanno in `app/Services/` (non in un controller) per poterle testare in Pest senza una request HTTP finta.
```php
class DeckExporter
{
    public function toText(Deck $deck): string
    {
        // Formato ufficiale SWU: verificare la sintassi esatta su un file esportato da swudb.com o dall'app ufficiale
        // prima di fissarla qui. Indicativamente "Leader: {name}", "Base: {name}", poi "{quantity}x {name}" per il resto.
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

#### 7.7.2 — `app/Services/DeckImporter.php`
```bash
php artisan make:class Services/DeckImporter
```
```php
class DeckImporter
{
    /**
     * @return array{deck: ?Deck, notFound: array<int, string>} Il mazzo creato (null se l'input non era valido) e i riferimenti carta non trovati
     */
    public function fromJson(string $json, User $owner): array
    {
        // decode -> per ogni riga: Card::where('cid', ...)->first(); se null, aggiungi a notFound invece di interrompere
        // stesso principio fail-soft del job di import: una carta non trovata non deve far fallire l'intero import
    }

    public function fromUrl(string $url, User $owner): array
    {
        // Http::get($url) con timeout esplicito; gestire: url non raggiungibile (->failed()), redirect, content-type inatteso
        // (verificarlo prima del json_decode). È il punto che nella vecchia versione aveva il bug segnalato in todo.md:
        // i tre casi limite vanno testati esplicitamente in Pest.
    }
}
```

### Step 7.8 — Statistiche mazzo

> Spostato qui dal piano 05 (era lo Step 10.2): è una pagina dei mazzi e usa `DeckController`, `resolveDeck()` e le rotte `/mazzo/{username}/{deckname}` dello Step 7.6, quindi si esegue **dopo il 7.6**.
> Differenze rispetto alla versione originale del piano 05:
> - controller e rotta contestuali a `{username}/{deckname}`, come tutte le altre pagine mazzo (prima usavano il route-model-binding `{deck}` su `/mazzi/{deck}/statistiche`);
> - il tipo unità è `'Unità'`, non `'Unit'`: i valori di `cards.type` sono in italiano, come li dà l'API con `locale=it` (vedi migration `create_cards_table`);
> - `cards.traits` caricato in eager loading (evita N+1 sul conteggio dei tratti).

#### 7.8.1 — Metodo del controller
In `app/Http/Controllers/DeckController.php`, accanto a `show()`:
```php
/**
 * Shows the statistics page of a deck (cost curve, card types, traits, averages)
 * Mostra la pagina delle statistiche di un mazzo (curva dei costi, tipi di carta, tratti, medie)
 */
public function statistics(string $username, string $deckname): View
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('view', $deck);

    $deck->load(['cards.traits', 'user']);

    $costCurve = $deck->cards->groupBy('cost')->map(fn ($cards) => $cards->sum(fn ($c) => $c->pivot->quantity));
    $byType = $deck->cards->groupBy('type')->map(fn ($cards) => $cards->sum(fn ($c) => $c->pivot->quantity));
    $traits = $deck->cards->flatMap(fn ($c) => $c->traits->pluck('name'))->countBy();
    $avgPower = $deck->cards->where('type', 'Unità')->avg('power');
    $avgHealth = $deck->cards->where('type', 'Unità')->avg('health');

    return view('decks.statistics', compact('deck', 'costCurve', 'byType', 'traits', 'avgPower', 'avgHealth'));
}
```
Da decidere prima di scrivere la vista (il codice sopra è quello originale del piano 05, non l'ho cambiato):
- `$traits`, `$avgPower` e `$avgHealth` contano ogni carta **una volta sola**, mentre `$costCurve` e `$byType` pesano per `quantity`: con 3 copie della stessa unità le medie e i tratti non rispecchiano il mazzo reale. Per coerenza andrebbero pesati anche loro.
- `$costCurve` raggruppa anche le carte con `cost` nullo (le Basi), che finiscono in un'unica barra senza etichetta: valutare di escluderle o di mostrarle a parte.

#### 7.8.2 — Rotta
In `routes/web.php`, nel blocco "Visualizzazione mazzo" dello Step 7.6.1, subito dopo la rotta `decks.versions` (pubblica come `show`: l'accesso lo decide la policy `view`):
```php
Route::get('/mazzo/{username}/{deckname}/statistiche', [DeckController::class, 'statistics'])->name('decks.statistics');
```

#### 7.8.3 — Vista `resources/views/decks/statistics.blade.php`
Usa `<x-decks.layout>` (Step 7.6.3.0) come ogni altra pagina, titolo nella prop `title`. Unica eccezione al "solo Blade + Alpine": Chart.js via CDN (`<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>`),
un `<canvas>` per grafico e i dati passati con `@json($costCurve)` dentro lo `<script>` della pagina. Il `<script>` sta nel corpo della vista, non nel layout: è l'unica pagina che ne ha bisogno.
Nessun bundler e nessun componente Vue/React per questo. Un link "Torna al mazzo" verso `route('decks.show', [$deck->user->name, $deck->name])`.

#### 7.8.4 — Link dalla pagina del mazzo
In `resources/views/decks/show.blade.php` (Step 7.6.3) il link "Statistiche" verso `route('decks.statistics', [$deck->user->name, $deck->name])`, vicino a "Cronologia versioni".
