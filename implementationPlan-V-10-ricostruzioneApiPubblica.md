# Implementation plan 10 — Ricostruzione UnlimitedDB · Fase 11: API REST pubblica

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md).
> **Prerequisiti**: [`implementationPlan-V-02-ricostruzioneAllineamentoSchema.md`](implementationPlan-V-02-ricostruzioneAllineamentoSchema.md) (relazioni con `card_id`),
> [`implementationPlan-V-05-ricostruzioneCatalogoPubblico.md`](implementationPlan-V-05-ricostruzioneCatalogoPubblico.md) Step 10.1 (`CardSearch`, condiviso con la pagina `/carte`) e
> [`implementationPlan-V-06-ricostruzioneMazziDominio.md`](implementationPlan-V-06-ricostruzioneMazziDominio.md) (enum e relazioni dei mazzi).
>
> Stato del codice (riletto il 2026-10-19 alle 14:45): Step 11.1, 11.2 e 11.4 applicati (Sanctum, `routes/api.php` registrato in `bootstrap/app.php`, controller `Api/*`, `CardResource` e `DeckResource`); lo Step 11.3 è rimandato; i test dello Step 11.5 sono **scritti** (`tests/Feature/Api/PublicApiTest.php`, 14 test, identici agli snippet) ma **7 falliscono**: l'ordinamento di default di `CardBuilder` usa SQL solo MariaDB e su SQLite (i test) dà errore. Il piano **non era chiuso**: mancava lo Step 11.5.8. Gli snippet sotto sono allineati al codice applicato.
>
> **Aggiornamento 2026-10-26**: Step 11.5.8 applicato (`CardBuilder::aspectOrderKey()` con un ramo per driver, blocco `orderByRaw` duplicato tolto), i 14 test dell'API sono verdi e la suite intera è a 111 passati; piano chiuso e rinominato `V-`. Resta rimandato solo lo Step 11.3 (token Sanctum).

## Fase 11 — API REST pubblica

### Step 11.1 — Sanctum

#### 11.1.1 — Installazione
```
composer require laravel/sanctum
php artisan install:api
```
`install:api` crea `routes/api.php`, aggiunge la rotta `api` a `bootstrap/app.php` e pubblica la migration di `personal_access_tokens`. Poi `php artisan migrate`. https://laravel.com/docs/12.x/sanctum
Il trait `HasApiTokens` su `User` non è stato aggiunto: serve solo per lo Step 11.3 (token), oggi non usato.

### Step 11.2 — Endpoint pubblici

#### 11.2.1 — Rotte in `routes/api.php`
```php
use App\Http\Controllers\Api;

Route::middleware('throttle:60,1')->group(function () {
    Route::prefix('cards')->group(function () {
        Route::get('/search', [Api\CardController::class, 'search']);
        Route::get('/{expansion}/{number}', [Api\CardController::class, 'show']);
    });
    Route::get('/decks/{userName}/{deckName}', [Api\DeckController::class, 'show']);
});
```
Il `throttle:60,1` copre tutte e tre le rotte (60 richieste al minuto per IP). `/search` va registrata prima di `/{expansion}/{number}`.

