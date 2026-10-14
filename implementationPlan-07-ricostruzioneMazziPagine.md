# Implementation plan 07 — Ricostruzione UnlimitedDB · Fase 7b: mazzi, pagine ed export/import

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisito**: [`implementationPlan-V-06-ricostruzioneMazziDominio.md`](implementationPlan-V-06-ricostruzioneMazziDominio.md) (enum, relazioni `leaders()`/`baseCard()`, validator, policy) e, per le pagine con la ricerca carte,
> [`implementationPlan-V-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-V-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`).
>
> **Stato del codice (2026-10-14, verificato sui file)**
> - Applicati gli Step 7.6.0–7.6.7, 7.7 (export/import) e 7.8 (statistiche). Restano: i test dello Step 7.9, le correzioni emerse dalla verifica del 2026-10-14 (rotte `decks.import*` dentro il gruppo `auth`+`verified`, pulsanti di export/import da riattivare in `decks/show` e `decks/index`, pulsante "Copia come nuovo mazzo" di `decks/show` che passa un `base_version` ignorato da `edit`) e le verifiche a mano (7.6.6bis.6, 7.6.7.4, 7.7.0bis).
> - Scelte volute dell'utente, che prevalgono sul testo sotto: nella copertina `<x-deck-cover>` la base sta in alto a sinistra e il leader in basso a destra; `decks.statistics` va registrata prima di `decks.show` come descritto in 7.8.2; `decks/show` usa un `@forelse` sui leader per mostrare "Nessun leader.".
>
> **Storico (2026-10-11, superato dal punto sopra)**
> - **Già applicato**: Step 7.6.0 (migration `2026_10_09_124754_add_unique_user_name_version_to_decks_table`), 7.6.1 (rotte `decks.*` in `routes/web.php`, tranne quelle indicate sotto), 7.6.2 (`DeckController` con `Gate::authorize`, versione nell'URL, `addCard` che somma) e le viste di 7.6.3 (`decks/index`, `create`, `show`, `edit`, `versions`). Le viste seguono le "Convenzioni per le view" dell'indice.
> - **Bug nel codice già applicato** (si correggono nello Step 7.6.5): `decks/show.blade.php` chiama `route('decks.statistics', ...)`, che non è registrata, quindi **ogni pagina di un mazzo dà `RouteNotFoundException`** finché non c'è la rotta dello Step 7.8.2; `Deck::baseCard()` è una `belongsToMany` (restituisce una collection) ma `index` e `show` la trattano come modello singolo (`$deck->baseCard?->front_art_path` lancia un'eccezione); `DeckController::index` elenca ogni versione di ogni mazzo come riga a sé; `show` non segnala che una versione precedente è di sola lettura e propone comunque "Modifica mazzo" (che modifica l'ultima).
> - **Mancante**: ricerca carte nella pagina di modifica (`edit.blade.php` ha solo un commento al posto della ricerca, quindi oggi non si può aggiungere nessuna carta: Step 7.6.6), export/import (Step 7.7, c'erano solo gli stub), statistiche (Step 7.8, rotta, metodo e vista) e test (Step 7.9).
> - **Aggiornamento (2026-10-11, pomeriggio)**: l'utente ha applicato `->first()` per la base in `index` e `show` (7.6.5.1 fatto; resta la base di `show` a `h-12` invece di `h-40`). Aggiunto lo Step 7.6.7 (copertina leader/base sovrapposti nella lista).
> - **Ordine di applicazione consigliato**: 7.8.1–7.8.2 (rotta e metodo `statistics`, per sbloccare subito `show`) → 7.6.5 → 7.6.6 → 7.6.6bis → 7.7 → 7.8.3–7.8.4 → 7.9.
> - **Verifiche in locale chiuse** (le "Da verificare" dell'elenco sotto): `<x-bladewind.table>` accetta `<x-slot name="header">` e le righe `<tr>` a mano nello slot (ramo `is_null($data)` del componente); `<x-bladewind.card>` propaga gli attributi extra e unisce `class` a quelle di default (`bg-white dark:bg-dark-800/25`): le classi `dark:bg-gray-800` dello scheletro vincono solo perché `app.css` viene caricato dopo il CSS di Bladewind, da controllare a occhio nel tema scuro; `Deck::baseCard()` è una **collection** (vedi 7.6.5.1); `DeckFormat` **non ha** `label()` (si usa `->value`); `layouts/app.blade.php` non ha `@stack('scripts')`, quindi gli script di pagina stanno inline nel corpo della vista.

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


### Step 7.6.5 — Correzioni al codice già applicato (2026-10-11)

Corregge i bug elencati nello stato in testa al piano. Si applica dopo 7.8.2 (la rotta `decks.statistics` deve esistere, altrimenti `show` continua a fallire).

#### 7.6.5.1 — `baseCard` è una collection
`Deck::baseCard()` è una `belongsToMany` filtrata per tipo (Step 7.2 del piano 06): `$deck->baseCard` è una `Collection`, anche se contiene una sola base, e `->front_art_path` su una collection lancia `Property [front_art_path] does not exist on this collection instance`. **Non cambiare la relazione** (il piano 06 e i validator la usano così): nelle viste si prende la prima.

In `resources/views/decks/index.blade.php`, subito dopo `@forelse ($decks as $deck)`:
```blade
@php $base = $deck->baseCard->first(); @endphp
```
e la cella della base diventa (con il placeholder "Nessuna base." che hai già messo in `index` per i mazzi senza base, ora agganciato a `$base`):
```blade
<td>
    @if ($base?->front_art_path)
        <img class="h-12 rounded" src="{{ asset('storage/' . $base->front_art_path) }}" alt="{{ $base->name }}">
    @else
        <span class="text-gray-500 dark:text-gray-400">Nessuna base.</span>
    @endif
</td>
```
**Attenzione al caso "mazzo senza base"**: `$deck->baseCard?->front_art_path !== null` (la forma oggi in `index`) non lo risolve, perché `$deck->baseCard` non è mai `null`: è una collection, anche vuota, e leggere una proprietà su una `Collection` lancia `Property [front_art_path] does not exist on this collection instance`. Il nullsafe `?->` va applicato al risultato di `->first()`, che invece è `null` quando non c'è nessuna base. Vale anche per `show`: lì, per i mazzi senza leader o senza base, si può mostrare lo stesso testo ("Nessun leader." / "Nessuna base.") nella card dell'intestazione.
In `resources/views/decks/show.blade.php`, nel `@php` in cima aggiungere `$base = $deck->baseCard->first();` e usare `$base` al posto di `$deck->baseCard` nel blocco delle immagini:
```blade
@if ($base?->front_art_path)
    <img class="h-40 rounded-lg" src="{{ asset('storage/' . $base->front_art_path) }}" alt="{{ $base->name }}">
@endif
```

#### 7.6.5.2 — `index` mostra solo l'ultima versione di ogni mazzo
Ogni versione è una riga di `decks`, quindi la lista di oggi mostra `Sabine Aggro v1`, `v2`, `v3` come tre mazzi. Mostrare solo le versioni senza successore (le altre stanno in `decks.versions`). Il `orWhere` va raggruppato, altrimenti scavalca il filtro sull'ultima versione. In `DeckController::index`:
```php
public function index(): View
{
    $decks = Deck::with('leaders', 'baseCard', 'user') // eager load: anteprima nella lista senza N+1
        ->whereDoesntHave('nextVersion') // solo l'ultima versione di ogni catena
        ->where(function ($q) {
            $q->where('is_public', true)
                ->when(auth()->id(), fn ($q, $userId) => $q->orWhere('user_id', $userId));
        })
        ->latest()
        ->paginate(20);

    return view('decks.index', compact('decks'));
}
```
Se si vuole indicare nella lista che esistono versioni precedenti, `previous_version_id` non nullo è già sufficiente (`@if ($deck->previous_version_id) ... @endif`); non serve altro.

#### 7.6.5.3 — `show`: versione precedente in sola lettura, link con versione, export
In `DeckController::show`, calcolare se è l'ultima versione e passarlo alla vista:
```php
$isLatest = ! $deck->nextVersion()->exists();

return view('decks.show', compact('deck', 'validationErrors', 'isLatest'));
```
In `resources/views/decks/show.blade.php`:
- il pulsante "Modifica mazzo" compare solo sull'ultima versione (la modifica agisce sempre sull'ultima, le precedenti sono snapshot):
```blade
@if ($isLatest)
    @can('update', $deck)
        <a href="{{ route('decks.edit', [$deck->user->name, $deck->name]) }}"><x-primary-button type="button">Modifica mazzo</x-primary-button></a>
    @endcan
@endif
```
- il link "Statistiche" porta alla versione che si sta guardando (la rotta ha la versione opzionale, Step 7.8.2):
```blade
<a href="{{ route('decks.statistics', [$deck->user->name, $deck->name, $deck->version]) }}"><x-secondary-button type="button">Statistiche</x-secondary-button></a>
```
- avviso di sola lettura in cima al contenuto, prima degli avvisi di formato:
```blade
@unless ($isLatest)
    <x-bladewind.alert type="info">
        Stai consultando la versione v{{ $deck->version }}, in sola lettura.
        <a class="underline" href="{{ route('decks.show', [$deck->user->name, $deck->name]) }}">Vai all'ultima versione</a>
    </x-bladewind.alert>
@endunless
```
- pulsanti di export (Step 7.7.3), accanto a "Statistiche":
```blade
<a href="{{ route('decks.export', [$deck->user->name, $deck->name, 'txt', $deck->version]) }}"><x-secondary-button type="button">Esporta TXT</x-secondary-button></a>
<a href="{{ route('decks.export', [$deck->user->name, $deck->name, 'json', $deck->version]) }}"><x-secondary-button type="button">Esporta JSON</x-secondary-button></a>
```

#### 7.6.5.4 — Lista: pulsante "Importa mazzo"
In `decks/index.blade.php`, nello slot `header`, accanto a "Nuovo mazzo" (dentro `@auth`):
```blade
<a href="{{ route('decks.import') }}"><x-secondary-button type="button">Importa mazzo</x-secondary-button></a>
```

#### 7.6.5.5 — Decisioni
- `<x-deck-card-row>` (citato in 7.6.3) **non si estrae nel piano 07**: la riga resta inline in `edit.blade.php`; si estrae nel piano 08 (Step 8.3.4) quando serve davvero anche in `decks/gap`.
- La validazione del formato resta informativa (non blocca il salvataggio), come deciso in 7.6.2.
- `createVersion` e `versions` non cambiano: `versions` fa un paio di query per anello della catena, che a poche versioni per mazzo è accettabile.


### Step 7.6.7 — Lista mazzi: copertina con leader e base sovrapposti

Nella lista `/mazzi` le colonne "Leader" e "Base" diventano una sola colonna con una "copertina" del mazzo: il leader davanti, la base dietro, spostata in alto e a sinistra di un quarto della sua dimensione (il leader sta in basso a destra), così si capisce a colpo d'occhio di che base si tratta. Il fronte di un leader è sempre orizzontale (come lo è quello della base), quindi le due immagini hanno lo stesso rapporto 7:5.

- **Premier / Eternal** (un leader): leader intero davanti, base dietro.
- **Twin Suns** (due leader): al posto del leader intero davanti c'è un'immagine composta dalla **metà sinistra** del fronte di ciascuno dei due leader, affiancate; base dietro come sopra.
- Si distingue Twin Suns dal numero di leader (`$deck->leaders->count() >= 2`), non dall'enum: nessuna dipendenza dai nomi dei `case` di `DeckFormat`.
- Mazzo senza leader o senza base: un riquadro tratteggiato con "Nessun leader." / "Nessuna base." al posto dell'immagine mancante (assorbe il fix dei placeholder già applicato in `decks/index`, e quello di 7.6.5.1).
- Nessuna query in più: `leaders` e `baseCard` sono già in eager load in `DeckController::index`.

Si applica insieme a 7.6.5.1 (la cella della base descritta lì viene sostituita da questo componente nella lista; in `show` resta quella di 7.6.5.1).

#### 7.6.7.1 — Componente `<x-deck-cover>`
Nuovo file `resources/views/components/deck-cover.blade.php`. Misure: l'immagine è larga `w-28` (7rem) e alta `aspect-[7/5]` (5rem); lo spostamento del 25% sono 1.75rem a destra e 1.25rem in alto, quindi il contenitore è `w-35` (8.75rem) × `h-25` (6.25rem). Con Tailwind 4 le dimensioni `w-35`/`h-25` esistono già (scala dinamica dello spacing).
```blade
@props(['deck'])
@php
    $leaders = $deck->leaders->values()->take(2);
    $base = $deck->baseCard->first();
    $twinSuns = $leaders->count() >= 2;
    $art = fn ($card) => $card?->front_art_path ? asset('storage/' . $card->front_art_path) : null;
@endphp
<div {{ $attributes->class('relative h-25 w-35 shrink-0') }}>
    {{-- Base: dietro, in alto e a sinistra --}}
    <div class="absolute left-0 top-0 z-0 aspect-[7/5] w-28 overflow-hidden rounded
                border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-700">
        @if ($art($base))
            <img class="h-full w-full object-cover" src="{{ $art($base) }}" alt="{{ $base->name }}" loading="lazy">
        @else
            <span class="flex h-full items-center justify-center border border-dashed border-gray-300 text-center text-[10px] leading-tight text-gray-500 dark:border-gray-600 dark:text-gray-400">Nessuna base.</span>
        @endif
    </div>

    {{-- Leader: davanti, in basso a destra --}}
    <div class="absolute bottom-0 right-0 z-10 flex aspect-[7/5] w-28 overflow-hidden rounded
                border border-gray-300 bg-gray-100 shadow-md dark:border-gray-600 dark:bg-gray-700">
        @forelse ($leaders as $leader)
            @if ($twinSuns)
                {{-- Twin Suns: metà sinistra di ciascun leader, affiancate --}}
                <div class="relative h-full w-1/2 overflow-hidden {{ $loop->first ? 'border-r border-gray-300 dark:border-gray-600' : '' }}">
                    @if ($art($leader))
                        <img class="h-full w-[200%] max-w-none object-cover object-left" src="{{ $art($leader) }}" alt="{{ $leader->name }}" loading="lazy">
                    @endif
                </div>
            @elseif ($art($leader))
                <img class="h-full w-full object-cover" src="{{ $art($leader) }}" alt="{{ $leader->name }}" loading="lazy">
            @endif
        @empty
            <span class="flex h-full w-full items-center justify-center text-center text-[10px] leading-tight text-gray-500 dark:text-gray-400">Nessun leader.</span>
        @endforelse
    </div>
</div>
```
Note:
- `w-[200%] max-w-none object-left` dentro un contenitore `w-1/2 overflow-hidden` mostra la metà sinistra del fronte; `overflow-hidden` taglia il resto. Se preferisci la metà **destra** di un leader (per esempio del secondo, così il nome o l'illustrazione non si ripetono sullo stesso lato), basta `object-right` al posto di `object-left` su quell'immagine.
- Le classi sono scritte per intero (nessun `w-{{ $x }}` costruito a runtime), quindi Tailwind le vede.
- `loading="lazy"`: la lista ha fino a 20 righe per pagina, ognuna con due o tre immagini.
- Se un leader di un Twin Suns ha l'immagine nulla, la sua metà resta vuota (sfondo grigio): lo stesso vale per un mazzo con un solo leader in un formato a due.

#### 7.6.7.2 — `decks/index.blade.php`
Intestazione: le colonne `<th>Leader</th><th>Base</th>` diventano una sola:
```blade
<th>Mazzo</th>
<th>Leader / Base</th>
<th>Formato</th>
<th>Autore</th>
<th>Versione</th>
```
Riga: le due celle `<td>` di leader e base (con i `@foreach`/`@if` e i placeholder) si sostituiscono con:
```blade
<td><x-deck-cover :deck="$deck" /></td>
```
e il riquadro vuoto passa da `colspan="6"` a `colspan="5"`. Il `@php $base = ...` di 7.6.5.1 nella lista non serve più (la cella della base non esiste più, la gestisce il componente): si applica solo la parte di `show`.

#### 7.6.7.3 — Facoltativo: riuso in `show`
In `decks/show.blade.php` la card di intestazione mostra leader e base affiancati e grandi (`h-40`). Si può lasciare così (le immagini grandi servono per leggere le carte); il componente è pensato per la lista.

#### 7.6.7.4 — Verifica a mano
Su `/mazzi`: un Premier/Eternal (leader davanti, base più in alto e a destra), un Twin Suns (due metà sinistre affiancate davanti), un mazzo senza base e uno senza leader (riquadri "Nessuna base." / "Nessun leader."), il tema scuro (bordi e sfondi dei riquadri) e la larghezza della colonna su mobile.


### Step 7.6.6 — Ricerca carte nella pagina di modifica

Oggi `decks/edit.blade.php` ha solo un commento al posto della ricerca, quindi non c'è modo di aggiungere carte a un mazzo. La ricerca è un endpoint JSON che riusa `CardSearch` (piano 05, Step 10.1) e un piccolo componente Alpine nella vista.

#### 7.6.6.1 — Rotta
In `routes/web.php`, nel gruppo `mazzo/modifica/{username}/{deckname}`, accanto alle altre rotte di deck-building:
```php
Route::get('/cerca-carte', [DeckController::class, 'searchCards'])->middleware('throttle:60,1')->name('decks.search-cards');
```

#### 7.6.6.2 — Metodo del controller
In `DeckController` (aggiungere `use App\Services\CardSearch;` e `use Illuminate\Http\JsonResponse;`):
```php
/**
 * Card types that can be part of a deck (tokens are excluded)
 * Tipi di carta che possono stare in un mazzo (i segnalini sono esclusi)
 */
protected const DECK_CARD_TYPES = ['Base', 'Evento', 'Leader', 'Miglioria', 'Unità'];

/**
 * Searches cards to add to the deck, JSON for the deck-building page (owner only)
 * Cerca carte da aggiungere al mazzo, JSON per la pagina di deck-building (solo proprietario)
 */
public function searchCards(Request $request, string $username, string $deckname, CardSearch $search): JsonResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $validated = $request->validate([
        'nome' => ['required', 'string', 'min:2', 'max:100'],
        'tipi' => ['nullable', 'array'],
        'tipi.*' => ['string', Rule::in(self::DECK_CARD_TYPES)],
    ]);

    $cards = $search->apply(Card::query(), [
        'nome' => $validated['nome'],
        'tipi' => $validated['tipi'] ?? self::DECK_CARD_TYPES,
    ])->limit(20)->get();

    return response()->json($cards->map(fn (Card $card) => [
        'id' => $card->id,
        'name' => $card->name,
        'title' => $card->title,
        'type' => $card->type,
        'label' => $card->snippet, // "SOR-12 - Nome, TITOLO": l'espansione e il numero distinguono le ristampe
        'image' => $card->front_art_path ? asset('storage/'.$card->front_art_path) : null,
    ])->values());
}
```
`CardSearch::apply()` accetta i nomi dei parametri del catalogo (`nome`, `tipi`); `CardBuilder::get()` applica l'ordinamento di default, quindi non serve un `orderBy`. Per le ristampe di una stessa carta il numero nell'etichetta le distingue. **Il tetto di 20 risultati è superato dallo Step 7.6.6bis** (paginazione a 30 con "Carica altre"): chi cerca una carta che possa stare nel mazzo, e non una carta precisa, può avere liste lunghe.

#### 7.6.6.3 — `resources/views/decks/edit.blade.php` (versione completa)
Sostituisce il file: aggiunge il titolo della carta nelle righe, la ricerca con aggiunta alla bozza, l'avviso degli import (`import-warnings`, Step 7.7.3) e il componente Alpine `deckEditor`. Leader e base restano a quantità 1 e, aggiungendone uno nuovo oltre il massimo del formato (1 leader, 2 in Twin Suns; 1 base), **sostituisce il più vecchio**: così il cambio di leader o base è un solo click. Il campo di ricerca non ha `name` (non viene inviato col form) e `Invio` non lo invia.
```blade
@php
    $rows = $deck->cards->map(fn ($c) => [
        'card_id' => $c->id,
        'name' => $c->title ? "{$c->name}, {$c->title}" : $c->name,
        'type' => $c->type,
        'quantity' => (int) $c->pivot->quantity,
    ])->values();
    $editorConfig = [
        'rows' => $rows,
        'format' => $deck->format->value,
        'searchUrl' => route('decks.search-cards', [$deck->user->name, $deck->name]),
    ];
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
            @if (!empty(session('import-warnings')))
                <x-bladewind.alert type="warning">
                    Alcune righe dell'import non sono state riconosciute e sono state saltate:
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('import-warnings') as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
                  x-data="deckEditor(@js($editorConfig))" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <x-input-label for="card-search" value="Aggiungi carte" />
                    <div class="mt-1 flex gap-2">
                        <input id="card-search" type="search" x-model="query"
                               @input.debounce.300ms="search()" @keydown.enter.prevent="search()"
                               placeholder="Nome della carta (almeno 2 lettere)"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <select x-model="type" @change="search()"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Tutti i tipi</option>
                            @foreach (['Leader', 'Base', 'Unità', 'Evento', 'Miglioria'] as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>
                    <p x-show="searched && !results.length && !error" class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nessuna carta trovata.</p>
                    <ul x-show="results.length" class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="card in results" :key="card.id">
                            <li class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                <span class="flex items-center gap-3">
                                    <img x-show="card.image" :src="card.image" :alt="card.name" class="h-10 rounded" loading="lazy">
                                    <span x-text="card.label"></span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span class="text-gray-500 dark:text-gray-400" x-text="card.type"></span>
                                    <button type="button" class="font-medium text-indigo-600 dark:text-indigo-400" @click="add(card)">Aggiungi</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </x-bladewind.card>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <p x-show="!rows.length" class="text-sm text-gray-500 dark:text-gray-400">Il mazzo è vuoto: cerca una carta qui sopra.</p>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Riga-carta inline: l'estrazione in <x-deck-card-row> è rimandata al piano 08 (Step 8.3.4) --}}
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

            {{-- Form separati dal principale: i form non si annidano --}}
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

    {{-- Il layout non ha @stack('scripts'): il componente sta qui. Alpine parte da resources/js/app.js (modulo differito),
         quindi questo listener `alpine:init` viene registrato prima dell'avvio. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('deckEditor', (config) => ({
                rows: config.rows,
                format: config.format,
                query: '',
                type: '',
                results: [],
                searched: false,
                error: '',
                seq: 0,

                async search() {
                    this.error = '';
                    if (this.query.trim().length < 2) {
                        this.results = [];
                        this.searched = false;
                        return;
                    }
                    const params = new URLSearchParams({ nome: this.query.trim() });
                    if (this.type) params.append('tipi[]', this.type);
                    const current = ++this.seq; // scarta le risposte arrivate fuori ordine
                    try {
                        const response = await fetch(`${config.searchUrl}?${params}`, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error(response.status);
                        const data = await response.json();
                        if (current === this.seq) {
                            this.results = data;
                            this.searched = true;
                        }
                    } catch (e) {
                        if (current === this.seq) this.error = 'Ricerca non riuscita, riprova.';
                    }
                },

                add(card) {
                    const single = ['Leader', 'Base'].includes(card.type);
                    const existing = this.rows.find((r) => r.card_id === card.id);
                    if (existing) {
                        if (!single) existing.quantity++;
                        return;
                    }
                    if (single) {
                        const max = card.type === 'Leader' && this.format === 'twin_suns' ? 2 : 1;
                        const same = this.rows.filter((r) => r.type === card.type);
                        if (same.length >= max) {
                            this.rows.splice(this.rows.indexOf(same[0]), 1); // sostituisce il più vecchio
                        }
                    }
                    this.rows.push({
                        card_id: card.id,
                        name: card.title ? `${card.name}, ${card.title}` : card.name,
                        type: card.type,
                        quantity: 1,
                    });
                },
            }));
        });
    </script>
</x-app-layout>
```
La bozza vive solo nel browser fino a "Salva modifiche": chi chiude la pagina prima perde le modifiche non salvate (l'avviso `beforeunload` si aggiunge nello Step 7.6.6bis). Nessun limite di copie lato client: lo dice il validator del formato, in modo informativo, dopo il salvataggio.


### Step 7.6.6bis — Ricerca carte paginata e avviso di modifiche non salvate

Si applica sopra lo Step 7.6.6 già fatto. Due modifiche, in due file: `DeckController::searchCards` (7.6.6bis.1) e `resources/views/decks/edit.blade.php` (7.6.6bis.2 e 7.6.6bis.3). Il 7.6.6bis.1 cambia la forma della risposta JSON, quindi **va applicato insieme al 7.6.6bis.2**: da soli, la vista non funziona più (si aspetta un array, riceve un oggetto).

- **Paginazione**: al posto di `->limit(20)` la ricerca usa `->paginate(30)`. Chi costruisce un mazzo non cerca sempre una carta precisa, spesso cerca "una carta che possa starci", quindi la lista può essere lunga. Nella vista i risultati si accumulano con un pulsante "Carica altre" (non pagine separate): aggiungere una carta alla bozza non fa perdere il punto raggiunto nella lista.
- **Avviso `beforeunload`**: se ci sono modifiche non salvate alla bozza, il browser chiede conferma prima di chiudere la scheda, ricaricare o seguire un link. Era la rifinitura facoltativa citata alla fine dello Step 7.6.6.3.

#### 7.6.6bis.1 — Backend: `paginate(30)` in `DeckController::searchCards`
Sostituire il metodo `searchCards` con questa versione (i `use` e la costante `DECK_CARD_TYPES` non cambiano):
```php
/**
 * Searches cards to add to the deck, paginated JSON for the deck-building page (owner only)
 * Cerca carte da aggiungere al mazzo, JSON paginato per la pagina di deck-building (solo proprietario)
 */
public function searchCards(Request $request, string $username, string $deckname, CardSearch $search): JsonResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $validated = $request->validate([
        'nome' => ['required', 'string', 'min:2', 'max:100'],
        'tipi' => ['nullable', 'array'],
        'tipi.*' => ['string', Rule::in(self::DECK_CARD_TYPES)],
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    $cards = $search->apply(Card::query(), [
        'nome' => $validated['nome'],
        'tipi' => $validated['tipi'] ?? self::DECK_CARD_TYPES,
    ])->paginate(30); // il numero di pagina lo legge paginate() dal parametro `page` della request

    return response()->json([
        'data' => $cards->getCollection()->map(fn (Card $card) => [
            'id' => $card->id,
            'name' => $card->name,
            'title' => $card->title,
            'type' => $card->type,
            'label' => $card->snippet, // "SOR-12 - Nome, TITOLO": l'espansione e il numero distinguono le ristampe
            'image' => $card->front_art_path ? asset('storage/'.$card->front_art_path) : null,
        ])->values(),
        'page' => $cards->currentPage(),
        'last_page' => $cards->lastPage(),
        'total' => $cards->total(),
    ]);
}
```
Perché è fatto così:
- `paginate()` esegue prima una `COUNT` (su `toBase()`, quindi senza ordinamento) e poi la query dei dati con `LIMIT`/`OFFSET` passando da `CardBuilder::get()`, che applica l'ordine di default del catalogo. Quell'ordine ha `cards.id` come tie-breaker finale, quindi è stabile: le pagine successive non si sovrappongono e non perdono carte.
- Non si restituisce il paginator così com'è (`response()->json($cards)`): includerebbe `links` e URL assoluti che il client non usa. Si espongono solo `data`, `page`, `last_page` e `total`.
- `page` è validato come intero positivo; un valore non valido, se arrivasse comunque a `paginate()`, ricade sulla pagina 1.
- Il `throttle:60,1` della rotta (7.6.6.1) resta: ogni "Carica altre" è una richiesta in più.

#### 7.6.6bis.2 — Vista: risultati accumulati e pulsante "Carica altre"
In `resources/views/decks/edit.blade.php`.

**a) Blocco dei risultati** (dentro la card "Aggiungi carte", dopo il paragrafo `Nessuna carta trovata.`). Sostituire il `<ul x-show="results.length" ...>...</ul>` con questo, che aggiunge il contatore e il pulsante e dà alla lista un'altezza massima con scroll (con più pagine accumulate, senza limite spingerebbe la bozza del mazzo fuori dallo schermo):
```blade
<p x-show="results.length" class="mt-3 text-xs text-gray-500 dark:text-gray-400"
    x-text="`${results.length} di ${total} carte`"></p>
<ul x-show="results.length" class="mt-1 max-h-96 divide-y divide-gray-200 overflow-y-auto dark:divide-gray-700">
    <template x-for="card in results" :key="card.id">
        <li class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
            <span class="flex items-center gap-3">
                <img x-show="card.image" :src="card.image" :alt="card.name" class="h-10 rounded" loading="lazy">
                <span x-text="card.label"></span>
            </span>
            <span class="flex items-center gap-3">
                <span class="text-gray-500 dark:text-gray-400" x-text="card.type"></span>
                <button type="button" class="font-medium text-indigo-600 dark:text-indigo-400"
                    @click="add(card)">Aggiungi</button>
            </span>
        </li>
    </template>
</ul>
<div x-show="page < lastPage" class="mt-3 flex justify-center">
    {{-- `x-bind:disabled` e non `:disabled`: su un componente Blade i due punti iniziali sarebbero letti come espressione PHP --}}
    <x-secondary-button type="button" @click="loadMore()" x-bind:disabled="loading">Carica altre</x-secondary-button>
</div>
```

**b) Logica**: vedi il blocco `<script>` completo in 7.6.6bis.3, che contiene sia la paginazione sia l'avviso (i campi `page`, `lastPage`, `total`, `loading`, `searchParams` e i metodi `search`, `loadMore`, `fetchPage`).

Comportamento:
- `search()` riparte sempre dalla pagina 1 e **sostituisce** i risultati; `loadMore()` chiede la pagina successiva e li **accoda**.
- I parametri della ricerca (`nome` e `tipi[]`) si memorizzano in `searchParams` quando parte la ricerca, e `loadMore()` riusa quelli: se nel frattempo l'utente ha già modificato il campo senza che la ricerca (debounce di 300 ms) sia partita, "Carica altre" continua la ricerca che si vede, non quella che si sta scrivendo.
- `seq` scarta le risposte arrivate fuori ordine, come prima; ora `search()` lo incrementa anche quando il testo è sotto le 2 lettere (prima una risposta in volo poteva ripopolare una lista appena svuotata).
- Il pulsante compare solo se `page < lastPage` ed è disabilitato mentre una richiesta è in corso (niente doppi click che chiedono due volte la stessa pagina).

#### 7.6.6bis.3 — Avviso `beforeunload` sulle modifiche non salvate
La bozza vive solo nel browser (`rows`) fino a "Salva modifiche". Si confronta uno "snapshot" della bozza con quello preso all'apertura della pagina: se differiscono e non si sta salvando, `beforeunload` chiede conferma.

**a) Tag del form principale**: aggiungere `@submit="saving = true"`, così l'invio del form di salvataggio non scatena l'avviso (anche il submit è una navigazione):
```blade
<form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
    x-data="deckEditor(@js($editorConfig))" @submit="saving = true" class="space-y-6">
```

**b) Blocco `<script>` completo** (sostituisce quello di 7.6.6.3; `add()` è invariato):
```blade
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('deckEditor', (config) => ({
            rows: config.rows,
            format: config.format,
            query: '',
            type: '',
            results: [],
            searched: false,
            error: '',
            seq: 0,
            page: 1,
            lastPage: 1,
            total: 0,
            loading: false,
            searchParams: '',
            initial: '',
            saving: false,

            init() {
                this.initial = this.snapshot();
                window.addEventListener('beforeunload', (event) => {
                    if (this.dirty && !this.saving) {
                        event.preventDefault();
                        event.returnValue = ''; // richiesto da alcuni browser; il testo del messaggio non è personalizzabile
                    }
                });
            },

            /**
             * Canonical string of the draft: card ids and quantities, order-independent
             * Stringa canonica della bozza: id e quantità delle carte, senza dipendere dall'ordine
             */
            snapshot() {
                return this.rows.map((r) => `${r.card_id}:${r.quantity}`).sort().join('|');
            },

            get dirty() {
                return this.snapshot() !== this.initial;
            },

            async search() {
                this.error = '';
                if (this.query.trim().length < 2) {
                    this.seq++; // scarta eventuali risposte ancora in volo
                    this.results = [];
                    this.searched = false;
                    this.page = 1;
                    this.lastPage = 1;
                    this.total = 0;
                    this.loading = false;
                    return;
                }
                const params = new URLSearchParams({
                    nome: this.query.trim()
                });
                if (this.type) params.append('tipi[]', this.type);
                this.searchParams = params.toString();
                await this.fetchPage(1);
            },

            async loadMore() {
                if (this.loading || this.page >= this.lastPage) return;
                await this.fetchPage(this.page + 1);
            },

            async fetchPage(page) {
                const current = ++this.seq; // scarta le risposte arrivate fuori ordine
                this.loading = true;
                try {
                    const response = await fetch(`${config.searchUrl}?${this.searchParams}&page=${page}`, {
                        headers: {
                            Accept: 'application/json'
                        }
                    });
                    if (!response.ok) throw new Error(response.status);
                    const body = await response.json();
                    if (current !== this.seq) return;
                    this.results = page === 1 ? body.data : this.results.concat(body.data);
                    this.page = body.page;
                    this.lastPage = body.last_page;
                    this.total = body.total;
                    this.searched = true;
                } catch (e) {
                    if (current === this.seq) this.error = 'Ricerca non riuscita, riprova.';
                } finally {
                    if (current === this.seq) this.loading = false;
                }
            },

            add(card) {
                const single = ['Leader', 'Base'].includes(card.type);
                const existing = this.rows.find((r) => r.card_id === card.id);
                if (existing) {
                    if (!single) existing.quantity++;
                    return;
                }
                if (single) {
                    const max = card.type === 'Leader' && this.format === 'twin_suns' ? 2 : 1;
                    const same = this.rows.filter((r) => r.type === card.type);
                    if (same.length >= max) {
                        this.rows.splice(this.rows.indexOf(same[0]), 1); // sostituisce il più vecchio
                    }
                }
                this.rows.push({
                    card_id: card.id,
                    name: card.title ? `${card.name}, ${card.title}` : card.name,
                    type: card.type,
                    quantity: 1,
                });
            },
        }));
    });
</script>
```
Punti da tenere:
- `init()` è chiamato da Alpine da solo quando il componente parte; lo snapshot iniziale è quello delle righe già salvate.
- Lo snapshot ignora l'ordine delle righe: aggiungere una carta e poi rimuoverla riporta la bozza identica all'originale e l'avviso non compare.
- Dopo un salvataggio la pagina si ricarica (redirect a `decks.edit`), quindi `initial` si ricalcola dalle righe nuove: nessun reset da gestire a mano.
- `saving` non torna mai a `false`: dopo il submit si cambia pagina comunque. Se il server risponde con un errore di validazione la pagina viene ricaricata con `old()` assente e la bozza persa, come già oggi.

