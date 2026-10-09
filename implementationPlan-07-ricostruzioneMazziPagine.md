# Implementation plan 07 — Ricostruzione UnlimitedDB · Fase 7b: mazzi, pagine ed export/import

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisito**: [`implementationPlan-V-06-ricostruzioneMazziDominio.md`](implementationPlan-V-06-ricostruzioneMazziDominio.md) (enum, relazioni `leaders()`/`baseCard()`, validator, policy) e, per le pagine con la ricerca carte,
> [`implementationPlan-05-V-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-V-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`).
>
> Stato del codice (2026-10-09): `DeckController` scritto con lo snippet originale dello Step 7.6.2; le correzioni di questa versione del piano (`Gate::authorize`, versione nell'URL, leader e base nel form, `addCard` che somma, unique su `user_id`+`name`+`version`) vanno applicate sopra. Le viste seguono le "Convenzioni per le view" dell'indice.

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
Tutte estendono `<x-app-layout>`, con titolo nello slot `header`. Ogni vista parte da uno **scheletro** (sotto, dopo la descrizione della vista) costruito con Tailwind e Bladewind UI: sono punti di partenza da rifinire, non markup definitivo.

##### Regole comuni degli scheletri
- **Bladewind solo con il punto**: i componenti sono già pubblicati in `resources/views/components/bladewind` (README, "Stack"), quindi `<x-bladewind.card>`, `<x-bladewind.table>`, `<x-bladewind.alert>`, **mai** `<x-bladewind::card>` (namespace del package, sotto `vendor/`). I file locali stanno in `resources/`, quindi Tailwind 4 ne scansiona le classi senza nuovi `@source` né `COPY` nel `Dockerfile`.
- **Palette e dark mode** (README, "Convenzioni di sviluppo"), sempre con la variante `dark:`:
  - sfondo pagina `bg-gray-100` / `dark:bg-gray-900` (già nel layout);
  - superfici `bg-white` / `dark:bg-gray-800`;
  - testo principale `text-gray-800` / `dark:text-gray-100`; secondario `text-gray-500` / `dark:text-gray-400`;
  - bordi `border-gray-200` / `dark:border-gray-700`.
- **Bladewind non segue la palette da solo**: ogni componente Bladewind riceve le classi `dark:` esplicite. Negli scheletri le classi della superficie sono `bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700` su ogni `<x-bladewind.card>`.
- **Breeze dove c'è, Bladewind dove manca**: pulsanti con `<x-primary-button>` / `<x-secondary-button>` / `<x-danger-button>`, label e campi con `<x-input-label>`, `<x-text-input>`, `<x-input-error>`; Bladewind per card, tabella e alert.
- `<x-card>` è la carta di gioco del catalogo, `<x-bladewind.card>` è il contenitore UI: non vanno scambiati.
- **Colori degli aspetti** (fissi, non seguono il tema): Vigilanza #4073d4, Eroismo #ffffff, Offensiva #d30808, Malvagità #000000, Autorità #0b992d, Astuzia #eb9f1c. Applicarli con `style="background-color: ..."` e un bordo `border border-gray-300 dark:border-gray-600` (altrimenti Eroismo sparisce sul chiaro e Malvagità sullo scuro); mai classi Tailwind composte a runtime (`bg-{{ $x }}-500`), che Tailwind non vede.
- **Da verificare in locale prima di copiare**: la firma di `<x-bladewind.table>` (negli scheletri è usata con `<x-slot name="header">` e righe `<tr>` a mano) e se `<x-bladewind.card>` propaga gli attributi extra; i nomi dei campi di `Card` (`name`, `type`, `cost`, `front_art_path`, `expansion`, `number`); se `baseCard` è una relazione a singolo modello o una collection (negli scheletri è trattata come singolo modello); se `DeckFormat` ha un metodo `label()` (altrimenti si usa `$case->value`). Gli scheletri usano l'asset delle immagini come `asset('storage/'.$card->front_art_path)` (nginx serve `/storage/`) con un `@if` perché `front_art_path` può essere nullo (rifinitura aperta del piano 05).

- `resources/views/decks/index.blade.php`: lista (tabella + paginazione), anteprima di leader e base grazie all'eager load; link a `/mazzo/{username}/{deckname}`.

Scheletro:
```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Mazzi</h2>
            @auth
                <a href="{{ route('decks.create') }}"><x-primary-button type="button">Nuovo mazzo</x-primary-button></a>
            @endauth
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <x-bladewind.table>
                    <x-slot name="header">
                        <th>Mazzo</th><th>Leader</th><th>Base</th><th>Formato</th><th>Autore</th><th>Versione</th>
                    </x-slot>
                    @forelse ($decks as $deck)
                        <tr>
                            <td>
                                <a class="font-medium text-gray-800 hover:underline dark:text-gray-100"
                                   href="{{ route('decks.show', [$deck->user->name, $deck->name]) }}">{{ $deck->name }}</a>
                                @unless ($deck->is_public)
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">privato</span>
                                @endunless
                            </td>
                            <td>
                                @foreach ($deck->leaders as $leader)
                                    @if ($leader->front_art_path)
                                        <img class="mr-1 inline h-12 rounded" src="{{ asset('storage/'.$leader->front_art_path) }}" alt="{{ $leader->name }}">
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                @if ($deck->baseCard?->front_art_path)
                                    <img class="h-12 rounded" src="{{ asset('storage/'.$deck->baseCard->front_art_path) }}" alt="{{ $deck->baseCard->name }}">
                                @endif
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $deck->format->value }}</td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $deck->user->name }}</td>
                            <td class="text-gray-500 dark:text-gray-400">v{{ $deck->version }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-gray-500 dark:text-gray-400">Nessun mazzo.</td></tr>
                    @endforelse
                </x-bladewind.table>
            </x-bladewind.card>

            {{ $decks->links() }}
        </div>
    </div>
</x-app-layout>
```

- `resources/views/decks/create.blade.php`: form nuovo mazzo con `name`, `format` come `<select>` sui `case` di `DeckFormat` e checkbox `is_public`.

Scheletro:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Nuovo mazzo</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('decks.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nome" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="format" value="Formato" />
                        <select id="format" name="format"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @foreach (\App\Enums\DeckFormat::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('format') === $case->value)>{{ $case->value }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('format')" class="mt-2" />
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="hidden" name="is_public" value="0">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        Mazzo pubblico
                    </label>

                    <div class="flex justify-end"><x-primary-button>Crea mazzo</x-primary-button></div>
                </form>
            </x-bladewind.card>
        </div>
    </div>
</x-app-layout>
```

- `resources/views/decks/show.blade.php`: vista di **sola lettura** pubblica (o del proprietario):
  - Mostra leader, base, carte raggruppate per tipologia e costo, statistiche rapide.
  - Link verso "Statistiche" (`route('decks.statistics', [$deck->user->name, $deck->name])`, Step 7.8).
  - Eventuali warning di formato (`$validationErrors`).
  - Se `@can('update', $deck)`: mostra pulsante in evidenza "Modifica mazzo" verso `route('decks.edit', [$deck->user->name, $deck->name])`.
  - Link verso "Cronologia versioni" `route('decks.versions', [$deck->user->name, $deck->name])`.

Scheletro:
```blade
@php
    $body = $deck->cards->reject(fn ($c) => in_array($c->type, ['Leader', 'Base']));
    $groups = $body->groupBy('type')->map(fn ($g) => $g->sortBy([['cost', 'asc'], ['name', 'asc']]));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $deck->name }} <span class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }} · {{ $deck->user->name }}</span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('decks.versions', [$deck->user->name, $deck->name]) }}"><x-secondary-button type="button">Cronologia versioni</x-secondary-button></a>
                <a href="{{ route('decks.statistics', [$deck->user->name, $deck->name]) }}"><x-secondary-button type="button">Statistiche</x-secondary-button></a>
                @can('update', $deck)
                    <a href="{{ route('decks.edit', [$deck->user->name, $deck->name]) }}"><x-primary-button type="button">Modifica mazzo</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (!empty($validationErrors))
                <x-bladewind.alert type="warning">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) $validationErrors as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-4">
                    @foreach ($deck->leaders as $leader)
                        @if ($leader->front_art_path)
                            <img class="h-40 rounded-lg" src="{{ asset('storage/'.$leader->front_art_path) }}" alt="{{ $leader->name }}">
                        @endif
                    @endforeach
                    @if ($deck->baseCard?->front_art_path)
                        <img class="h-40 rounded-lg" src="{{ asset('storage/'.$deck->baseCard->front_art_path) }}" alt="{{ $deck->baseCard->name }}">
                    @endif
                </div>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ $deck->format->value }} · {{ $body->sum('pivot.quantity') }} carte
                </p>
            </x-bladewind.card>

            @foreach ($groups as $type => $cards)
                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">{{ $type }} ({{ $cards->sum('pivot.quantity') }})</h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($cards as $card)
                            <li class="flex items-center justify-between py-2 text-sm">
                                <a class="text-gray-800 hover:underline dark:text-gray-100"
                                   href="{{ route('cards.show', [$card->expansion, $card->number]) }}">{{ $card->name }}</a>
                                <span class="text-gray-500 dark:text-gray-400">costo {{ $card->cost }} · ×{{ $card->pivot->quantity }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-bladewind.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
```
Il raggruppamento nel `@php` può passare nel controller se la vista cresce.

- `resources/views/decks/edit.blade.php`: pagina di **deck-building interattivo** (riservata al proprietario):
  - **Flusso primario di modifica in batch**: form `<form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}">` con `@method('PUT')`.
    - Consente all'utente di aggiungere e togliere N carte e regolarne le quantità all'interno della stessa schermata prima del salvataggio.
    - Selettore/pulsanti `+` e `-` per ciascuna carta già presente, pulsante di rimozione riga (che rimuove l'elemento dal form o imposta la quantità a 0).
    - Ricerca carte (`CardSearch`, Step 10.1): selezionando una carta dai risultati viene aggiunta una nuova riga alla bozza del mazzo.
    - Tasto primario in evidenza **"Salva modifiche"** / **"Conferma modifiche"** (in testa e in coda alla lista) per inviare l'intero stato delle carte in un'unica richiesta atomica a `decks.sync-cards`.
  - **Leader e base fanno parte del form**: sono righe di `deck_cards` come le altre e `sync()` stacca ciò che non riceve. Vanno mostrati nel form (con `quantity` 1, cambiabili con la ricerca carte filtrata per tipo `Leader`/`Base`) e inviati sempre in `cards[]`; così leader e base restano modificabili dopo la creazione del mazzo.
  - Componente `<x-deck-card-row>` per la riga-carta (riutilizzabile anche in `decks/gap.blade.php`).
  - Errori e avvisi di formato in riquadro di alert non bloccante (`session('deck-errors')`).
  - Pulsante secondario separato per `toggle-assembled` (`PATCH decks.toggle-assembled`).
  - Pulsante secondario **"Crea nuova versione"** che invia una `POST` a `route('decks.create-version', [$deck->user->name, $deck->name])`.
  - Le rotte atomiche singole (`decks.add-card` e `decks.remove-card`) restano implementate nel backend per interoperabilità e fallback, ma la UI è progettata attorno al salvataggio batch.

Scheletro (il form principale contiene tutte le righe, leader e base compresi; i due pulsanti secondari stanno in form **separati** fuori dal principale, perché i form non si annidano):
```blade
@php
    $rows = $deck->cards->map(fn ($c) => [
        'card_id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'quantity' => (int) $c->pivot->quantity,
    ])->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Modifica {{ $deck->name }} <span class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <x-bladewind.alert type="success">{{ session('status') }}</x-bladewind.alert>
            @endif
            @if (!empty(session('deck-errors')))
                <x-bladewind.alert type="warning">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('deck-errors') as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
                  x-data="{ rows: @js($rows) }" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>

                {{-- Ricerca carte (CardSearch, Step 10.1): selezionando un risultato fa
                     rows.push({ card_id, name, type, quantity: 1 }). Per leader e base filtrare per tipo. --}}

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Riga-carta: da estrarre in <x-deck-card-row> (riusata anche in decks/gap) --}}
                        <template x-for="(row, i) in rows" :key="row.card_id">
                            <li class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                <input type="hidden" :name="`cards[${i}][card_id]`" :value="row.card_id">
                                <input type="hidden" :name="`cards[${i}][quantity]`" :value="row.quantity">
                                <span x-text="row.name"></span>
                                <span class="text-gray-500 dark:text-gray-400" x-text="row.type"></span>
                                <span class="flex items-center gap-2">
                                    {{-- Leader e base restano a quantità 1: niente +/- --}}
                                    <template x-if="!['Leader', 'Base'].includes(row.type)">
                                        <span class="flex items-center gap-2">
                                            <button type="button" class="px-2" @click="row.quantity = Math.max(1, row.quantity - 1)">−</button>
                                            <span x-text="row.quantity"></span>
                                            <button type="button" class="px-2" @click="row.quantity++">+</button>
                                        </span>
                                    </template>
                                    <button type="button" class="text-red-600 dark:text-red-400" @click="rows.splice(i, 1)">Rimuovi</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </x-bladewind.card>

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>
            </form>

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('decks.toggle-assembled', [$deck->user->name, $deck->name]) }}">
                    @csrf @method('PATCH')
                    <x-secondary-button type="submit">{{ $deck->assembled ? 'Segna come smontato' : 'Segna come assemblato' }}</x-secondary-button>
                </form>
                <form method="POST" action="{{ route('decks.create-version', [$deck->user->name, $deck->name]) }}">
                    @csrf
                    <x-secondary-button type="submit">Crea nuova versione</x-secondary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```
Rimuovere una riga la toglie dall'array inviato: `sync()` stacca ciò che non riceve (stesso effetto della quantità a 0).

- `resources/views/decks/versions.blade.php`: elenco cronologico delle versioni con badge della versione (`v1`, `v2`), data di creazione, stato (pubblico/privato, assemblato) e link alla consultazione di quella versione (`route('decks.show', [$deck->user->name, $deck->name, $v->version])`).

Scheletro:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Versioni di {{ $deck->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-3 px-4 sm:px-6 lg:px-8">
            @foreach ($decks as $v)
                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:bg-gray-700 dark:text-gray-100">v{{ $v->version }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $v->created_at->format('d/m/Y') }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $v->is_public ? 'pubblico' : 'privato' }}{{ $v->assembled ? ' · assemblato' : '' }}
                            </span>
                            @if ($v->is($deck))<span class="text-xs text-gray-500 dark:text-gray-400">(questa)</span>@endif
                        </div>
                        <a class="text-sm text-gray-800 hover:underline dark:text-gray-100"
                           href="{{ route('decks.show', [$deck->user->name, $deck->name, $v->version]) }}">Consulta</a>
                    </div>
                </x-bladewind.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
```


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
Estende `<x-app-layout>` come ogni altra pagina, titolo nello slot `header`. Unica eccezione al "solo Blade + Alpine": Chart.js via CDN (`<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>`),
un `<canvas>` per grafico e i dati passati con `@json($costCurve)` dentro lo `<script>` della pagina. Il `<script>` sta nel corpo della vista, non nel layout: è l'unica pagina che ne ha bisogno.
Nessun bundler e nessun componente Vue/React per questo. Un link "Torna al mazzo" verso `route('decks.show', [$deck->user->name, $deck->name])`.

#### 7.8.4 — Link dalla pagina del mazzo
In `resources/views/decks/show.blade.php` (Step 7.6.3) il link "Statistiche" verso `route('decks.statistics', [$deck->user->name, $deck->name])`, vicino a "Cronologia versioni".
