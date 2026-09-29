# Implementation plan 07 — Ricostruzione UnlimitedDB · Fase 7b: mazzi, pagine ed export/import

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisito**: [`implementationPlan-06-ricostruzioneMazziDominio.md`](implementationPlan-06-ricostruzioneMazziDominio.md) (enum, relazioni `leaders()`/`baseCard()`, validator, policy) e, per le pagine con la ricerca carte,
> [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`).
>
> Stato del codice: nessuna rotta, controller o vista dei mazzi. Le viste seguono le "Convenzioni per le view" dell'indice.

## Fase 7 — Gestione mazzi multi-formato (pagine)

### Step 7.6 — Pagine mazzi

#### 7.6.1 — Rotte
In `routes/web.php`. L'ordine conta: `/mazzi/crea` deve stare **prima** di `/mazzi/{deck}`.
```php
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
`{card}` nella rotta di rimozione fa route-model-binding su `Card` con la PK `id` (default del modello).

#### 7.6.2 — Controller: metodi con logica non banale
```
php artisan make:controller DeckController
```
```php
use App\Enums\DeckFormat;
use App\Models\Card;
use App\Models\Deck;
use App\Services\DeckValidation\DeckFormatValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * Lists public decks plus the logged-in user's own decks
 * Elenca i mazzi pubblici più quelli dell'utente loggato
 */
public function index(): View
{
    $decks = Deck::with('leaders', 'baseCard') // eager load: anteprima nella lista senza N+1
        ->where('is_public', true)
        ->when(auth()->id(), fn ($q, $userId) => $q->orWhere('user_id', $userId))
        ->latest()
        ->paginate(20);

    return view('decks.index', compact('decks'));
}

public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'format' => ['required', Rule::enum(DeckFormat::class)],
    ]);

    $deck = Deck::create([
        'user_id' => $request->user()->id,
        'name' => $validated['name'],
        'format' => $validated['format'],
        'is_public' => $request->boolean('is_public'),
    ]);

    return redirect()->route('decks.edit', $deck);
}

public function addCard(Request $request, Deck $deck): RedirectResponse
{
    $this->authorize('update', $deck);
    $validated = $request->validate([
        'card_id' => ['required', 'exists:cards,id'],
        'quantity' => ['required', 'integer', 'min:1'],
    ]);

    $deck->cards()->syncWithoutDetaching([
        $validated['card_id'] => ['quantity' => $validated['quantity']],
    ]);

    $errors = DeckFormatValidatorFactory::make($deck->format)->validate($deck->fresh());

    return back()->with('deck-errors', $errors); // warning non bloccanti nella vista: la carta resta comunque aggiunta
}

public function removeCard(Deck $deck, Card $card): RedirectResponse
{
    $this->authorize('update', $deck);
    $deck->cards()->detach($card->id);

    return back();
}

public function toggleAssembled(Deck $deck): RedirectResponse
{
    $this->authorize('update', $deck);
    $deck->update(['assembled' => ! $deck->assembled]);

    return back();
}

/**
 * Lists the whole version chain the deck belongs to, oldest first
 * Elenca l'intera catena di versioni a cui appartiene il mazzo, dalla più vecchia
 */
public function versions(Deck $deck): View
{
    $this->authorize('view', $deck);

    $chain = collect([$deck]);
    for ($d = $deck; $d->previousVersion; $d = $d->previousVersion) {
        $chain->prepend($d->previousVersion);
    }
    for ($d = $deck; $d->nextVersion; $d = $d->nextVersion) {
        $chain->push($d->nextVersion);
    }

    return view('decks.versions', ['decks' => $chain]);
}
```
`create()` e `edit()` restituiscono solo la vista (`decks.create`, `decks.edit`); `edit()` fa prima `$this->authorize('update', $deck)`.
La validazione è **informativa, non bloccante**: la vecchia versione permetteva di costruire un mazzo incompleto e vederne gli errori, non impediva il salvataggio riga per riga.

#### 7.6.3 — Viste
Tutte estendono `<x-app-layout>`, con titolo nello slot `header`.
- `resources/views/decks/index.blade.php`: lista (stesso pattern tabella + paginazione delle pagine admin), anteprima di leader e base grazie all'eager load.
- `resources/views/decks/create.blade.php`: form nuovo mazzo con `name`, `format` come `<select>` sui `case` di `DeckFormat` e checkbox `is_public`.
- `resources/views/decks/edit.blade.php`: pagina di deck-building (la più complessa del progetto): carte nel mazzo con quantità, ricerca per aggiungerne (riusa `CardSearch`, Step 10.1), pulsanti
  per `toggle-assembled` e per la rimozione di una riga, errori di `deck-errors` in un riquadro di warning. Estrarre la riga-carta in un componente (`<x-deck-card-row>`): lo stesso markup ricorre in `decks/gap.blade.php` (Fase 8).
- `resources/views/decks/versions.blade.php`: elenco della catena di versioni (`$decks`) con link a ciascuna.

#### 7.6.4 — Decisioni aperte
- **Pagina di sola lettura per i mazzi pubblici**: `GET /mazzi/{deck}` è la rotta di modifica e sta nel gruppo `auth`, quindi un mazzo pubblico di un altro utente compare in `decks.index` ma non si può aprire
  (`authorize('update')` risponde 403). La policy `view` è già pronta per una rotta `decks.show` pubblica; non è prevista in nessun step, va decisa.
- **Creazione di una nuova versione**: le colonne `version` e `previous_version_id` e le relazioni `previousVersion()`/`nextVersion()` esistono, ma nessuno step descrive come si crea una versione successiva
  (duplicare il mazzo con `version + 1` e `previous_version_id` = mazzo corrente, incluse le righe di `deck_cards`?).

### Step 7.7 — Export/Import mazzi

#### 7.7.1 — `app/Services/DeckExporter.php`
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