#### 7.6.6bis.4 — Effetti da conoscere
- L'avviso scatta anche sui form **"Crea nuova versione"** e **"Segna come assemblato"** (stanno fuori dal form principale, quindi non impostano `saving`) e sui link del menu. Su "Crea nuova versione" è corretto: lo snapshot copia le carte **salvate**, non la bozza, e senza avviso le modifiche non salvate andrebbero perse.
- Il testo della finestra lo decide il browser: `event.returnValue` serve solo ad attivarla.
- Una modifica di sola quantità (`+`/`-`) conta come modifica, come l'aggiunta e la rimozione di una riga.

#### 7.6.6bis.5 — Verifica a mano
1. Cercare un nome comune (per esempio `ba`) e controllare che compaiano 30 risultati, il contatore "30 di N carte" e il pulsante "Carica altre".
2. Premere "Carica altre": i risultati diventano 60 (nessun duplicato, l'ordine continua) e il contatore si aggiorna; all'ultima pagina il pulsante sparisce.
3. Premere "Carica altre" due volte di fila in fretta: parte una sola richiesta (il pulsante è disabilitato durante il caricamento).
4. Cambiare il tipo nel menu a tendina: la lista riparte dalla pagina 1 con i nuovi risultati.
5. Cancellare il testo fino a meno di 2 lettere: la lista si svuota e non ricompare con una risposta in ritardo.
6. Aggiungere una carta alla bozza e provare a chiudere la scheda, ricaricare o cliccare un link: il browser chiede conferma. Premere "Salva modifiche": nessuna richiesta di conferma.
7. Aggiungere una carta e rimuoverla subito: nessun avviso.
8. Modifiche non salvate e "Crea nuova versione": il browser avvisa.

I test automatici di `decks.search-cards` (struttura `data`/`page`/`last_page`/`total`, 30 elementi per pagina, `page=2`, accesso negato a chi non è il proprietario) rientrano in `DeckPagesTest` dello Step 7.9. L'avviso `beforeunload` si verifica solo a mano.

#### 7.6.6bis.6 — Fuori scope
- Il campo `nome` resta obbligatorio (almeno 2 lettere). Per sfogliare le carte **senza nome** (per esempio solo per tipo) servirebbe renderlo opzionale nella validazione e togliere il controllo `query.trim().length < 2` in `search()`: non fa parte di questo step, da decidere a parte.
- Nessun salvataggio automatico della bozza.


### Step 7.6.6bis — Ricerca carte con `<x-cards-filter>`, paginata, e avviso di modifiche non salvate

Ritocca il 7.6.6 già applicato (7.6.6.2 `searchCards` e 7.6.6.3 `decks/edit.blade.php`) in tre punti:
- la ricerca usa il componente `<x-cards-filter>` del catalogo (Step 10.1.5ter) al posto del campo nome e del select dei tipi: i criteri sono quelli di `/carte` (nome, espansioni, tratti, tipi, aspetti, costo, vita, potenza, carte uniche) e si applicano con la stessa `CardSearch`. Cade anche il vincolo del nome obbligatorio: chi cerca "una carta che possa starci nel mazzo" può filtrare solo per tipo, aspetto o costo;
- la ricerca non si ferma più alle prime 20 carte con `limit(20)`: restituisce pagine da 30 e la vista le carica a richiesta;
- il form di modifica avvisa quando si lascia la pagina con modifiche non salvate (la rifinitura `beforeunload` che il 7.6.6.3 lasciava facoltativa).

Nessuna rotta nuova, nessuna migration. Gli step vanno applicati nell'ordine 1 → 5: il 7.6.6bis **sostituisce** il formato della risposta JSON del 7.6.6.2 (da array a oggetto con `data`) e l'intero blocco "Aggiungi carte" di `decks/edit`, quindi `CardSearch`, `cards-filter`, controller e vista vanno cambiati insieme, altrimenti la lista dei risultati resta vuota.

#### 7.6.6bis.1 — `CardSearch`: opzioni dei filtri e chiavi condivise
Oggi `CardController::index` calcola da solo le opzioni del filtro (espansioni, tipi, aspetti, tratti, limiti) e ha l'elenco dei parametri accettati; l'editor dei mazzi ha bisogno delle stesse due cose. Si spostano in `CardSearch` (refactor puro: `/carte` deve restare identica).

In `app/Services/CardSearch.php` aggiungere i `use`
```php
use App\Models\Aspect;
use App\Models\CardTrait;
use App\Models\Expansion;
```
e, nella classe (per esempio subito dopo la dichiarazione), la costante e il metodo:
```php
/**
 * GET parameters accepted by apply(): shared by the /carte page and the deck editor search
 * Parametri GET accettati da apply(): condivisi dalla pagina /carte e dalla ricerca dell'editor mazzi
 */
public const FILTER_KEYS = [
    'nome', 'espansioni', 'tipi', 'aspetti', 'tratti',
    'costo_min', 'costo_max', 'vita_min', 'vita_max', 'potenza_min', 'potenza_max',
    'unique_card',
];

/**
 * Options for the <x-cards-filter> component (expansions, types, aspects, traits) plus the min/max bounds
 * Opzioni per il componente <x-cards-filter> (espansioni, tipi, aspetti, tratti) più i limiti min/max
 */
public function filterOptions(): array
{
    return [
        'bounds' => $this->getBounds(),
        'expansions' => Expansion::orderByDesc('legal_date')
            ->where(function ($q) {
                $q->whereColumn('group_main_expansion', 'expansion')
                    ->orWhereNull('group_main_expansion');
            })
            ->pluck('expansion'),
        'types' => Card::query()->distinct()->orderBy('type')->pluck('type'),
        'aspects' => Aspect::orderBy('order')->get(['id', 'name']),
        'traits' => CardTrait::orderBy('name')->pluck('name'),
    ];
}
```
In `app/Http/Controllers/CardController.php`, `index()` diventa:
```php
public function index(Request $request, CardSearch $search): View
{
    $cards = $search
        ->apply(Card::query()->with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS))
        ->paginate(24)
        ->withQueryString();

    return view('cards.index', [
        'cards' => $cards,
        'filters' => $request->all(),
    ] + $search->filterOptions());
}
```
e si tolgono i `use App\Models\Aspect;`, `use App\Models\CardTrait;` e `use App\Models\Expansion;` che non servono più (`show()` e `newReleases()` non li usano). Il resto del controller non cambia.

#### 7.6.6bis.2 — `cards-filter`: uso incorporato in un'altra pagina
Il componente nasce per `/carte`: è un `<form method="get">` che ricarica la pagina e ha un link "Azzera" verso `cards.index`. Per usarlo nell'editor serve poter (a) intercettare l'invio, (b) aprirlo subito, (c) azzerarlo senza ricaricare. Tutte le modifiche hanno un valore di default che lascia `/carte` com'è.

**a) `resources/views/components/cards-filter.blade.php`** — due prop nuovi in `@props`, dopo `'action'`:
```php
    // Embedded in another page (deck editor): the caller submits the form via attributes and "Azzera" empties it without reloading
    // Incorporato in un'altra pagina (editor mazzi): il form lo invia chi lo usa tramite attributi e "Azzera" lo svuota senza ricaricare
    'embedded' => false,
    // Starting state of the panel: null = open only if a filter is active (behaviour of /carte)
    // Stato iniziale del pannello: null = aperto solo se c'è un filtro attivo (comportamento di /carte)
    'open' => null,
```
Lo stato iniziale del pannello:
```blade
<div x-data="{ open: {{ ($open ?? $hasFilters) ? 'true' : 'false' }} }">
```
Il `<form>` accetta gli attributi di chi usa il componente (qui servirà `x-on:submit.prevent`); la classe di griglia diventa il valore di default:
```blade
<form action="{{ $action }}" method="get"
    {{ $attributes->merge(['class' => 'mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3']) }}>
```
Il link "Azzera" in fondo resta per `/carte`; incorporato diventa un pulsante che svuota il form e avvisa i sotto-componenti (che tengono lo stato in Alpine, quindi `form.reset()` da solo non basta):
```blade
@if ($embedded)
    <button type="button"
        x-on:click="$el.closest('form').reset(); window.dispatchEvent(new CustomEvent('cards-filter-reset'))"
        class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Azzera</button>
@else
    <a href="{{ route('cards.index') }}"
        class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Azzera</a>
@endif
```

