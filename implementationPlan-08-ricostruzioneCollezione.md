# Implementation plan 08 — Ricostruzione UnlimitedDB · Fase 8: collezione e carte mancanti

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisiti**: [`implementationPlan-V-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-V-02-ricostruzioneAllineamentoSchema.md) (colonne `card_id`),
> [`implementationPlan-V-06-ricostruzioneMazziDominio.md`](implementationPlan-V-06-ricostruzioneMazziDominio.md) (relazioni dei mazzi) e, per la ricerca,
> [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`).
>
> Stato del codice (verificato il 2026-10-17): i file degli Step 8.1, 8.2 e 8.3.1 esistono (`CollectionCard`, `CollectionController`, `collection/index.blade.php`, `DeckGapCalculator`, `Card::collectionCards()`),
> e sono già registrate le rotte `collection.*` e `decks.gap`. Aggiornamento del 2026-10-17 (riletto il codice): Step 8.1, 8.3.1, 8.3.2, 8.3.3 e 8.3.4 applicati e coerenti con il piano (rotta, metodo `gap` con `resolveDeck` e `$summary`, componente `<x-deck-card-row>`, vista `decks/gap`, link in `edit` e `show`); manca la verifica a mano 8.3.4.4. **Lo Step 8.2 non è finito**: la vista `collection/index` è ancora la copia di `cards/index` e il controller non passa le opzioni del filtro (vedi 8.2.2 e 8.2.3). Per questo il piano non è ancora rinominato `V-`.
> Lo Step 8.3.4 ha creato `decks/gap.blade.php` e il componente `<x-deck-card-row>` (prima il file era vuoto e il componente non esisteva).
> Il toggle "carte mancanti / carte presenti" richiesto per la pagina di deck-building non era descritto da nessuna parte nel vecchio piano (rimandava a una "decisione più sopra" che non esisteva): è lo Step 8.3.4.

## Fase 8 — Gestione collezione

### Step 8.1 — Modello e migration

#### 8.1.1 — Generare
```
php artisan make:model CollectionCard -m
```

#### 8.1.2 — Migration
```php
Schema::create('collection_cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('card_id', 20);
    $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
    $table->enum('variant', ['normal', 'foil', 'hyper', 'prestige', 'hyper_foil'])->default('normal');
    $table->unsignedSmallInteger('quantity');
    $table->timestamps();

    $table->unique(['user_id', 'card_id', 'variant']);
});
```
Le varianti di stampa vivono **solo qui**, non nei mazzi.

#### 8.1.3 — Modello `app/Models/CollectionCard.php`
```bash
php artisan make:model CollectionCard
```
```php
class CollectionCard extends Model
{
    protected $fillable = ['user_id', 'card_id', 'variant', 'quantity'];

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

#### 8.1.4 — Relazione inversa su `Card`
In `app/Models/Card.php` (serve all'eager load dello Step 8.2):
```php
public function collectionCards()
{
    return $this->hasMany(CollectionCard::class, 'card_id');
}
```

### Step 8.2 — Pagina `/collezione`

#### 8.2.1 — Rotte
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/collezione', [CollectionController::class, 'index'])->name('collection.index');
    Route::patch('/collezione', [CollectionController::class, 'update'])->name('collection.update');
});
```

#### 8.2.2 — Controller
```
php artisan make:controller CollectionController
```
```php
class CollectionController extends Controller
{
    public function index(Request $request, CardSearch $search): View
    {
        // stessi filtri e stesse opzioni di /carte (CardController::index): il form <x-cards-filter> li vuole tutti
        $cards = $search
            ->apply(Card::query()->with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS))
            ->with(['collectionCards' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->paginate(24)->withQueryString();

        return view('collection.index', [
            'cards' => $cards,
            'filters' => $request->all(),
        ] + $search->filterOptions());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'variant' => ['required', 'in:normal,foil,hyper,prestige,hyper_foil'],
            'quantity' => ['required', 'integer', 'min:0', 'max:65535'], // la colonna è unsignedSmallInteger
        ]);

        $key = ['user_id' => $request->user()->id, 'card_id' => $validated['card_id'], 'variant' => $validated['variant']];

        if ((int) $validated['quantity'] === 0) {
            CollectionCard::where($key)->delete(); // niente righe a zero: la somma per card_id resta corretta senza filtrarle ovunque
        } else {
            CollectionCard::updateOrCreate($key, ['quantity' => $validated['quantity']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
```