#### 11.2.2 — `app/Http/Controllers/Api/CardController.php`
```bash
php artisan make:controller Api/CardController
```
Il `with()` evita una query per carta quando `CardResource` legge aspetti e tratti. Servono gli `use` di `App\Http\Resources\CardResource`, `App\Models\Card`, `App\Services\CardSearch`, `Illuminate\Http\Request` e `Illuminate\Http\Resources\Json\AnonymousResourceCollection`: senza quello di `CardResource` ogni chiamata dà errore 500.
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
        $cards = $search
            ->apply(Card::with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS))
            ->paginate(24)
            ->withQueryString();

        return CardResource::collection($cards);
    }
}
```
`search()` riusa `CardSearch`: stessa logica e **stessi parametri** della pagina `/carte` (`CardSearch::FILTER_KEYS`: `nome`, `espansioni`, `tipi`, `aspetti` (id), `tratti` (nomi), `costo_min/max`, `vita_min/max`, `potenza_min/max`, `unique_card`), output diverso (JSON tramite Resource). I nomi dei filtri non vanno copiati a mano: un nome diverso da quelli di `FILTER_KEYS` viene ignorato in silenzio. `->withQueryString()` mantiene i filtri nei link di paginazione. Le pagine API condividono il backend delle pagine UI dove possibile.
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
            ->whereDoesntHave('nextVersion')
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
- La combinazione è resa univoca per singolo utente e versione tramite `unique(['user_id', 'name', 'version'])`; l'API restituisce l'ultima versione (`whereDoesntHave('nextVersion')`) e solo se pubblica.
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
Espone `name`, `format` (`$this->format->value`), `is_public`, `version` e `cards` come `CardResource` con la quantità aggiunta a mano
(`$this->cards->map(fn ($c) => [...(new CardResource($c))->resolve(), 'quantity' => $c->pivot->quantity])`), non l'`id` interno del mazzo. https://laravel.com/docs/12.x/eloquent-resources

### Step 11.5 — Test Pest dell'API

Un unico file di test, `tests/Feature/Api/PublicApiTest.php`, che copre i tre endpoint di `routes/api.php` e il rate limit. File scritto il 2026-10-19 (Step 11.5.1–11.5.5 applicati); l'ultima esecuzione (log delle 12:47) dà 7 falliti e 7 passati, per la causa dello Step 11.5.8.

Punti di partenza, verificati sul codice:
- `tests/Pest.php` applica già `RefreshDatabase` ai test `Feature`: non serve `uses()` nel file.
- `phpunit.xml` usa SQLite in memoria e `CACHE_STORE=array`: ogni test parte pulito, anche per `CardSearch::getBounds()` (cache di un'ora) e per il contatore del `throttle`.
- Gli helper `makeCard(array $attributes = [])` e `makeDeck(User $user, array $cards = [], array $attributes = [])` sono in `tests/Pest.php`. `makeCard()` crea da sola l'espansione e accetta qualunque colonna di `Card::$fillable` (`name`, `type`, `cost`, `expansion`, `front_art_path`...); `makeDeck()` vuole le carte come coppie `[carta, quantità]`, crea il mazzo pubblico, `premier`, `version` 1, e accetta `name`, `is_public`, `version`, `previous_version_id` negli attributi.
- Le carte vanno create **prima** della prima richiesta di ogni test: `getBounds()` mette i limiti in cache alla prima chiamata e i filtri a intervallo (`costo_min`, ...) si applicano solo se più stretti dei limiti.

#### 11.5.1 — Creare il file
```
php artisan make:test Api/PublicApiTest --pest
```
Sostituire l'esempio generato con il contenuto dei punti seguenti, nello stesso file e in quest'ordine. Intestazione:
```php
<?php

use App\Models\Aspect;
use App\Models\CardTrait;
use App\Models\User;
```

#### 11.5.2 — Dettaglio carta
```php
test('card detail returns the card resource', function () {
    $aspect = Aspect::create(['name' => 'Vigilanza', 'slug' => 'vigilance', 'order' => 1, 'color' => '#4073d4']);
    $trait = CardTrait::create(['name' => 'Ribelle']);
    $card = makeCard([
        'name' => 'Luke Skywalker',
        'title' => 'Jedi Knight',
        'front_art_path' => 'cards/SOR/1-front.jpg',
    ]);
    $card->aspects()->attach($aspect->id);
    $card->traits()->attach($trait->name);

    $response = $this->getJson("/api/cards/{$card->expansion}/{$card->number}")
        ->assertOk()
        ->assertJsonPath('data.cid', $card->cid)
        ->assertJsonPath('data.name', 'Luke Skywalker')
        ->assertJsonPath('data.title', 'Jedi Knight')
        ->assertJsonPath('data.expansion', $card->expansion)
        ->assertJsonPath('data.number', $card->number)
        ->assertJsonPath('data.aspects', ['Vigilanza'])
        ->assertJsonPath('data.traits', ['Ribelle'])
        ->assertJsonPath('data.back_image', null)
        ->assertJsonMissingPath('data.id');

    expect($response->json('data.front_image'))->toEndWith('/storage/cards/SOR/1-front.jpg');
});

test('card detail returns 404 for an unknown card', function () {
    $this->getJson('/api/cards/SOR/9999')->assertNotFound();
});
```
L'`id` interno (`{espansione}{numero}`) non deve comparire: la chiave pubblica è `cid`. `front_image` si confronta solo nella parte finale perché l'host dipende da `APP_URL`.

#### 11.5.3 — Ricerca carte
```php
test('card search filters by name', function () {
    makeCard(['name' => 'Luke Skywalker']);
    makeCard(['name' => 'Darth Vader']);

    $this->getJson('/api/cards/search?nome=Luke')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Luke Skywalker');
});

