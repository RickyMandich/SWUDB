# Implementation plan 08 — Ricostruzione UnlimitedDB · Fase 8: collezione e carte mancanti

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisiti**: [`implementationPlan-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-02-ricostruzioneAllineamentoSchema.md) (colonne `card_id`),
> [`implementationPlan-06-ricostruzioneMazziDominio.md`](implementationPlan-06-ricostruzioneMazziDominio.md) (relazioni dei mazzi) e, per la ricerca,
> [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`).
>
> Stato del codice: non esistono tabella `collection_cards`, modello `CollectionCard`, `CollectionController`, `DeckGapCalculator` né viste.
> Nota: il toggle "carte mancanti / carte presenti" richiesto per la pagina di deck-building non era descritto da nessuna parte nel vecchio piano (rimandava a una "decisione più sopra" che non esisteva): è lo Step 8.3.4.

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
        $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo']))
            ->with(['collectionCards' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->paginate(24)->withQueryString();

        return view('collection.index', compact('cards'));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'variant' => ['required', 'in:normal,foil,hyper,prestige,hyper_foil'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $key = ['user_id' => $request->user()->id, 'card_id' => $validated['card_id'], 'variant' => $validated['variant']];

        if ($validated['quantity'] === 0) {
            CollectionCard::where($key)->delete(); // niente righe a zero: la somma per card_id resta corretta senza filtrarle ovunque
        } else {
            CollectionCard::updateOrCreate($key, ['quantity' => $validated['quantity']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
```

#### 8.2.3 — Vista `resources/views/collection/index.blade.php`
Titolo nello slot `header`. Stessa griglia di `cards/index.blade.php` (Step 10.1, riusa `CardSearch`), con un controllo quantità per variante su ogni carta.
L'aggiornamento senza reload è l'unico punto del progetto con JS oltre ad Alpine e Chart.js: uno `<script>` inline che intercetta il cambio di un `<input type="number">` e chiama
`fetch("{{ route('collection.update') }}", {method: 'PATCH', ...})` con header `X-CSRF-TOKEN` (il meta `csrf-token` è già nel layout) e `Accept: application/json`. Niente bundler.

### Step 8.3 — "Carte mancanti per un mazzo"
Tre informazioni per ogni carta del mazzo: mancante del tutto, posseduta ma impegnata in un altro mazzo assemblato, disponibile.

#### 8.3.1 — `app/Services/DeckGapCalculator.php`
```bash
php artisan make:class Services/DeckGapCalculator
```
Rispetto alla prima bozza: il calcolo è **per l'utente che guarda** (non per il proprietario del mazzo, altrimenti guardando un mazzo pubblico altrui si vedrebbe la collezione del proprietario)
e restituisce anche le carte disponibili (`present`), necessarie al toggle dello Step 8.3.4.
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

#### 8.3.2 — Rotta
Nel gruppo `auth` + `verified` (la collezione è personale):
```php
Route::get('/mazzi/{deck}/delta', [DeckController::class, 'gap'])->name('decks.gap');
```

#### 8.3.3 — Metodo `gap` del controller
```php
public function gap(Request $request, Deck $deck, DeckGapCalculator $calculator): View
{
    $this->authorize('view', $deck);

    $mode = $request->query('mostra') === 'presenti' ? 'presenti' : 'mancanti'; // default: mancanti
    ['missing' => $missing, 'reservedElsewhere' => $reserved, 'present' => $present] = $calculator->forDeck($deck, $request->user());

    $quantities = $mode === 'presenti'
        ? ['present' => $present]
        : ['missing' => $missing, 'reserved' => $reserved];

    $cards = Card::whereIn('id', collect($quantities)->flatMap(fn ($q) => array_keys($q))->unique())->get()->keyBy('id');

    return view('decks.gap', compact('deck', 'mode', 'quantities', 'cards'));
}
```

#### 8.3.4 — Vista con toggle (`resources/views/decks/gap.blade.php`)
- Titolo nello slot `header` ("Carte per {{ $deck->name }}").
- **Toggle mancanti / presenti**: due link `?mostra=mancanti` e `?mostra=presenti` affiancati (segmented control Tailwind), quello attivo evidenziato in base a `$mode`. Sono link GET, quindi la scelta resta nell'URL e si può condividere.
- Modalità `mancanti`: due sezioni, "Mancanti del tutto" (`$quantities['missing']`) e "Impegnate in altri mazzi assemblati" (`$quantities['reserved']`), con due `<x-badge>` di colore diverso (Step 5.4.5 di
  [`implementationPlan-03-ricostruzioneAdminErrori.md`](implementationPlan-03-ricostruzioneAdminErrori.md)).
- Modalità `presenti`: una sola sezione con `$quantities['present']`, cioè le copie possedute e libere.
- Riga-carta: lo stesso componente estratto per `decks/edit.blade.php` (Step 7.6.3 in [`implementationPlan-07-ricostruzioneMazziPagine.md`](implementationPlan-07-ricostruzioneMazziPagine.md)).
- Un link "Carte mancanti / presenti" in `decks/edit.blade.php` che punta a `route('decks.gap', $deck)`.