#### 8.2.3 — Vista `resources/views/collection/index.blade.php`
> **Stato (2026-10-17): da completare.** Il codice applicato ha la vista copiata da `cards/index` (titolo "Carte", nessun controllo quantità, nessuno script) e il controller passa solo `$cards`,
> mentre la vista usa `$aspects`, `$traits`, `$types`, `$expansions`, `$filters`, `$bounds`: la pagina dà `Undefined variable`. In più il controller filtrava con le vecchie chiavi `nome/espansione/tipo`, non più quelle di `CardSearch::FILTER_KEYS`.
> Il controller qui sopra è già corretto; resta la vista.

Titolo nello slot `header` ("La mia collezione"). Stessa griglia di `cards/index.blade.php` (Step 10.1, riusa `CardSearch`), con sotto ogni carta un controllo quantità per variante (`normal`, `foil`, `hyper`, `prestige`, `hyper_foil`), precompilato da `$card->collectionCards->pluck('quantity', 'variant')`.
L'aggiornamento senza reload è l'unico punto del progetto con JS oltre ad Alpine e Chart.js: uno `<script>` inline che intercetta il cambio di un `<input type="number">` e chiama
`fetch("{{ route('collection.update') }}", {method: 'PATCH', ...})` con header `X-CSRF-TOKEN` (il meta `csrf-token` è già nel layout) e `Accept: application/json`. Niente bundler.
```blade
@php
    $variants = [
        'normal' => 'Normale',
        'foil' => 'Foil',
        'hyper' => 'Hyperspace',
        'hyper_foil' => 'Hyperspace foil',
        'prestige' => 'Prestige',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">La mia collezione</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-6">
        <x-cards-filter :aspects="$aspects" :traits="$traits" :types="$types" :expansions="$expansions"
            :filters="$filters" :bounds="$bounds" :action="route('collection.index')" />
    </div>

    <x-flash-message />

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-4 px-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            @php($owned = $card->collectionCards->pluck('quantity', 'variant'))
            <div class="space-y-2">
                <x-card :card="$card" />
                <div class="grid grid-cols-5 gap-2 rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
                    @foreach ($variants as $key => $label)
                        <label class="text-center text-xs text-gray-500 dark:text-gray-400">
                            {{ $label }}
                            <input type="number" min="0" max="65535" value="{{ $owned[$key] ?? 0 }}"
                                data-collection data-card="{{ $card->id }}" data-variant="{{ $key }}"
                                class="mt-1 w-full rounded border-gray-300 text-center text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if ($cards->isEmpty())
            <p class="col-span-full text-center text-gray-800 dark:text-gray-200">Non ho trovato carte che soddisfino
                tutti i criteri di ricerca.</p>
        @endif
    </div>

    <div class="mx-auto max-w-7xl px-4 py-6">
        {{ $cards->links() }}
    </div>

    <script>
        document.addEventListener('change', async (event) => {
            const input = event.target.closest('input[data-collection]');
            if (!input) return;
            const quantity = Math.max(0, parseInt(input.value, 10) || 0);
            input.value = quantity;
            input.classList.remove('border-red-500');
            try {
                const response = await fetch("{{ route('collection.update') }}", {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        card_id: input.dataset.card,
                        variant: input.dataset.variant,
                        quantity,
                    }),
                });
                if (!response.ok) throw new Error(response.status);
            } catch (e) {
                input.classList.add('border-red-500'); // salvataggio non riuscito: il valore a schermo non è quello nel database
            }
        });
    </script>
</x-app-layout>
```
Verifica: `/collezione` si apre senza errori e con i filtri; cambiare una quantità e ricaricare la pagina la conserva; portarla a 0 elimina la riga in `collection_cards`; un valore non valido (negativo, > 65535) non rompe nulla; il confronto in `/mazzo/.../delta` cambia di conseguenza.