test('card search uses the same filter names as the catalogue page', function () {
    makeCard(['expansion' => 'SOR', 'name' => 'Carta SOR', 'type' => 'Unità']);
    makeCard(['expansion' => 'SHD', 'name' => 'Carta SHD', 'type' => 'Unità']);
    makeCard(['expansion' => 'SHD', 'name' => 'Evento SHD', 'type' => 'Evento']);

    $query = http_build_query(['espansioni' => ['SHD'], 'tipi' => ['Unità']]);

    $this->getJson("/api/cards/search?{$query}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Carta SHD');
});

test('card search filters by aspect id', function () {
    $aspect = Aspect::create(['name' => 'Vigilanza', 'slug' => 'vigilance', 'order' => 1, 'color' => '#4073d4']);
    $withAspect = makeCard(['name' => 'Con aspetto']);
    makeCard(['name' => 'Senza aspetto']);
    $withAspect->aspects()->attach($aspect->id);

    $query = http_build_query(['aspetti' => [$aspect->id]]);

    $this->getJson("/api/cards/search?{$query}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Con aspetto');
});

test('card search applies the cost range', function () {
    makeCard(['name' => 'Economica', 'cost' => 1]);
    makeCard(['name' => 'Media', 'cost' => 3]);
    makeCard(['name' => 'Costosa', 'cost' => 6]);

    // limiti nel database: 1 e 6, quindi 3 e 5 sono entrambi filtri attivi
    $this->getJson('/api/cards/search?costo_min=3&costo_max=5')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Media');
});

test('card search paginates by 24 and keeps the filters in the links', function () {
    foreach (range(1, 30) as $i) {
        makeCard(['name' => "Droide {$i}"]);
    }
    makeCard(['name' => 'Wookiee']);

    $first = $this->getJson('/api/cards/search?nome=Droide')
        ->assertOk()
        ->assertJsonCount(24, 'data')
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.current_page', 1);

    expect($first->json('links.next'))->toContain('nome=Droide')->toContain('page=2');

    $this->getJson('/api/cards/search?nome=Droide&page=2')
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.current_page', 2);
});
```
Il test dei filtri con i nomi di `FILTER_KEYS` (`espansioni`, `tipi`) è il test di regressione del bug trovato il 2026-10-19: con i vecchi nomi il filtro veniva ignorato in silenzio e tornavano tutte le carte.

#### 11.5.4 — Dettaglio mazzo
```php
test('deck detail returns a public deck with the quantities', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    $card = makeCard(['name' => 'Luke Skywalker']);
    makeDeck($user, [[$card, 3]], ['name' => 'mazzo-test']);

    $this->getJson('/api/decks/ricky/mazzo-test')
        ->assertOk()
        ->assertJsonPath('data.name', 'mazzo-test')
        ->assertJsonPath('data.format', 'premier')
        ->assertJsonPath('data.is_public', true)
        ->assertJsonPath('data.version', 1)
        ->assertJsonCount(1, 'data.cards')
        ->assertJsonPath('data.cards.0.cid', $card->cid)
        ->assertJsonPath('data.cards.0.quantity', 3);
});

test('deck detail returns 404 for a private deck', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    makeDeck($user, [], ['name' => 'mazzo-test', 'is_public' => false]);

    $this->getJson('/api/decks/ricky/mazzo-test')->assertNotFound();
});

test('deck detail returns only the latest version', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    $card = makeCard();
    $v1 = makeDeck($user, [[$card, 1]], ['name' => 'mazzo-test']);
    makeDeck($user, [[$card, 2]], ['name' => 'mazzo-test', 'version' => 2, 'previous_version_id' => $v1->id]);

    $this->getJson('/api/decks/ricky/mazzo-test')
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.cards.0.quantity', 2);
});

test('deck detail returns 404 when the latest version is private even if an older one is public', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    $v1 = makeDeck($user, [], ['name' => 'mazzo-test']);
    makeDeck($user, [], ['name' => 'mazzo-test', 'version' => 2, 'previous_version_id' => $v1->id, 'is_public' => false]);

    $this->getJson('/api/decks/ricky/mazzo-test')->assertNotFound();
});