**b) `resources/views/components/range-slider.blade.php`** — sul `<div>` radice, dopo l'`x-data` esistente (invariato), un ascoltatore dell'evento di reset:
```blade
<div x-data="{ ...invariato... }" x-on:cards-filter-reset.window="from = min; to = max">
```

**c) `resources/views/components/multi-datalist.blade.php`** — stessa cosa sul `<div>` radice:
```blade
<div x-data="{ ...invariato... }" x-on:cards-filter-reset.window="selected = []; query = ''">
```
Badge dei tipi/aspetti, radio delle carte uniche e campo nome sono input normali: ci pensa `form.reset()`. Su `/carte` l'evento non viene mai emesso (c'è il link), quindi gli ascoltatori restano inerti.

#### 7.6.6bis.3 — `DeckController`: opzioni dei filtri e `searchCards` con filtri e `paginate(30)`
In `app/Http/Controllers/DeckController.php` (i `use` di `CardSearch`, `JsonResponse`, `Rule` ci sono già).

`edit()` passa alla vista le opzioni del filtro; i tipi sono solo quelli che possono stare in un mazzo:
```php
public function edit(string $username, string $deckname, CardSearch $search): View
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);

    $options = $search->filterOptions();
    $options['types'] = collect(self::DECK_CARD_TYPES); // niente segnalini tra i badge del filtro

    return view('decks.edit', ['deck' => $deck] + $options);
}
```
`searchCards()` accetta gli stessi parametri di `/carte`, non richiede più il nome e restituisce pagine da 30:
```php
/**
 * Searches cards to add to the deck, paginated JSON for the deck-building page (owner only)
 * Cerca carte da aggiungere al mazzo, JSON paginato per la pagina di deck-building (solo proprietario)
 */
public function searchCards(Request $request, string $username, string $deckname, CardSearch $search): JsonResponse
{
    $deck = $this->resolveDeck($username, $deckname);
    Gate::authorize('update', $deck);

    $validated = $request->validate([
        'nome' => ['nullable', 'string', 'max:100'],
        'tipi' => ['nullable', 'array'],
        'tipi.*' => ['string', Rule::in(self::DECK_CARD_TYPES)],
        'page' => ['nullable', 'integer', 'min:1'],
    ]);

    $filters = $request->only(CardSearch::FILTER_KEYS);
    $filters['tipi'] = ($validated['tipi'] ?? []) ?: self::DECK_CARD_TYPES; // senza tipo scelto: tutti i tipi da mazzo, mai i segnalini

    $cards = $search->apply(Card::query(), $filters)->paginate(30); // il numero di pagina lo legge da solo da ?page=

    return response()->json([
        'data' => $cards->getCollection()->map(fn (Card $card) => [
            'id' => $card->id,
            'name' => $card->name,
            'title' => $card->title,
            'type' => $card->type,
            'label' => $card->snippet, // "SOR-12 - Nome, TITOLO": l'espansione e il numero distinguono le ristampe
            'image' => $card->front_art_path ? asset('storage/'.$card->front_art_path) : null,
        ])->values(),
        'current_page' => $cards->currentPage(),
        'last_page' => $cards->lastPage(),
        'total' => $cards->total(),
    ]);
}
```
Punti da tenere:
- Gli altri parametri (`espansioni`, `aspetti`, `tratti`, `costo_*`, `vita_*`, `potenza_*`, `unique_card`) non si validano, come in `/carte`: finiscono solo in binding della query. `nome` e `tipi` sì, perché `tipi` impedisce di far uscire i segnalini.
- Con il form vuoto la ricerca elenca tutte le carte da mazzo, 30 per pagina, nell'ordine di default di `CardBuilder` (`paginate()` esegue la query con `get()`, che applica l'ordine; il conteggio del totale non ha l'`ORDER BY`; il tie-breaker su `cards.id` rende stabile il passaggio da una pagina alla successiva).
- Si restituisce un oggetto con solo i campi che servono alla vista, non il paginatore intero. `page` fuori intervallo non è un errore: `data` esce vuoto con `current_page` oltre `last_page`.