### Step 8.3 — "Carte mancanti per un mazzo"
Tre informazioni per ogni carta del mazzo: mancante del tutto, posseduta ma impegnata in un altro mazzo assemblato, disponibile.

#### 8.3.1 — `app/Services/DeckGapCalculator.php`
```bash
php artisan make:class Services/DeckGapCalculator
```
Rispetto alla prima bozza: il calcolo è **per l'utente che guarda** (non per il proprietario del mazzo, altrimenti guardando un mazzo pubblico altrui si vedrebbe la collezione del proprietario)
e restituisce anche le carte disponibili (`present`), necessarie al toggle dello Step 8.3.4.
Leader e base contano come le altre carte: sono righe di `deck_cards` e servono per giocare il mazzo.
```php
class DeckGapCalculator
{
    /**
     * @return array{missing: array<string,int>, reservedElsewhere: array<string,int>, present: array<string,int>}
     */
    public function forDeck(Deck $deck, User $user): array
    {
        $required = $deck->cards->mapWithKeys(fn ($c) => [$c->id => $c->pivot->quantity]);

        $owned = CollectionCard::where('user_id', $user->id)
            ->selectRaw('card_id, SUM(quantity) as qty')->groupBy('card_id')->pluck('qty', 'card_id');

        // copie già impegnate nei mazzi assemblati dell'utente, escluso il mazzo che si sta guardando
        $reserved = DeckCard::whereHas('deck', fn ($q) => $q->where('user_id', $user->id)
                ->where('assembled', true)->where('id', '!=', $deck->id))
            ->selectRaw('card_id, SUM(quantity) as qty')->groupBy('card_id')->pluck('qty', 'card_id');

        $missing = [];
        $reservedElsewhere = [];
        $present = [];

        foreach ($required as $cardId => $needed) {
            $ownedQty = (int) ($owned[$cardId] ?? 0);
            $reservedQty = (int) ($reserved[$cardId] ?? 0);
            $freelyAvailable = max(0, $ownedQty - $reservedQty);

            if (($presentQty = min($needed, $freelyAvailable)) > 0) {
                $present[$cardId] = $presentQty;
            }
            if ($freelyAvailable >= $needed) {
                continue;
            }

            $shortfall = $needed - $freelyAvailable;
            $reservedContribution = min($shortfall, $reservedQty);

            if ($reservedContribution > 0) {
                $reservedElsewhere[$cardId] = $reservedContribution;
            }
            if (($trulyMissing = $shortfall - $reservedContribution) > 0) {
                $missing[$cardId] = $trulyMissing;
            }
        }

        return compact('missing', 'reservedElsewhere', 'present');
    }
}
```