test('decks with the same name of different users do not collide', function () {
    $cardA = makeCard(['name' => 'Carta di ricky']);
    $cardB = makeCard(['name' => 'Carta di luke']);
    makeDeck(User::factory()->create(['name' => 'ricky']), [[$cardA, 1]], ['name' => 'mazzo-test']);
    makeDeck(User::factory()->create(['name' => 'luke']), [[$cardB, 1]], ['name' => 'mazzo-test']);

    $this->getJson('/api/decks/luke/mazzo-test')
        ->assertOk()
        ->assertJsonPath('data.cards.0.cid', $cardB->cid);
});

test('deck detail returns 404 for an unknown user or deck', function () {
    $user = User::factory()->create(['name' => 'ricky']);
    makeDeck($user, [], ['name' => 'mazzo-test']);

    $this->getJson('/api/decks/nessuno/mazzo-test')->assertNotFound();
    $this->getJson('/api/decks/ricky/non-esiste')->assertNotFound();
});
```
Il quarto test fissa un comportamento voluto: se l'ultima versione è privata l'API non è più raggiungibile, nemmeno con una versione precedente pubblica, così non si espone un mazzo che il proprietario ha ritirato.

#### 11.5.5 — Rate limit
```php
test('the API is limited to 60 requests per minute', function () {
    $this->getJson('/api/cards/search')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60');

    foreach (range(2, 60) as $i) {
        $this->getJson('/api/cards/search')->assertOk();
    }

    $this->getJson('/api/cards/search')->assertStatus(429);
});
```
Il `throttle:60,1` senza nome identifica il chiamante per dominio e IP, non per rotta: il contatore è condiviso dai tre endpoint.

#### 11.5.6 — Esecuzione e chiusura
```
php artisan test tests/Feature/Api/PublicApiTest.php
composer test
```
Se un test fallisce per un dettaglio dei dati (es. un tipo `int` contro `string` nei confronti di `assertJsonPath`), correggere il test, non l'API. Se invece fallisce per un comportamento reale (es. 500), annotarlo nel `todo.md` come bug dell'API.
Con l'esecuzione attuale 7 test su 14 falliscono per un bug **del codice**, non dei test: vedi lo Step 11.5.8, da applicare prima di chiudere. Dopo i test verdi: annotare l'esito di `composer test` nel README (sezione Test, che oggi riporta solo quello del 2026-10-04), togliere "API pubblica" dall'elenco di cosa non ha test, spuntare 11.5 nel `todo.md` e rinominare il piano in `implementationPlan-V-10-ricostruzioneApiPubblica.md`.

#### 11.5.7 — Opzionale: errori sempre in JSON sotto `/api`
Da verificare prima: una richiesta a `/api/...` **senza** header `Accept: application/json` (es. `curl -i http://localhost:66/api/cards/SOR/9999`) potrebbe ricevere la pagina HTML di errore invece di JSON. In quel caso, in `bootstrap/app.php`:
```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(fn ($request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
})
```
e un test che fa `$this->get('/api/cards/SOR/9999')` (senza `getJson`) e controlla `assertNotFound()->assertJsonStructure(['message'])`. Non fa parte del piano base.

#### 11.5.8 — Bug: `CardBuilder` usa SQL solo MariaDB, i test su SQLite vanno in errore
Sintomo (esecuzione del 2026-10-19, 12:47, `php artisan test tests/Feature/Api/PublicApiTest.php`): `Tests: 7 failed, 7 passed`. Nel log: `SQLSTATE[HY000]: General error: 1 near "ORDER": syntax error (Connection: sqlite, Database: :memory:...)`, con la query che contiene `GROUP_CONCAT(LPAD(...) ORDER BY ... SEPARATOR '')`.

Causa: `App\Models\Builders\CardBuilder::get()` aggiunge l'ordinamento di default a ogni query su `Card` che non ha un `orderBy`. Il criterio sugli aspetti (`applyDefaultOrder()`, punto 2) è scritto per MariaDB: `GROUP_CONCAT(... ORDER BY ... SEPARATOR '')` e `LPAD` non esistono in SQLite, che è il database di `phpunit.xml`. Su MariaDB funziona; nei test no. L'errore si presenta appena la query ha almeno una carta da leggere (con `paginate()` su tabella vuota il `select` non parte), quindi i sette test che cadono sono i due del dettaglio carta (`firstOrFail()` → `first()` → `get()`) e i cinque della ricerca; i sei test dei mazzi e il rate limit non passano da `CardBuilder` e restano verdi. Anche altri test che fanno `Card::query()...->get()` (es. `CardSearchTest`, `DeckPagesTest`) possono essere colpiti: lo dirà la suite intera.