#### 7.6.6bis.4 — `decks/edit.blade.php`: filtro, risultati paginati e componente Alpine
Il file diventa questo (sostituisce quello del 7.6.6.3). Differenze rispetto a prima:
- l'`x-data="deckEditor(...)"` passa dal `<form>` a un `<div>` che avvolge tutta la pagina, perché `<x-cards-filter>` contiene un suo `<form>` e **i form non si annidano**: la card "Aggiungi carte" sta fuori dal form di salvataggio ma dentro lo stesso componente Alpine, quindi vede `rows` e `add()`;
- il campo nome e il select dei tipi sono sostituiti da `<x-cards-filter>`, che al "Cerca" non ricarica la pagina (`x-on:submit.prevent`) ma chiama `search()` con il form;
- i risultati sono scorrevoli, con conteggio "N di TOT carte" e pulsante "Carica altre carte" che **aggiunge** la pagina successiva in coda;
- `search()` non guarda più `query` e `type`: prende i criteri da `FormData` del filtro e li tiene in `criteria`, così "Carica altre carte" li riusa identici.
```blade
@php
    $rows = $deck->cards
        ->map(
            fn($c) => [
                'card_id' => $c->id,
                'name' => $c->title ? "{$c->name}, {$c->title}" : $c->name,
                'type' => $c->type,
                'quantity' => (int) $c->pivot->quantity,
            ],
        )
        ->values();
    $editorConfig = [
        'rows' => $rows,
        'format' => $deck->format->value,
        'searchUrl' => route('decks.search-cards', [$deck->user->name, $deck->name]),
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Modifica {{ $deck->name }} <span
                class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="deckEditor(@js($editorConfig))">
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
            @if (!empty(session('import-warnings')))
                <x-bladewind.alert type="warning">
                    Alcune righe dell'import non sono state riconosciute e sono state saltate:
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('import-warnings') as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            {{-- Ricerca: fuori dal form del mazzo, perché <x-cards-filter> è un suo <form> e i form non si annidano --}}
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Aggiungi carte</h3>

                <x-cards-filter embedded :open="true" :aspects="$aspects" :traits="$traits" :types="$types"
                    :expansions="$expansions" :filters="[]" :bounds="$bounds"
                    :action="route('decks.search-cards', [$deck->user->name, $deck->name])"
                    x-on:submit.prevent="search($event.target)" />

                <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600 dark:text-red-400"></p>
                <p x-show="searched && !results.length && !error" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Nessuna carta trovata.</p>
                <ul x-show="results.length"
                    class="mt-3 max-h-96 divide-y divide-gray-200 overflow-y-auto dark:divide-gray-700">
                    <template x-for="card in results" :key="card.id">
                        <li class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                            <span class="flex items-center gap-3">
                                <img x-show="card.image" :src="card.image" :alt="card.name" class="h-10 rounded"
                                    loading="lazy">
                                <span x-text="card.label"></span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="text-gray-500 dark:text-gray-400" x-text="card.type"></span>
                                <button type="button" class="font-medium text-indigo-600 dark:text-indigo-400"
                                    @click="add(card)">Aggiungi</button>
                            </span>
                        </li>
                    </template>
                </ul>
                <div x-show="results.length" class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="`${results.length} di ${total} carte`"></p>
                    <x-secondary-button type="button" x-show="page < lastPage" x-bind:disabled="loading"
                        @click="search()">Carica altre carte</x-secondary-button>
                </div>
            </x-bladewind.card>

            <form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
                class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <p x-show="!rows.length" class="text-sm text-gray-500 dark:text-gray-400">Il mazzo è vuoto: cerca
                        una carta qui sopra.</p>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Riga-carta inline: l'estrazione in <x-deck-card-row> è rimandata al piano 08 (Step 8.3.4) --}}
                        <template x-for="(row, i) in rows" :key="row.card_id">
                            <li
                                class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                <input type="hidden" :name="`cards[${i}][card_id]`" :value="row.card_id">
                                <input type="hidden" :name="`cards[${i}][quantity]`" :value="row.quantity">
                                <span x-text="row.name"></span>
                                <span class="text-gray-500 dark:text-gray-400" x-text="row.type"></span>
                                <span class="flex items-center gap-2">
                                    {{-- Leader e base restano a quantità 1: niente +/- --}}
                                    <template x-if="!['Leader', 'Base'].includes(row.type)">
                                        <span class="flex items-center gap-2">
                                            <button type="button" class="px-2"
                                                @click="row.quantity = Math.max(1, row.quantity - 1)">−</button>
                                            <span x-text="row.quantity"></span>
                                            <button type="button" class="px-2" @click="row.quantity++">+</button>
                                        </span>
                                    </template>
                                    <button type="button" class="text-red-600 dark:text-red-400"
                                        @click="rows.splice(i, 1)">Rimuovi</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </x-bladewind.card>

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>
            </form>

            {{-- Form separati dal principale: i form non si annidano --}}
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('decks.toggle-assembled', [$deck->user->name, $deck->name]) }}">
                    @csrf @method('PATCH')
                    <x-secondary-button
                        type="submit">{{ $deck->assembled ? 'Segna come smontato' : 'Segna come assemblato' }}</x-secondary-button>
                </form>
                <form method="POST" action="{{ route('decks.create-version', [$deck->user->name, $deck->name]) }}">
                    @csrf
                    <x-secondary-button type="submit">Crea nuova versione</x-secondary-button>
                </form>
            </div>
        </div>
    </div>

    {{-- Il layout non ha @stack('scripts'): il componente sta qui. Alpine parte da resources/js/app.js (modulo differito),
         quindi questo listener `alpine:init` viene registrato prima dell'avvio. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('deckEditor', (config) => ({
                rows: config.rows,
                format: config.format,
                criteria: '', // query string dell'ultima ricerca: "Carica altre carte" la riusa con la pagina successiva
                results: [],
                searched: false,
                error: '',
                seq: 0,
                page: 1,
                lastPage: 1,
                total: 0,
                loading: false,

                /**
                 * search(form): nuova ricerca con i criteri del filtro, dalla pagina 1
                 * search():     pagina successiva della ricerca in corso ("Carica altre carte")
                 */
                async search(form = null) {
                    const append = form === null;
                    if (append && (this.loading || this.page >= this.lastPage)) return;
                    if (!append) this.criteria = new URLSearchParams(new FormData(form)).toString();

                    this.error = '';
                    const params = new URLSearchParams(this.criteria);
                    params.set('page', append ? this.page + 1 : 1);
                    const current = ++this.seq; // scarta le risposte arrivate fuori ordine (anche una "Carica altre" ancora in volo)
                    this.loading = true;
                    try {
                        const response = await fetch(`${config.searchUrl}?${params}`, {
                            headers: { Accept: 'application/json' }
                        });
                        if (!response.ok) throw new Error(response.status);
                        const data = await response.json();
                        if (current === this.seq) {
                            this.results = append ? this.results.concat(data.data) : data.data;
                            this.page = data.current_page;
                            this.lastPage = data.last_page;
                            this.total = data.total;
                            this.searched = true;
                        }
                    } catch (e) {
                        if (current === this.seq) this.error = 'Ricerca non riuscita, riprova.';
                    } finally {
                        if (current === this.seq) this.loading = false;
                    }
                },

                add(card) {
                    const single = ['Leader', 'Base'].includes(card.type);
                    const existing = this.rows.find((r) => r.card_id === card.id);
                    if (existing) {
                        if (!single) existing.quantity++;
                        return;
                    }
                    if (single) {
                        const max = card.type === 'Leader' && this.format === 'twin_suns' ? 2 : 1;
                        const same = this.rows.filter((r) => r.type === card.type);
                        if (same.length >= max) {
                            this.rows.splice(this.rows.indexOf(same[0]), 1); // sostituisce il più vecchio
                        }
                    }
                    this.rows.push({
                        card_id: card.id,
                        name: card.title ? `${card.name}, ${card.title}` : card.name,
                        type: card.type,
                        quantity: 1,
                    });
                },
            }));
        });
    </script>
</x-app-layout>
```
Punti da tenere:
- `x-on:submit.prevent` arriva al `<form>` del filtro grazie a `{{ $attributes }}` (7.6.6bis.2). Anche Invio nel campo Nome invia il form (c'è un pulsante "Cerca"), quindi parte `search()` e non il salvataggio del mazzo, che è un altro form.
- Il filtro è un figlio del `<div x-data="deckEditor">`, quindi `search()` si chiama dall'attributo anche se il filtro ha un suo `x-data` (Alpine eredita l'ambito del genitore). I `x-data` di `range-slider` e `multi-datalist` definiscono `min`, `max`, `from`, `to`, `options`, `selected`, `query`: nomi che il componente dell'editor non usa più (`query` e `type` sono spariti).
- Gli slider inviano sempre min e max, anche quando non si sono toccati: `CardSearch` li applica solo se più stretti dei limiti, come in `/carte`.
- Con il form vuoto "Cerca" elenca tutte le carte da mazzo. Finché non si preme "Cerca" la lista dei risultati è vuota e senza messaggio.
- `add(card)` e la gestione di leader e base non cambiano.