#### 8.3.2 — Rotta (✅ applicata il 2026-10-17)
> **Storico, bug risolto**: la rotta era stata messa dentro il gruppo `mazzo/modifica/{username}/{deckname}`, quindi l'URL reale è
> `/mazzo/modifica/{username}/{deckname}/mazzi/{deck}/delta`, con due parametri in più (`{username}`, `{deckname}`) oltre a `{deck}`, e per di più sotto il prefisso del deck-building (solo proprietario).
> Inoltre `{deck}` come id numerico non segue la convenzione del resto del progetto (utente + nome + versione nell'URL, decisione dello Step 7.6.4).

La pagina serve a chiunque possa **vedere** il mazzo (anche pubblico di un altro utente), quindi sta fuori dal gruppo `modifica` e usa lo schema delle altre rotte di lettura (`versioni`, `esporta`, `statistiche`).

1. In `routes/web.php`, **togliere** dal gruppo `mazzo/modifica/{username}/{deckname}` la riga:
   ```php
   Route::get('/mazzi/{deck}/delta', [DeckController::class, 'gap'])->name('decks.gap');
   ```
2. Aggiungere `decks.gap` nel gruppo `auth` + `verified` in fondo al file, accanto a `/collezione` (la collezione è personale, serve il login):
   ```php
   Route::middleware(['auth', 'verified'])->group(function () {
       Route::get('/collezione', [CollectionController::class, 'index'])->name('collection.index');
       Route::patch('/collezione', [CollectionController::class, 'update'])->name('collection.update');

       Route::get('/mazzo/{username}/{deckname}/delta/{version?}', [DeckController::class, 'gap'])
           ->whereNumber('version')->name('decks.gap');
   });
   ```
   Nessun conflitto con `decks.show` (`/mazzo/{username}/{deckname}/{version?}`, `whereNumber`): `.../delta` non è numerico, quindi `show` non lo prende; con la versione (`.../delta/2`) i segmenti sono di più di quelli di `show`.
3. Verifica: `php artisan route:list --name=decks.gap` mostra una sola rotta, `mazzo/{username}/{deckname}/delta/{version?}`.

#### 8.3.3 — Metodo `gap` del controller (✅ applicato il 2026-10-17)
> **Storico, bug risolti**: (1) `$this->authorize('view', $deck)` non esiste, `Controller` di Laravel 12 non ha il trait `AuthorizesRequests` (gli altri metodi usano `Gate::authorize`): la pagina darebbe `BadMethodCallException`;
> (2) il mazzo arriva dal route model binding per id: va risolto con `resolveDeck($username, $deckname, $version)` come negli altri metodi. In più il metodo ora passa alla vista un riepilogo (`$summary`) con i totali delle copie.

Sostituire il metodo `gap` di `app/Http/Controllers/DeckController.php` (gli `use` di `Card`, `Deck`, `DeckGapCalculator`, `Gate`, `Request`, `View` ci sono già):
```php
/**
 * Carte mancanti / presenti del mazzo rispetto alla collezione di chi guarda (non del proprietario)
 */
public function gap(Request $request, DeckGapCalculator $calculator, string $username, string $deckname, ?int $version = null): View
{
    $deck = $this->resolveDeck($username, $deckname, $version);
    Gate::authorize('view', $deck);

    $mode = $request->query('mostra') === 'presenti' ? 'presenti' : 'mancanti'; // default: mancanti; qualunque altro valore vale "mancanti"
    $gap = $calculator->forDeck($deck, $request->user());

    // totali in copie, sempre mostrati sopra le liste qualunque sia la modalità
    $summary = [
        'needed' => (int) $deck->cards->sum('pivot.quantity'),
        'present' => array_sum($gap['present']),
        'reserved' => array_sum($gap['reservedElsewhere']),
        'missing' => array_sum($gap['missing']),
    ];

    $quantities = $mode === 'presenti'
        ? ['present' => $gap['present']]
        : ['missing' => $gap['missing'], 'reserved' => $gap['reservedElsewhere']];

    // una sola query; `get()` applica l'ordinamento di default di CardBuilder (Leader, Base, aspetti, tipo, costo...) e `keyBy` lo conserva
    $cards = Card::whereIn('id', collect($quantities)->flatMap(fn ($q) => array_keys($q))->unique()->values())
        ->get()->keyBy('id');

    return view('decks.gap', compact('deck', 'mode', 'quantities', 'cards', 'summary'));
}
```

#### 8.3.4 — Vista con toggle (`resources/views/decks/gap.blade.php`) (✅ applicato il 2026-10-17, verifica a mano 8.3.4.4 da fare)

Questo step ha sovrascritto il file vuoto e creato anche il componente della riga-carta e i link di accesso alla pagina.
Scostamento rispetto alla prima versione dello step: il "componente estratto per `decks/edit.blade.php`" non esiste (la riga di `edit` è inline e pilotata da Alpine, con quantità modificabili), quindi `<x-deck-card-row>` è un componente **nuovo e di sola lettura**; `edit` non si tocca (decisione 7.6.5.5 del `todo.md`).

Cosa fa la pagina:
- Titolo nello slot `header` ("Carte per {{ $deck->name }}", con versione e proprietario) e due link: "Torna al mazzo" e "La mia collezione".
- Riepilogo in copie (sempre visibile): richieste dal mazzo, presenti, impegnate altrove, mancanti.
- **Toggle mancanti / presenti**: due link GET affiancati (`?mostra=mancanti` e `?mostra=presenti`, segmented control Tailwind), quello attivo evidenziato in base a `$mode`. Sono link, quindi la scelta resta nell'URL e si può condividere.
- Modalità `mancanti`: due sezioni, "Mancanti del tutto" (`$quantities['missing']`, badge rosso) e "Impegnate in altri mazzi assemblati" (`$quantities['reserved']`, badge ambra).
- Modalità `presenti`: una sola sezione con `$quantities['present']`, cioè le copie possedute e libere (badge verde).
- Le righe seguono l'ordine di `$cards` (ordinamento di default delle carte), non quello di inserimento delle quantità. Se una sezione è vuota compare un messaggio al posto della lista.
- I badge usano `<x-badge :color="...">` (Step 5.4.5 di [`implementationPlan-V-03-ricostruzioneAdminErrori.md`](implementationPlan-V-03-ricostruzioneAdminErrori.md)).

##### 8.3.4.1 — Componente `resources/views/components/deck-card-row.blade.php`
Riga di sola lettura: miniatura (se la carta ha l'immagine, altrimenti niente: `front_art_path` può essere nullo), `snippet` come link alla scheda, tipo, quantità e uno slot per il badge.
```blade
{{-- resources/views/components/deck-card-row.blade.php --}}
@props(['card', 'quantity'])
<li {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100']) }}>
    <a href="{{ route('cards.show', [$card->expansion, $card->number]) }}"
        class="flex min-w-0 flex-1 items-center gap-3 hover:underline">
        @if ($card->front_art_path)
            <img src="{{ asset('storage/' . $card->front_art_path) }}" alt="{{ $card->name }}"
                class="h-10 shrink-0 rounded shadow-xs" loading="lazy">
        @endif
        <span class="truncate font-medium">{{ $card->snippet }}</span>
    </a>
    <span class="flex shrink-0 items-center gap-3">
        <span class="hidden w-24 text-right text-gray-500 dark:text-gray-400 sm:inline">{{ $card->type }}</span>
        <span class="w-10 text-right font-semibold">×{{ $quantity }}</span>
        {{ $slot }}
    </span>
</li>
```

##### 8.3.4.2 — Vista `resources/views/decks/gap.blade.php`
```blade
@php
    // chiavi di $quantities, titolo, colore del badge ed etichetta: una sezione per ogni lista mostrata
    $sections =
        $mode === 'presenti'
            ? [
                [
                    'key' => 'present',
                    'title' => 'Copie possedute e libere',
                    'color' => '#16a34a',
                    'label' => 'Presente',
                    'empty' => 'Nessuna carta del mazzo è tra le copie libere della tua collezione.',
                ],
            ]
            : [
                [
                    'key' => 'missing',
                    'title' => 'Mancanti del tutto',
                    'color' => '#dc2626',
                    'label' => 'Mancante',
                    'empty' => 'Non ti manca nessuna carta: hai tutto quello che serve.',
                ],
                [
                    'key' => 'reserved',
                    'title' => 'Impegnate in altri mazzi assemblati',
                    'color' => '#d97706',
                    'label' => 'In un altro mazzo',
                    'empty' => 'Nessuna carta di questo mazzo è impegnata in un altro mazzo assemblato.',
                ],
            ];

    $tabs = ['mancanti' => 'Carte mancanti', 'presenti' => 'Carte presenti'];

    $tiles = [
        'Richieste dal mazzo' => $summary['needed'],
        'Presenti' => $summary['present'],
        'Impegnate altrove' => $summary['reserved'],
        'Mancanti' => $summary['missing'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Carte per {{ $deck->name }} <span class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }}
                    · {{ $deck->user->name }}</span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('decks.show', [$deck->user->name, $deck->name, $deck->version]) }}">
                    <x-secondary-button type="button">Torna al mazzo</x-secondary-button>
                </a>
                <a href="{{ route('collection.index') }}">
                    <x-secondary-button type="button">La mia collezione</x-secondary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Il confronto usa la <strong>tua</strong> collezione, anche se il mazzo è di un altro utente.
            </p>

            {{-- Riepilogo in copie --}}
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($tiles as $label => $value)
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Toggle mancanti / presenti: due link GET, la scelta resta nell'URL --}}
            <nav aria-label="Modalità"
                class="inline-flex overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('decks.gap', ['username' => $deck->user->name, 'deckname' => $deck->name, 'version' => $deck->version, 'mostra' => $key]) }}"
                        @if ($mode === $key) aria-current="page" @endif
                        class="px-4 py-2 text-sm font-medium transition-colors {{ $mode === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }} {{ $loop->first ? '' : 'border-l border-gray-200 dark:border-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @foreach ($sections as $section)
                {{-- carte della sezione, nell'ordine di default di $cards --}}
                @php($rows = $cards->filter(fn($card) => isset($quantities[$section['key']][$card->id])))
                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $section['title'] }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $rows->count() }} carte · {{ array_sum($quantities[$section['key']]) }} copie
                        </span>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $section['empty'] }}</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($rows as $card)
                                <x-deck-card-row :card="$card" :quantity="$quantities[$section['key']][$card->id]">
                                    <x-badge :color="$section['color']">{{ $section['label'] }}</x-badge>
                                </x-deck-card-row>
                            @endforeach
                        </ul>
                    @endif
                </x-bladewind.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
```
Note:
- Niente anteprima al passaggio del cursore (quella di `show`/`edit` dipende da `deckCardPreview()`, definito inline in `show.blade.php`): la riga porta alla scheda della carta. Se serve, estrarre `deckCardPreview()` in un partial condiviso è un lavoro a parte.
- `<x-badge>` oggi contiene un `\Log::debug(...)` a ogni render (riga `@php(\Log::debug('debugging color in badge component', ...))`): con questa pagina scrive una riga di log per carta. Va tolto (residuo di debug).

##### 8.3.4.3 — Link da `decks/edit.blade.php` e da `decks/show.blade.php`
In `decks/edit.blade.php`, nel blocco dei pulsanti in fondo (accanto a "Vedi mazzo"), prima del link "Vedi mazzo":
```blade
<a href="{{ route('decks.gap', [$deck->user->name, $deck->name, $deck->version]) }}">
    <x-secondary-button type="button">Carte mancanti / presenti</x-secondary-button>
</a>
```
In `decks/show.blade.php`, nel gruppo di pulsanti dello slot `header`, dopo "Cronologia versioni" (solo per chi è loggato, perché la rotta richiede il login; per gli ospiti il link porterebbe al login):
```blade
@auth
    <a href="{{ route('decks.gap', [$deck->user->name, $deck->name, $deck->version]) }}">
        <x-secondary-button type="button">Carte mancanti</x-secondary-button>
    </a>
@endauth
```
Per `edit` il link porta alla versione che si sta modificando (l'ultima), per `show` a quella che si sta guardando.

##### 8.3.4.4 — Verifica a mano
1. `php artisan route:list --name=decks.gap`: una sola rotta, `mazzo/{username}/{deckname}/delta/{version?}`; nessuna `decks.gap` sotto `mazzo/modifica`.
2. Collezione vuota: `/mazzo/{utente}/{mazzo}/delta` mostra tutte le carte tra le "Mancanti del tutto", il riepilogo ha Presenti = 0 e Mancanti = Richieste.
3. Collezione con tutte le copie del mazzo: "Mancanti del tutto" mostra il messaggio di lista vuota, Mancanti = 0, e `?mostra=presenti` elenca tutte le carte.
4. Una carta posseduta ma già in un altro mazzo **assemblato** dello stesso utente compare in "Impegnate in altri mazzi assemblati"; segnando quell'altro mazzo come smontato (`decks.toggle-assembled`) passa tra le presenti.
5. Un mazzo pubblico di un altro utente: i numeri seguono la **propria** collezione, non quella del proprietario.
6. Mazzo privato di un altro utente: 403. Da ospite: redirect al login.
7. Toggle: i due link cambiano `?mostra=`, il pulsante attivo è evidenziato, ricaricando la pagina la modalità resta; `?mostra=qualunque` si comporta come `mancanti`.
8. Una carta con `front_art_path` nullo non rompe la riga (niente miniatura, nessun errore).
9. Versione vecchia di un mazzo (`/delta/1`): usa le carte di quella versione.
10. Tema scuro e viewport mobile: il tipo di carta sparisce sotto `sm`, il resto resta leggibile.
11. I link "Carte mancanti / presenti" di `edit` e "Carte mancanti" di `show` aprono la pagina giusta.
