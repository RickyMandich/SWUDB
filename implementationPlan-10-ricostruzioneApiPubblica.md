# Implementation plan 10 — Ricostruzione UnlimitedDB · Fase 11: API REST pubblica

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisiti**: [`implementationPlan-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-02-ricostruzioneAllineamentoSchema.md) (relazioni con `card_id`),
> [`implementationPlan-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`, condiviso con la pagina `/carte`) e
> [`implementationPlan-06-ricostruzioneMazziDominio.md`](implementationPlan-06-ricostruzioneMazziDominio.md) (enum e relazioni dei mazzi).
>
> Stato del codice: Sanctum non è in `composer.json` e non esiste `routes/api.php`; `bootstrap/app.php` registra solo `web`, `commands` e `health`.

## Fase 11 — API REST pubblica

### Step 11.1 — Sanctum

#### 11.1.1 — Installazione
```
composer require laravel/sanctum
php artisan install:api
```
`install:api` crea `routes/api.php`, aggiunge la rotta `api` a `bootstrap/app.php` e pubblica la migration di `personal_access_tokens`. Poi `php artisan migrate`. https://laravel.com/docs/12.x/sanctum

### Step 11.2 — Endpoint pubblici

#### 11.2.1 — Rotte in `routes/api.php`
```php
use App\Http\Controllers\Api;

Route::prefix('cards')->group(function () {
    Route::get('/search', [Api\CardController::class, 'search'])->middleware('throttle:60,1');
    Route::get('/{expansion}/{number}', [Api\CardController::class, 'show']);
});
Route::get('/decks/{userName}/{deckName}', [Api\DeckController::class, 'show']);
```

#### 11.2.2 — `app/Http/Controllers/Api/CardController.php`
```bash
php artisan make:controller Api/CardController
```
Il `with()` evita una query per carta quando `CardResource` legge aspetti e tratti.
```php
class CardController extends Controller
{
    public function show(string $expansion, int $number): CardResource
    {
        $card = Card::with(['aspects', 'traits'])
            ->where('expansion', $expansion)->where('number', $number)->firstOrFail();

        return new CardResource($card);
    }

    public function search(Request $request, CardSearch $search): AnonymousResourceCollection
    {
        $cards = $search->apply(Card::with(['aspects', 'traits']), $request->only(['nome', 'espansione', 'tipo', 'costo', 'aspetto', 'tratto', 'unique_card']))
            ->paginate(24);

        return CardResource::collection($cards);
    }
}
```
`search()` riusa `CardSearch`: stessa logica della pagina `/carte`, output diverso (JSON tramite Resource). Le pagine API condividono il backend delle pagine UI dove possibile.
Rate limiting: https://laravel.com/docs/12.x/routing#rate-limiting

#### 11.2.3 — `app/Http/Controllers/Api/DeckController.php`
```bash
php artisan make:controller Api/DeckController
```
```php
class DeckController extends Controller
{
    public function show(string $userName, string $deckName): DeckResource
    {
        $deck = Deck::with('cards.aspects', 'cards.traits')
            ->whereHas('user', fn ($q) => $q->where('name', $userName))
            ->where('name', $deckName)
            ->where('is_public', true)
            ->firstOrFail();

        return new DeckResource($deck);
    }
}
```

#### 11.2.4 — Risoluzione: URL stabile dei mazzi
Confermata l'architettura adottata nel piano 07:
- L'URL usa `{userName}/{deckName}`.
- La combinazione è resa univoca per singolo utente tramite regola di unicità `unique(['user_id', 'name'])` (e query sull'ultima versione `latest('version')`).
- Utenti diversi possono avere mazzi con lo stesso nome senza alcuna collisione.


### Step 11.3 — Endpoint autenticati
Token Sanctum per azioni future (es. sync della collezione da un'app esterna). Non necessario al day 1.

### Step 11.4 — API Resources

#### 11.4.1 — `CardResource`
```
php artisan make:resource CardResource
```
```php
class CardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // 'id' (espansione+numero) volutamente escluso: 'cid' è la chiave pubblica stabile dell'API ufficiale
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

#### 11.4.2 — `DeckResource`
```
php artisan make:resource DeckResource
```
Espone `name`, `format` (`$this->format->value`), `is_public` e `cards` come `CardResource` con la quantità aggiunta a mano
(`$this->cards->map(fn ($c) => [...(new CardResource($c))->resolve(), 'quantity' => $c->pivot->quantity])`), non l'`id` interno del mazzo. https://laravel.com/docs/12.x/eloquent-resources