#### 7.6.6bis.5 — `beforeunload`: avviso di modifiche non salvate
La bozza del mazzo vive solo nel browser fino a "Salva modifiche" (7.6.6.3): chiudere la scheda, ricaricare o seguire un link la perde. Si confronta lo stato attuale delle righe con quello iniziale e, se è diverso, si chiede conferma prima di lasciare la pagina.

**a) Componente `deckEditor`**: aggiungere i campi
```js
initial: '',
submitting: false,
```
e questi tre membri (ovunque nell'oggetto, per esempio dopo `seq`):
```js
/**
 * Stato delle righe come stringa confrontabile: per ogni carta id e quantità, in ordine di id
 * (l'ordine delle righe non cambia il mazzo salvato)
 */
snapshot() {
    return JSON.stringify(
        this.rows
            .map((r) => [r.card_id, r.quantity])
            .sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : 0))
    );
},

get dirty() {
    return this.snapshot() !== this.initial;
},

init() {
    this.initial = this.snapshot();
    window.addEventListener('beforeunload', (event) => {
        if (this.dirty && !this.submitting) {
            event.preventDefault();
            event.returnValue = ''; // necessario per Chrome; il testo del messaggio lo decide il browser
        }
    });
},
```
`init()` lo chiama Alpine da solo quando il componente parte. `dirty` è un getter: Alpine lo ricalcola quando cambiano le righe, quindi si può usare anche in un `x-show` (vedi sotto).

**b) Il salvataggio non deve far scattare l'avviso**: sul `<form>` di salvataggio delle righe (dopo il 7.6.6bis.4 non ha più l'`x-data`: lo stato arriva dal `<div>` che avvolge la pagina) aggiungere `@submit="submitting = true"`:
```blade
<form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
    @submit="submitting = true" class="space-y-6">
```
Dopo il salvataggio la pagina si ricarica con la bozza ormai salvata, quindi `submitting` riparte da `false` senza bisogno di azzerarlo.

**c) Indicatore visibile (facoltativo, consigliato)**: accanto al pulsante "Salva modifiche" in testa al form, così l'avviso del browser non arriva senza spiegazioni. Il `<div class="flex justify-end">` in testa diventa:
```blade
<div class="flex items-center justify-end gap-3">
    <span x-show="dirty" style="display: none" class="text-sm text-amber-600 dark:text-amber-400">Modifiche non salvate</span>
    <x-primary-button>Salva modifiche</x-primary-button>
</div>
```
`style="display: none"` evita che il testo compaia per un attimo prima che parta Alpine (il progetto non definisce `x-cloak`).

Comportamento da conoscere:
- **I due form separati** in fondo alla pagina ("Segna come assemblato" e "Crea nuova versione") e ogni link fanno scattare l'avviso se ci sono modifiche non salvate. Per "Crea nuova versione" è proprio quello che serve: lo snapshot copia le righe **salvate** del mazzo, non la bozza nel browser.
- I browser mostrano un testo generico (non personalizzabile) e lo mostrano solo dopo che l'utente ha interagito con la pagina.
- Aggiungere e poi togliere la stessa carta, o rimettere la quantità di prima, riporta lo stato uguale a quello iniziale: l'avviso non scatta.