**Conferma dalla suite intera (2026-10-23, `test.log`: 30 falliti, 85 passati).** La stessa causa (`near "ORDER"`) fa cadere 26 test, non solo i 7 dell'API: `PublicApiTest` (7), `CardSearchTest` (7), `DeckImporterTest` (7), `DeckPagesTest` (4: 'removing a card detaches it', 'card search paginates by 30', 'card search applies the catalogue filters', 'a logged user imports a json file'), `DeckExporterTest` (1). Gli altri 4 fallimenti non c'entrano con `CardBuilder`: sono i test di `ImportCardsFromSwuApiJobTest` (ruolo `admin` assente, mail accodata), risolti da `implementationPlan-importCartePerPagina.md`. Quindi, applicata questa correzione e quella del piano di import, la suite dovrebbe essere verde (115 test). Attenzione: i due test di ricerca di `DeckPagesTest` potrebbero poi mostrare l'errore, oggi mascherato, sulla relazione `Card::with('aspect')` (`todo.md`, 7.6.6ter): se succede, correggere in `DeckController::searchCards` con `with('aspects')`.

Il bug non è dell'API: è in un file del codice che si applica a mano. Non si risolve cambiando i test (non si aggira con `orderBy` espliciti nei controller: `/carte` e le altre pagine perderebbero l'ordine di default).

Correzione in `app/Models/Builders/CardBuilder.php`: scegliere l'espressione degli aspetti in base al driver. Sostituire, in `applyDefaultOrder()`, il blocco `$aspects = "(SELECT GROUP_CONCAT(...)";` con una chiamata a un nuovo metodo, e aggiungere il metodo:
```php
// in applyDefaultOrder(), al posto di $aspects = "(...)";
$aspects = $this->aspectOrderKey();

$this->orderByRaw("($aspects) IS NULL")
    ->orderByRaw($aspects);
```
```php
/**
 * SQL expression that builds the aspects sort key of a card (zero-padded `order`s, NULL if none).
 * Espressione SQL della chiave di ordinamento degli aspetti: MariaDB per l'app, SQLite per i test.
 *
 * @return string Raw SQL, a correlated scalar subquery on `cards.id`
 */
protected function aspectOrderKey(): string
{
    if ($this->query->getConnection()->getDriverName() === 'sqlite') {
        // SQLite non ha LPAD e non accetta ORDER BY/SEPARATOR dentro GROUP_CONCAT: printf al posto di LPAD,
        // nessun ordine interno garantito (basta per i test, che non verificano l'ordine dei multi-aspetto).
        return <<<'SQL'
            (SELECT GROUP_CONCAT(printf('%04d', a."order"), '')
                FROM card_aspect ca
                JOIN aspects a ON a.id = ca.aspect_id
                WHERE ca.card_id = cards.id)
            SQL;
    }

    return <<<'SQL'
        (SELECT GROUP_CONCAT(
                LPAD(a.`order`, 4, '0')
                ORDER BY ca.position, ca.created_at, a.`order`
                SEPARATOR ''
            )
            FROM card_aspect ca
            JOIN aspects a ON a.id = ca.aspect_id
            WHERE ca.card_id = cards.id)
        SQL;
}
```
Il ramo MariaDB è identico a quello attuale: in produzione e in locale (compose) l'ordinamento non cambia. Il resto di `applyDefaultOrder()` (tipo generico, tipo specifico, costo, nome, `legal_date`, numero, `id`) è SQL standard e non si tocca.

Verifica:
```
php artisan test tests/Feature/Api/PublicApiTest.php
composer test
```
Atteso: 14 test su 14 verdi per l'API. Se la suite intera mostra altri fallimenti, controllare nel log se hanno la stessa causa (stesso errore `near "ORDER"`) prima di indagare altrove, e annotare nel `todo.md` quelli con causa diversa. Poi chiudere come da Step 11.5.6 (README sezione Test con l'esito di `composer test`, `todo.md`, rinomina in `V-10`).