#### 7.6.6bis.6 — Verifica
- **`/carte` com'era** (rischio dei punti 1 e 2): filtri, paginazione e il link "Azzera" funzionano come prima; il pannello "Filtri" si apre solo se c'è un filtro attivo.
- Nell'editor il pannello dei filtri è già aperto e i badge dei tipi sono solo Base, Evento, Leader, Miglioria, Unità (niente segnalini).
- "Cerca" a form vuoto elenca le carte da mazzo: 30 righe, "30 di N carte" e il pulsante "Carica altre carte"; premendolo il numero sale e, all'ultima pagina, il pulsante sparisce.
- Combina criteri (per esempio tipo Unità + un aspetto + costo massimo, e anche il solo nome): i risultati sono quelli che darebbe `/carte` con gli stessi filtri.
- Cambia un filtro e premi "Cerca" dopo aver caricato più pagine: i risultati ripartono dalla prima pagina, nessuna riga della ricerca precedente resta in lista.
- Invio nel campo Nome fa partire la ricerca, senza ricaricare la pagina e senza salvare il mazzo.
- "Azzera" svuota i chip di espansioni e tratti, riporta gli slider ai limiti e badge e radio ai valori iniziali; non ripete la ricerca.
- Aggiungere un leader o una base a un mazzo che ne ha già uno sostituisce il più vecchio, come nel 7.6.6.
- Aggiungi una carta senza salvare e prova a ricaricare, a chiudere la scheda e a cliccare un link del menu: il browser chiede conferma; compare "Modifiche non salvate".
- Aggiungi una carta e premi "Salva modifiche": nessuna richiesta di conferma, l'indicatore non c'è più dopo il ricaricamento.
- Senza modifiche (appena aperta la pagina, o dopo aver rimesso tutto com'era) nessun avviso.
- Nello Step 7.9, in `DeckPagesTest` (ricerca): la ricerca esclude i segnalini anche senza `tipi`; `tipi` fuori elenco dà 422; con più di 30 carte la prima pagina ha 30 elementi e `last_page` 2, e `?page=2` restituisce le restanti; i filtri (per esempio `aspetti`) restringono i risultati; chi non è il proprietario riceve 403.

#### 7.6.6bis.7 — Fuori scope
- **Ricerca live con debounce** (il 7.6.6 cercava a ogni tasto): sostituita dal pulsante "Cerca" del filtro, come in `/carte`.
- "Azzera" nell'editor non ripete la ricerca: svuota solo i criteri.
- I risultati mostrano ancora solo l'etichetta, il tipo e una miniatura; mostrare costo, aspetti e potenza (per esempio con una versione compatta di `<x-card>`) è una rifinitura a parte.
- Il tetto "20 risultati" descritto nelle note del 7.6.6.2 e il vincolo del nome obbligatorio (7.6.6.5) sono superati da questo step.


### Step 7.7 — Export/Import mazzi

#### 7.7.0 — Formati supportati (riferimento: la vecchia SWUDB, `DecksController`)
La sintassi è quella che la vecchia SWUDB già leggeva e scriveva con i file dell'app ufficiale e di swudb.com; non si inventa un formato.

| Formato | Struttura |
|---|---|
| **JSON ufficiale** | `{"metadata": {"name", "author"}, "leader": {"id", "count"}, "secondleader": {...}, "base": {...}, "deck": [{"id", "count"}], "sideboard": [...]}`. L'`id` è `{ESPANSIONE}_{numero}` (es. `SOR_134`): corrisponde a `cards.expansion` + `cards.number`, **non** a `cards.id` (che è `SOR134`, senza underscore). |
| **TXT** | Sezioni `Leaders`, `Base`, `Deck`, `Sideboard` separate da una riga vuota; ogni carta una riga `{copie} \| {nome}` oppure `{copie} \| {nome} \| {titolo}` (i leader hanno il titolo). |
| **URL swudb.com** | `https://swudb.com/deck/{id}` si riscrive in `https://swudb.com/api/deck/{id}`, che risponde con un JSON "nativo" (`deckId`, `leader`, `base`, `shuffledDeck[]` con `card.defaultExpansionAbbreviation`, `card.defaultCardNumber`, `count`, `sideboardCount`) da convertire nel JSON ufficiale. |

Decisioni (tutte discendono da come è fatto il nostro database):
- **I nomi in DB sono in italiano** (l'import carte usa `locale=it`) mentre i file ufficiali del TXT hanno i nomi in inglese: l'**import TXT per nome funziona solo con file esportati da questo sito** (nomi italiani). L'import affidabile è quello **JSON per id**, indipendente dalla lingua; i link swudb.com passano dal JSON. Le righe non riconosciute non fanno fallire l'import: finiscono nell'elenco `notFound` mostrato all'utente (stesso principio fail-soft del job di import carte).
- **La sideboard non esiste nel nostro schema**: viene ignorata e l'utente ne vede il numero di carte scartate.
- La vecchia SWUDB esportava il TXT con una colonna in più (`3 | SOR-134 | Nome`) ma lo leggeva con il formato a due colonne: un suo export non si reimportava. Il nuovo importer riconosce anche quella forma (secondo campo `ESP-numero`) e risolve la carta per espansione e numero.
- `secondleader` (Twin Suns) e il nome della chiave del secondo leader nel JSON nativo di swudb.com (`secondLeader`) **sono da verificare su un export reale** prima di darli per buoni; l'importer li legge in modo tollerante (se mancano, si importa un solo leader e il validator del formato lo segnala).
- Il numero nell'`id` si esporta senza zeri iniziali (come faceva la vecchia SWUDB); in lettura `SOR_005` e `SOR_5` sono la stessa carta.
- Il mazzo importato è sempre un **mazzo nuovo alla versione 1**, del proprietario loggato, col nome scelto nel form (l'unicità `user_id`+`name` si controlla come in `store()`); il formato lo sceglie l'utente.

#### 7.7.1 — `app/Services/DeckExporter.php`
```bash
php artisan make:class Services/DeckExporter
```
Le classi stanno in `app/Services/` (non in un controller) per poterle testare in Pest senza una request HTTP finta.
```php
<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use Illuminate\Support\Collection;

class DeckExporter
{
    /**
     * Plain-text export: sections Leaders / Base / Deck, one "{copies} | {name}[ | {title}]" line per card
     * Export in testo: sezioni Leaders / Base / Deck, una riga "{copie} | {nome}[ | {titolo}]" per carta
     */
    public function toText(Deck $deck): string
    {
        [$leaders, $bases, $main] = $this->split($deck);

        $line = fn (Card $card): string => $card->pivot->quantity.' | '.$card->name.($card->title ? ' | '.$card->title : '');

        $sections = [];
        foreach (['Leaders' => $leaders, 'Base' => $bases, 'Deck' => $main] as $title => $cards) {
            if ($cards->isNotEmpty()) {
                $sections[] = $title."\n".$cards->map($line)->implode("\n");
            }
        }

        return implode("\n\n", $sections)."\n";
    }

    /**
     * JSON export in the official format (ids are "{EXPANSION}_{number}")
     * Export JSON nel formato ufficiale (gli id sono "{ESPANSIONE}_{numero}")
     */
    public function toJson(Deck $deck): string
    {
        [$leaders, $bases, $main] = $this->split($deck);

        $entry = fn (Card $card): array => [
            'id' => "{$card->expansion}_{$card->number}",
            'count' => (int) $card->pivot->quantity,
        ];

        $data = ['metadata' => ['name' => $deck->name, 'author' => $deck->user->name]];

        if ($leaders->isNotEmpty()) {
            $data['leader'] = $entry($leaders[0]);
        }
        if ($leaders->count() > 1) {
            $data['secondleader'] = $entry($leaders[1]); // Twin Suns
        }
        if ($bases->isNotEmpty()) {
            $data['base'] = $entry($bases[0]);
        }
        $data['deck'] = $main->map($entry)->all();

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Leaders, bases and the rest of the deck, the rest sorted by cost then name
     * Leader, basi e resto del mazzo, il resto ordinato per costo e poi per nome
     *
     * @return array{0: Collection<int, Card>, 1: Collection<int, Card>, 2: Collection<int, Card>}
     */
    private function split(Deck $deck): array
    {
        $deck->loadMissing('cards', 'user');

        $cards = $deck->cards->sortBy([['cost', 'asc'], ['name', 'asc']]);

        return [
            $cards->where('type', 'Leader')->values(),
            $cards->where('type', 'Base')->values(),
            $cards->reject(fn (Card $card) => in_array($card->type, ['Leader', 'Base']))->values(),
        ];
    }
}
```
`$deck->user->name` è lo stesso nome pubblico che sta già nell'URL del mazzo. L'export è autorizzato dalla policy `view` nel controller (7.7.3), non qui.

#### 7.7.2 — `app/Services/DeckImporter.php`
```bash
php artisan make:class Services/DeckImporter
```
```php
<?php

namespace App\Services;

use App\Enums\DeckFormat;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeckImporter
{
    private const MAX_BYTES = 1_048_576; // 1 MB: un mazzo pesa pochi KB

    /**
     * Imports a deck from raw content (JSON or TXT, detected from the content itself)
     * Importa un mazzo da un contenuto grezzo (JSON o TXT, rilevato dal contenuto stesso)
     *
     * @return array{deck: ?Deck, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     *         deck = il mazzo creato (null se non è stata riconosciuta nessuna carta), notFound = righe/id non risolti,
     *         sideboardIgnored = copie di sideboard scartate, error = motivo del fallimento (null se deck non è null)
     */
    public function fromContent(string $content, User $owner, string $name, DeckFormat $format, bool $isPublic = false): array
    {
        $data = json_decode($content, true);

        [$rows, $sideboard] = is_array($data) ? $this->parseJson($data) : $this->parseText($content);

        return $this->persist($rows, $sideboard, $owner, $name, $format, $isPublic);
    }

    /**
     * Downloads a deck from an https URL (swudb.com deck links are rewritten to their API) and imports it
     * Scarica un mazzo da un URL https (i link swudb.com si riscrivono verso la loro API) e lo importa
     *
     * @return array{deck: ?Deck, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     */
    public function fromUrl(string $url, User $owner, string $name, DeckFormat $format, bool $isPublic = false): array
    {
        [$body, $error] = $this->download($url);

        if ($body === null) {
            return $this->failure($error);
        }

        return $this->fromContent($body, $owner, $name, $format, $isPublic);
    }

    /**
     * Whether the host resolves to a public address (no private/reserved ranges): the URL is typed by the user,
     * so the server must not be usable to reach internal services (SSRF). Protected so tests can bypass the DNS lookup.
     * Se l'host risolve a un indirizzo pubblico (niente range privati/riservati): l'URL lo scrive l'utente, quindi il server
     * non deve poter raggiungere servizi interni (SSRF). Protected così i test possono saltare la risoluzione DNS.
     */
    protected function isPublicHost(string $host): bool
    {
        $ip = gethostbyname($host); // se non risolve restituisce l'host invariato, che non è un IP valido

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return array{0: ?string, 1: ?string} [body, error]: uno dei due è sempre null
     */
    private function download(string $url): array
    {
        if (preg_match('~^https?://(?:www\.)?swudb\.com/deck/([A-Za-z0-9]+)~', $url, $m)) {
            $url = "https://swudb.com/api/deck/{$m[1]}";
        }

        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return [null, 'Sono accettati solo URL https.'];
        }
        if (! $this->isPublicHost($parts['host'])) {
            return [null, 'Indirizzo non consentito o host non raggiungibile.'];
        }

        try {
            // Nessun redirect: il controllo sull'host varrebbe solo per il primo indirizzo, non per quelli a cui rimanda
            $response = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()
                ->accept('application/json, text/plain')
                ->get($url);
        } catch (ConnectionException) {
            return [null, 'URL non raggiungibile.'];
        }

        if ($response->redirect()) {
            return [null, "L'URL rimanda a un altro indirizzo: incolla direttamente l'indirizzo finale."];
        }
        if ($response->failed()) {
            return [null, "L'URL ha risposto con errore HTTP {$response->status()}."];
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'json') && ! str_contains($contentType, 'text/plain')) {
            return [null, 'L\'URL non restituisce un file di mazzo (atteso JSON o testo, ricevuto "'.($contentType ?: 'sconosciuto').'").'];
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            return [null, 'Il file è troppo grande.'];
        }

        return [$body, null];
    }

    /**
     * @param array<string, mixed> $data JSON decodificato (ufficiale o nativo di swudb.com)
     * @return array{0: list<array{label: string, card: ?Card, count: int}>, 1: int} [righe, copie di sideboard]
     */
    private function parseJson(array $data): array
    {
        if (isset($data['shuffledDeck'])) {
            $data = $this->convertSwudb($data);
        }

        $entries = [];
        foreach (['leader', 'secondleader', 'base'] as $key) {
            if (isset($data[$key]['id'])) {
                $entries[] = $data[$key];
            }
        }
        foreach ((array) ($data['deck'] ?? []) as $entry) {
            $entries[] = $entry;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $rows[] = ['label' => $id, 'card' => $this->findById($id), 'count' => (int) ($entry['count'] ?? 1)];
        }

        return [$rows, (int) collect($data['sideboard'] ?? [])->sum('count')];
    }

    /**
     * @return array{0: list<array{label: string, card: ?Card, count: int}>, 1: int} [righe, copie di sideboard]
     */
    private function parseText(string $text): array
    {
        $rows = [];
        $sideboard = 0;
        $section = null;

        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (in_array($line, ['Leaders', 'Base', 'Deck', 'Sideboard'], true)) {
                $section = $line;

                continue;
            }

            // Riga fuori da una sezione o che non ha la forma "{copie} | {nome}[ | {titolo}]": non si perde in silenzio
            if ($section === null || ! preg_match('/^(\d+)\s*\|\s*([^|]+?)\s*(?:\|\s*(.+))?$/', $line, $m)) {
                $rows[] = ['label' => $line, 'card' => null, 'count' => 1];

                continue;
            }

            $count = (int) $m[1];
            if ($section === 'Sideboard') {
                $sideboard += $count;

                continue;
            }

            $rows[] = ['label' => $line, 'card' => $this->findByText(trim($m[2]), trim($m[3] ?? '')), 'count' => $count];
        }

        return [$rows, $sideboard];
    }

    /**
     * Converts the native swudb.com JSON into the official format
     * Converte il JSON nativo di swudb.com nel formato ufficiale
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function convertSwudb(array $data): array
    {
        $id = fn (array $card): string => data_get($card, 'defaultExpansionAbbreviation', '').'_'.data_get($card, 'defaultCardNumber', '');

        $official = ['deck' => [], 'sideboard' => []];

        foreach (['leader' => 'leader', 'secondLeader' => 'secondleader', 'base' => 'base'] as $from => $to) { // 'secondLeader': da verificare (7.7.0)
            if (isset($data[$from]) && is_array($data[$from])) {
                $official[$to] = ['id' => $id($data[$from]), 'count' => 1];
            }
        }

        foreach ((array) $data['shuffledDeck'] as $entry) {
            $card = (array) ($entry['card'] ?? []);
            if (($entry['count'] ?? 0) > 0) {
                $official['deck'][] = ['id' => $id($card), 'count' => (int) $entry['count']];
            }
            if (($entry['sideboardCount'] ?? 0) > 0) {
                $official['sideboard'][] = ['id' => $id($card), 'count' => (int) $entry['sideboardCount']];
            }
        }

        return $official;
    }

    /**
     * Card from an official id "{EXPANSION}_{number}" (SOR_134, SOR_005 and SOR_5 are the same card)
     * Carta da un id ufficiale "{ESPANSIONE}_{numero}" (SOR_134; SOR_005 e SOR_5 sono la stessa carta)
     */
    private function findById(string $id): ?Card
    {
        if (! preg_match('/^([A-Za-z0-9]+)_(\d+)$/', $id, $m)) {
            return null;
        }

        return Card::where('expansion', strtoupper($m[1]))->where('number', (int) $m[2])->first();
    }

    /**
     * Card from a TXT line: by name and title, or by "EXP-number" for the exports of the old SWUDB
     * Carta da una riga TXT: per nome e titolo, oppure per "ESP-numero" negli export della vecchia SWUDB
     */
    private function findByText(string $name, string $title): ?Card
    {
        if (preg_match('/^([A-Za-z0-9]+)-(\d+)$/', $name, $m)) {
            return Card::where('expansion', strtoupper($m[1]))->where('number', (int) $m[2])->first();
        }

        return Card::query()
            ->where('name', $name)
            ->when(
                $title !== '',
                fn ($q) => $q->where('title', $title),
                fn ($q) => $q->where(fn ($q) => $q->whereNull('title')->orWhere('title', ''))
            )
            ->orderByDesc('release_date') // più ristampe con lo stesso nome: la più recente
            ->first();
    }

    /**
     * @param list<array{label: string, card: ?Card, count: int}> $rows
     * @return array{deck: ?Deck, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     */
    private function persist(array $rows, int $sideboard, User $owner, string $name, DeckFormat $format, bool $isPublic): array
    {
        $quantities = [];
        $notFound = [];

        foreach ($rows as $row) {
            if ($row['card'] === null) {
                $notFound[] = $row['label'];
            } elseif ($row['count'] > 0) {
                $quantities[$row['card']->id] = ($quantities[$row['card']->id] ?? 0) + $row['count'];
            }
        }

        if ($quantities === []) {
            return $this->failure('Nessuna carta riconosciuta nel file.', $notFound, $sideboard);
        }

        $deck = DB::transaction(function () use ($quantities, $owner, $name, $format, $isPublic) {
            $deck = Deck::create([
                'user_id' => $owner->id,
                'name' => $name,
                'format' => $format,
                'is_public' => $isPublic,
                'version' => 1,
            ]);
            $deck->cards()->attach(collect($quantities)->map(fn (int $quantity) => ['quantity' => $quantity])->all());

            return $deck;
        });

        return ['deck' => $deck, 'notFound' => $notFound, 'sideboardIgnored' => $sideboard, 'error' => null];
    }

    /**
     * @param array<int, string> $notFound
     * @return array{deck: null, notFound: array<int, string>, sideboardIgnored: int, error: ?string}
     */
    private function failure(?string $error, array $notFound = [], int $sideboard = 0): array
    {
        return ['deck' => null, 'notFound' => $notFound, 'sideboardIgnored' => $sideboard, 'error' => $error];
    }
}
```
Punti da tenere:
- **Fail-soft**: una carta non trovata finisce in `notFound` e non interrompe l'import; il mazzo non si crea solo se **nessuna** carta è stata riconosciuta.
- Le copie della stessa carta in più righe si sommano (`$quantities[$card->id] += ...`); `attach()` con un array `id => ['quantity' => n]` scrive la pivot `deck_cards` (con i timestamp, come in `createVersion`).
- **I tre casi limite dell'URL** (quelli del bug segnalato in `todo.md` per la vecchia versione) sono ognuno un ramo di `download()`: host non raggiungibile (`ConnectionException`), redirect (`$response->redirect()`), content-type inatteso (HTML al posto di JSON). Tutti restituiscono un messaggio, nessuno lancia eccezioni: vanno coperti dai test dello Step 7.9.
- Rispetto alla vecchia SWUDB: niente `CURLOPT_SSL_VERIFYPEER => false` (la verifica TLS resta attiva), niente redirect seguiti, solo `https`, host pubblici, timeout e dimensione massima. Resta un margine teorico di *DNS rebinding* tra il controllo dell'IP e la richiesta; per un sito con registrazione e verifica email è accettabile.

#### 7.7.3 — Controller, rotte e vista di import

##### Export (`DeckController::export`)
Aggiungere in `DeckController` (`use App\Services\DeckExporter;`, `use Illuminate\Http\Response;`, `use Illuminate\Support\Str;`). Il parametro iniettato va **prima** di quelli della rotta, perché `$version` è opzionale:
```php
/**
 * Downloads the deck as a TXT or JSON file (public if the deck is public, otherwise owner only)
 * Scarica il mazzo come file TXT o JSON (pubblico se il mazzo è pubblico, altrimenti solo proprietario)
 */
public function export(DeckExporter $exporter, string $username, string $deckname, string $format, ?int $version = null): Response
{
    $deck = $this->resolveDeck($username, $deckname, $version);
    Gate::authorize('view', $deck);

    $content = $format === 'json' ? $exporter->toJson($deck) : $exporter->toText($deck);
    $filename = (Str::slug($deck->name) ?: 'mazzo')."-v{$deck->version}.{$format}";

    return response($content, 200, [
        'Content-Type' => $format === 'json' ? 'application/json' : 'text/plain; charset=UTF-8',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
    ]);
}
```
Rotta in `routes/web.php`, nel blocco "Visualizzazione mazzo", **prima** di `decks.show` come `versions` e `statistics` (non confligge: ha più segmenti):
```php
Route::get('/mazzo/{username}/{deckname}/esporta/{format}/{version?}', [DeckController::class, 'export'])
    ->whereIn('format', ['txt', 'json'])->whereNumber('version')->name('decks.export');
```

##### Import (`DeckImportController`)
```bash
php artisan make:controller DeckImportController
```
Un solo controller, un solo form con due sorgenti alternative (file o URL): se c'è un file vince quello. La validazione del nome è la stessa di `DeckController::store()`.
```php
<?php

namespace App\Http\Controllers;

use App\Enums\DeckFormat;
use App\Services\DeckImporter;
use App\Services\DeckValidation\DeckFormatValidatorFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeckImportController extends Controller
{
    public function create(): View
    {
        return view('decks.import');
    }

    /**
     * Imports a deck from an uploaded TXT/JSON file or from an https URL, as a new deck (version 1) of the logged user
     * Importa un mazzo da un file TXT/JSON caricato o da un URL https, come nuovo mazzo (versione 1) dell'utente loggato
     */
    public function store(Request $request, DeckImporter $importer): RedirectResponse
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
            'file' => ['required_without:url', 'nullable', 'file', 'extensions:txt,json', 'max:2048'], // KB
            'url' => ['required_without:file', 'nullable', 'url:https', 'max:2048'],
        ]);

        $owner = $request->user();
        $format = DeckFormat::from($validated['format']);
        $isPublic = $request->boolean('is_public');

        $result = $request->hasFile('file')
            ? $importer->fromContent($request->file('file')->get(), $owner, $validated['name'], $format, $isPublic)
            : $importer->fromUrl($validated['url'], $owner, $validated['name'], $format, $isPublic);

        if ($result['deck'] === null) {
            return back()->withInput()
                ->withErrors(['import' => $result['error'] ?? 'Import non riuscito.'])
                ->with('import-warnings', $result['notFound']);
        }

        $warnings = $result['notFound'];
        if ($result['sideboardIgnored'] > 0) {
            $warnings[] = "Sideboard non supportata: {$result['sideboardIgnored']} carte ignorate.";
        }

        // Informativa, come dopo ogni salvataggio: mostra subito cosa manca per essere legale nel formato scelto
        $errors = DeckFormatValidatorFactory::make($format)->validate($result['deck']->fresh());

        return redirect()->route('decks.edit', [$owner->name, $result['deck']->name])
            ->with('status', 'Mazzo importato.')
            ->with('deck-errors', $errors)
            ->with('import-warnings', $warnings);
    }
}
```
Rotte in `routes/web.php` (aggiungere `use App\Http\Controllers\DeckImportController;`), dentro il gruppo `auth` + `verified` già presente nel prefisso `/mazzi`, accanto a `decks.create`:
```php
Route::get('/importa', [DeckImportController::class, 'create'])->name('decks.import');
Route::post('/importa', [DeckImportController::class, 'store'])->name('decks.import.store');
```
La pagina di arrivo è `decks.edit`, che mostra già `status`, `deck-errors` e `import-warnings` (Step 7.6.6.3): un import parziale (qualche carta non riconosciuta) lo si vede lì, con l'elenco delle righe saltate.

##### Vista `resources/views/decks/import.blade.php`
Scheletro con due schede (file / URL) in Alpine; i campi comuni (nome, formato, pubblico) stanno una volta sola. I campi che non servono nella scheda attiva sono comunque inviati vuoti: il controller dà la precedenza al file e altrimenti usa l'URL. `enctype="multipart/form-data"` è obbligatorio per il file.
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Importa mazzo</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (!empty(session('import-warnings')))
                <x-bladewind.alert type="warning">
                    Righe non riconosciute:
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('import-warnings') as $warning)<li>{{ $warning }}</li>@endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('decks.import.store') }}" enctype="multipart/form-data"
                      x-data="{ tab: '{{ old('url') ? 'url' : 'file' }}' }" class="space-y-6">
                    @csrf

                    <x-input-error :messages="$errors->get('import')" />

                    <div>
                        <x-input-label for="name" value="Nome del mazzo" />
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

                    <div class="flex gap-2">
                        <x-secondary-button type="button" @click="tab = 'file'" ::class="tab === 'file' ? 'ring-2 ring-indigo-500' : ''">Da file</x-secondary-button>
                        <x-secondary-button type="button" @click="tab = 'url'" ::class="tab === 'url' ? 'ring-2 ring-indigo-500' : ''">Da URL</x-secondary-button>
                    </div>

                    <div x-show="tab === 'file'">
                        <x-input-label for="file" value="File del mazzo (TXT o JSON, max 2 MB)" />
                        <input id="file" name="file" type="file" accept=".txt,.json" class="mt-1 block w-full text-sm text-gray-800 dark:text-gray-200">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <div x-show="tab === 'url'">
                        <x-input-label for="url" value="URL del mazzo (https)" />
                        <x-text-input id="url" name="url" type="url" class="mt-1 block w-full" :value="old('url')" placeholder="https://swudb.com/deck/..." />
                        <x-input-error :messages="$errors->get('url')" class="mt-2" />
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="hidden" name="is_public" value="0">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        Mazzo pubblico
                    </label>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Per il JSON si riconosce la carta dall'id (<code>SOR_134</code>), per il TXT dal nome in italiano:
                        i file esportati da questo sito si reimportano senza perdite, quelli con i nomi inglesi vanno importati dal JSON.
                        La sideboard viene ignorata.
                    </p>

                    <div class="flex justify-end"><x-primary-button>Importa</x-primary-button></div>
                </form>
            </x-bladewind.card>
        </div>
    </div>
</x-app-layout>
```
Il campo file e il campo URL stanno entrambi nel form: se l'utente carica un file e ha un URL nel campo, vince il file (comportamento deciso nel controller). Con `<x-input-error>` per `import` si mostrano i fallimenti dell'importer (URL non raggiungibile, redirect, content-type inatteso, nessuna carta riconosciuta).


#### 7.7.4 — Cosa resta fuori
- Export/import della **sideboard** e di eventuali tag: non esistono nello schema (backlog del `todo.md`).
- Import in un mazzo **esistente** (aggiunta/sostituzione): fuori scope, l'import crea sempre un mazzo nuovo.
- L'import dei link di altri siti oltre a swudb.com: non previsto.


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
public function statistics(string $username, string $deckname, ?int $version = null): View
{
    $deck = $this->resolveDeck($username, $deckname, $version);
    Gate::authorize('view', $deck);

    $deck->load(['cards.traits', 'user']);

    // Solo il mazzo vero e proprio: leader e base hanno un ruolo a parte e la base non ha costo
    $main = $deck->cards->reject(fn (Card $card) => in_array($card->type, ['Leader', 'Base']));
    $qty = fn (Card $card): int => (int) $card->pivot->quantity;
    $total = $main->sum($qty);

    // Curva dei costi senza buchi: ogni costo da 0 al massimo, anche con zero carte
    $curve = $main->groupBy(fn (Card $card) => (int) $card->cost)->map(fn ($cards) => $cards->sum($qty));
    $costCurve = collect(range(0, (int) $curve->keys()->max()))
        ->mapWithKeys(fn (int $cost) => [$cost => $curve[$cost] ?? 0]);

    $byType = $main->groupBy('type')->map(fn ($cards) => $cards->sum($qty));

    // Tratti pesati per copie: 3 copie di un'unità con un tratto contano 3
    $traits = [];
    foreach ($main as $card) {
        foreach ($card->traits as $trait) {
            $traits[$trait->name] = ($traits[$trait->name] ?? 0) + $qty($card);
        }
    }
    arsort($traits);

    // Medie su potenza e vita delle unità, pesate per copie; null se il mazzo non ha unità
    $units = $main->where('type', 'Unità');
    $unitCount = $units->sum($qty);
    $avgPower = $unitCount ? $units->sum(fn (Card $card) => (int) $card->power * $qty($card)) / $unitCount : null;
    $avgHealth = $unitCount ? $units->sum(fn (Card $card) => (int) $card->health * $qty($card)) / $unitCount : null;

    return view('decks.statistics', compact('deck', 'total', 'costCurve', 'byType', 'traits', 'avgPower', 'avgHealth'));
}
```
Decisioni (le due aperte del piano 05, chiuse):
- **Tutto pesato per `quantity`**: tratti e medie contano le copie, non le carte distinte (con 3 copie della stessa unità le medie rispecchiano il mazzo reale). Prima solo curva e tipi erano pesati.
- **Leader e base esclusi** da curva, tipi, tratti e medie: le statistiche descrivono il mazzo di 50+ carte; la base non ha costo (finiva in una barra senza etichetta) e il leader ha un costo che falserebbe la curva.
- Il metodo ha la **versione opzionale** come `show`: dalla pagina di una versione precedente le statistiche sono quelle di quella versione.
- L'ipotetica probabilità ipergeometrica resta nel backlog del `todo.md`.

#### 7.8.2 — Rotta
In `routes/web.php`, nel blocco "Visualizzazione mazzo" dello Step 7.6.1, subito dopo la rotta `decks.versions` e **prima** di `decks.show` (pubblica come `show`: l'accesso lo decide la policy `view`). La versione è opzionale e numerica come in `show`:
```php
Route::get('/mazzo/{username}/{deckname}/statistiche/{version?}', [DeckController::class, 'statistics'])
    ->whereNumber('version')->name('decks.statistics');
```
L'ordine finale del blocco "Visualizzazione mazzo" è: `decks.versions`, `decks.statistics`, `decks.export` (Step 7.7.3), `decks.show` per ultima. **Questa rotta va aggiunta per prima di tutto il piano**: finché non esiste, `decks/show.blade.php` dà `RouteNotFoundException` per ogni mazzo.

#### 7.8.3 — Vista `resources/views/decks/statistics.blade.php`
Estende `<x-app-layout>` come ogni altra pagina, titolo nello slot `header`. Unica eccezione al "solo Blade + Alpine": Chart.js via CDN (`cdnjs`, versione fissata), un `<canvas>` per grafico e i dati passati con `@json(...)` dentro lo `<script>` della pagina. Lo `<script>` sta nel corpo della vista, non nel layout (che non ha `@stack('scripts')`): è l'unica pagina che ne ha bisogno. Nessun bundler e nessun componente Vue/React per questo.

Contenuto: tre riquadri numerici (carte nel mazzo, potenza media, vita media delle unità), la curva dei costi e la ripartizione per tipo come grafici a barre, i primi 10 tratti come elenco; per un mazzo senza carte (solo leader e base, o vuoto) un messaggio al posto dei grafici. Il tema dei grafici segue `prefers-color-scheme`, come la classe `dark` che il layout sincronizza su `<html>`. Un link "Torna al mazzo" verso la stessa versione.

Scheletro:
```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Statistiche di {{ $deck->name }}
                <span class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }} · {{ $deck->user->name }}</span>
            </h2>
            <a href="{{ route('decks.show', [$deck->user->name, $deck->name, $deck->version]) }}"><x-secondary-button type="button">Torna al mazzo</x-secondary-button></a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($total === 0)
                <x-bladewind.alert type="info">Il mazzo non ha ancora carte oltre a leader e base: niente da misurare.</x-bladewind.alert>
            @else
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Carte nel mazzo</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $total }}</p>
                    </x-bladewind.card>
                    <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Potenza media unità</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $avgPower !== null ? number_format($avgPower, 1, ',', '') : '—' }}</p>
                    </x-bladewind.card>
                    <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Vita media unità</p>
                        <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $avgHealth !== null ? number_format($avgHealth, 1, ',', '') : '—' }}</p>
                    </x-bladewind.card>
                </div>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Curva dei costi</h3>
                    <canvas id="cost-curve" height="120"></canvas>
                </x-bladewind.card>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Tipi di carta</h3>
                    <canvas id="by-type" height="120"></canvas>
                </x-bladewind.card>

                @if (!empty($traits))
                    <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Tratti più presenti</h3>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach (array_slice($traits, 0, 10, true) as $name => $count)
                                <li class="flex items-center justify-between py-2 text-sm text-gray-800 dark:text-gray-100">
                                    <span>{{ $name }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">×{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-bladewind.card>
                @endif

                <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
                <script>
                    const dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    Chart.defaults.color = dark ? '#d1d5db' : '#374151';
                    const grid = dark ? '#374151' : '#e5e7eb';
                    const options = {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { grid: { color: grid } },
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: grid } },
                        },
                    };

                    new Chart(document.getElementById('cost-curve'), {
                        type: 'bar',
                        data: { labels: @json($costCurve->keys()), datasets: [{ data: @json($costCurve->values()), backgroundColor: '#6366f1' }] },
                        options,
                    });
                    new Chart(document.getElementById('by-type'), {
                        type: 'bar',
                        data: { labels: @json($byType->keys()), datasets: [{ data: @json($byType->values()), backgroundColor: '#6366f1' }] },
                        options,
                    });
                </script>
            @endif
        </div>
    </div>
</x-app-layout>
```
I grafici sono barre verticali in entrambi i casi (con l'asse orizzontale invertito `beginAtZero` andrebbe spostato sull'asse x). Da guardare a occhio nel tema chiaro e scuro: i colori delle barre sono neutri e non seguono la palette degli aspetti.

#### 7.8.4 — Link dalla pagina del mazzo
In `resources/views/decks/show.blade.php` (Step 7.6.3) il link "Statistiche" c'è già, vicino a "Cronologia versioni", ma senza la versione: si aggiorna con `route('decks.statistics', [$deck->user->name, $deck->name, $deck->version])` come in 7.6.5.3 (dalla pagina di una versione precedente porta alle statistiche di quella versione).


### Step 7.9 — Test Pest

Nessun test sui mazzi esiste ancora (restano aperti anche quelli dei validator, piano 06, Step 7.5bis). Si seguono le convenzioni di `tests/Feature/Services/CardSearchTest.php`: `RefreshDatabase`, `Expansion::create` e `Card::create` a mano (non ci sono factory per `Card`, `Deck` ed `Expansion`; c'è solo `UserFactory`).

#### 7.9.1 — Helper condiviso
In `tests/Pest.php`, in fondo, per non ripetere la creazione di carte in ogni file:
```php
use App\Models\Card;
use App\Models\Deck;
use App\Models\Expansion;
use App\Models\User;

/**
 * Creates a card, creating its expansion if needed
 * Crea una carta, creando l'espansione se serve
 */
function makeCard(array $attributes = []): Card
{
    static $number = 0;
    $expansion = $attributes['expansion'] ?? 'SOR';
    Expansion::firstOrCreate(['expansion' => $expansion], ['legal_date' => '2024-03-08', 'rotation' => '1']);

    $number++;

    return Card::create($attributes + [
        'expansion' => $expansion,
        'number' => $number,
        'cid' => "cid-{$expansion}-{$number}",
        'name' => "Carta {$number}",
        'type' => 'Unità',
        'rarity' => 'Comune',
        'cost' => 2,
        'unique_card' => false,
    ]);
}

/**
 * Creates a deck for the user with the given cards as [card => quantity] pairs
 * Crea un mazzo per l'utente con le carte indicate come coppie [carta => quantità]
 */
function makeDeck(User $user, array $cards = [], array $attributes = []): Deck
{
    $deck = Deck::create($attributes + ['user_id' => $user->id, 'name' => 'Mazzo di prova', 'format' => 'premier', 'is_public' => true, 'version' => 1]);
    foreach ($cards as [$card, $quantity]) {
        $deck->cards()->attach($card->id, ['quantity' => $quantity]);
    }

    return $deck;
}
```
(`makeDeck` prende le carte come lista di coppie `[$card, $quantity]`, non come array associativo: un oggetto non può essere chiave di array.)

#### 7.9.2 — `tests/Feature/Services/DeckExporterTest.php`
- `toText`: un mazzo con un leader (con titolo), una base e due carte produce le sezioni `Leaders`, `Base`, `Deck` nell'ordine, righe `1 | Nome | TITOLO` e `3 | Nome`, separate da una riga vuota e senza sezioni vuote.
- `toJson`: `leader`, `base`, `deck` con `id` come `SOR_134` (espansione, underscore, numero **senza** zeri) e `count`; un mazzo con due leader produce anche `secondleader`; `metadata.author` è il nome utente.
- Round-trip: `DeckImporter::fromContent($exporter->toJson($deck), ...)` ricrea le stesse carte con le stesse quantità; lo stesso per `toText`.

#### 7.9.3 — `tests/Feature/Services/DeckImporterTest.php`
- JSON ufficiale: carte risolte per `expansion`+`number` (anche `SOR_005` = `SOR_5`); un id inesistente va in `notFound` e il mazzo si crea comunque.
- TXT: nome + titolo, carta senza titolo, righe fuori sezione e righe malformate in `notFound`, `Sideboard` contata in `sideboardIgnored` e non importata, forma vecchia SWUDB `3 | SOR-134 | Nome`.
- Nessuna carta riconosciuta: `deck` nullo, `error` valorizzato, **nessun** mazzo creato (`Deck::count()` invariato).
- Righe duplicate della stessa carta: le quantità si sommano.
- JSON nativo swudb.com (`shuffledDeck`): convertito in `deck` e `sideboard`.
- **I tre casi limite dell'URL** con `Http::fake`. Per saltare la risoluzione DNS (nei test non c'è rete) si usa una sottoclasse anonima:
```php
use App\Enums\DeckFormat;
use App\Services\DeckImporter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function publicImporter(): DeckImporter
{
    return new class extends DeckImporter
    {
        protected function isPublicHost(string $host): bool
        {
            return true;
        }
    };
}

test('an unreachable url fails without creating a deck', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('non raggiungibile')
        ->and(Deck::count())->toBe(0);
});

test('a redirect is not followed', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://example.org/altro'])]);

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('rimanda');
});

test('an html response is rejected before decoding', function () {
    Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

    $result = publicImporter()->fromUrl('https://example.com/mazzo.json', User::factory()->create(), 'Prova', DeckFormat::Premier);

    expect($result['deck'])->toBeNull()->and($result['error'])->toContain('text/html');
});
```
Più: URL `http://` rifiutato, link `https://swudb.com/deck/ABC123` riscritto in `https://swudb.com/api/deck/ABC123` (`Http::assertSent`), risposta oltre 1 MB rifiutata, e senza la sottoclasse un host che non risolve (`https://host-che-non-esiste.invalid`) è rifiutato dal controllo sull'host.

#### 7.9.4 — `tests/Feature/Decks/DeckPagesTest.php`
- `GET /mazzi`: un mazzo con tre versioni compare **una volta sola** (la v3); un mazzo privato di un altro utente non compare per un ospite; **un mazzo senza base e senza leader non fa errore** (regressione di 7.6.5.1).
- `GET /mazzo/{user}/{deck}`: 200 per un mazzo pubblico a un ospite, 403 per un privato a un ospite e a un altro utente; `/mazzo/{user}/{deck}/1` mostra la v1 con l'avviso di sola lettura quando esiste la v2; la pagina non dà `RouteNotFoundException` (regressione `decks.statistics`).
- `GET /mazzo/modifica/...`: 403 per un altro utente, 200 per il proprietario; `PUT .../carte` sostituisce le carte e stacca quelle non inviate; `POST .../carte` **somma** la quantità (2 + 1 = 3); `POST .../versione` crea la v2 con le stesse carte, `previous_version_id` e la stessa catena in `decks.versions`; una seconda versione con lo stesso nome non viola l'unicità.
- `GET .../cerca-carte?nome=...`: 403 per un altro utente, 422 con meno di 2 lettere, restituisce solo i tipi ammessi (nessun segnalino) e al massimo 20 risultati.
- Export: `GET .../esporta/json/1` risponde con `Content-Disposition: attachment` e il JSON atteso, 403 per un mazzo privato a un ospite, 404 per un formato diverso da `txt`/`json`.
- Import: `POST /mazzi/importa` con un file JSON crea il mazzo e reindirizza a `decks.edit` con `import-warnings`; con un nome già usato dà un errore di validazione; da ospite reindirizza al login.

#### 7.9.5 — `tests/Feature/Decks/DeckStatisticsTest.php`
Mazzo con 3 copie di un'unità con potenza 4 e vita 5 e 1 copia di un'unità con potenza 2 e vita 1: `$avgPower` = (3·4 + 1·2) / 4 = **3,5** e `$avgHealth` = (3·5 + 1·1) / 4 = **4** (pesate per copie). Leader e base non entrano in `$total` né nella curva. La curva non ha buchi (costi 0..max). `$traits` ordinati per conteggio decrescente. Pagina 200 anche per un mazzo senza carte (messaggio al posto dei grafici). Si leggono i dati dalla vista con `$response->viewData('avgPower')`.

Si lanciano da host con `composer test` (vedi README, "Test").
