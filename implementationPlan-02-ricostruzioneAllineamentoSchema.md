# Implementation plan 02 — Ricostruzione UnlimitedDB · Fase 4ter: allineamento schema, modelli e test

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). **Da eseguire dopo il piano 01** (`TelegramService`, senza il quale i test dello Step 4ter.5 non girano) e prima di tutti gli altri (i piani 06, 07, 08 e 10 danno per scontato lo schema di riferimento, con `card_id` nelle pivot).
>
> Cosa è stato trovato confrontando il piano con il codice (settembre 2026):
> - le pivot `card_aspect`, `card_trait`, `deck_cards` hanno la colonna che punta alla carta chiamata **`id`** (schema di riferimento: `card_id`);
> - `create_card_aspects_table` dichiara sia `$table->id()` sia `$table->string('id')`: due colonne con lo stesso nome;
> - `Deck::cards()` usa ancora le chiavi `'id'`, `'id'` (il vecchio piano la dava per "già corretta");
> - il job legge `publishedAt` senza chiederlo nei `fields[]` dell'API;
> - il test del job è rotto in più punti (vedi Step 4ter.5).

## Fase 4ter — Allineamento

### Step 4ter.1 — Riscrivere le tre migration delle pivot con `card_id`
Il progetto non è ancora in produzione (il redeploy pulito è lo Step 12.2), quindi si modificano le migration esistenti e si ricrea il database di sviluppo:
non serve una migration di rinomina. Dopo lo Step 4ter.3 basta un `cards:scan` per ripopolare le carte.

#### 4ter.1.1 — `database/migrations/2026_09_20_115102_create_card_aspects_table.php`
Sostituire il corpo di `up()`: niente `id()` surrogato, PK composta, colonna `card_id` con la stessa lunghezza di `cards.id`.
```php
Schema::create('card_aspect', function (Blueprint $table) {
    $table->string('card_id', 20);
    $table->foreignId('aspect_id')->constrained('aspects')->cascadeOnDelete();
    $table->timestamps();

    $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
    $table->primary(['card_id', 'aspect_id']);
});
```

#### 4ter.1.2 — `database/migrations/2026_09_20_140500_create_card_trait_table.php`
```php
Schema::create('card_trait', function (Blueprint $table) {
    $table->string('card_id', 20);
    $table->string('trait_name');
    $table->timestamps();

    $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
    $table->foreign('trait_name')->references('name')->on('traits')->cascadeOnDelete();
    $table->primary(['card_id', 'trait_name']);
});
```

#### 4ter.1.3 — `database/migrations/2026_09_20_135830_create_deck_cards_table.php`
```php
Schema::create('deck_cards', function (Blueprint $table) {
    $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
    $table->string('card_id', 20);
    $table->unsignedTinyInteger('quantity');
    $table->timestamps();

    $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
    $table->primary(['deck_id', 'card_id']);
});
```

### Step 4ter.2 — Aggiornare le chiavi delle relazioni nei modelli

#### 4ter.2.1 — `app/Models/Card.php`
Sostituire `aspects()`, `traits()` e `decks()`:
```php
public function aspects()
{
    return $this->belongsToMany(Aspect::class, 'card_aspect', 'card_id', 'aspect_id');
}

public function traits()
{
    return $this->belongsToMany(CardTrait::class, 'card_trait', 'card_id', 'trait_name');
}

public function decks()
{
    return $this->belongsToMany(Deck::class, 'deck_cards', 'card_id', 'deck_id')
        ->using(DeckCard::class)
        ->withPivot('quantity')
        ->withTimestamps();
}
```
`traits()` non ha bisogno della chiave del modello correlato: `CardTrait` ha già `$primaryKey = 'name'`.

#### 4ter.2.2 — `app/Models/Aspect.php` e `app/Models/CardTrait.php`
```php
// Aspect::cards()
return $this->belongsToMany(Card::class, 'card_aspect', 'aspect_id', 'card_id');

// CardTrait::cards()
return $this->belongsToMany(Card::class, 'card_trait', 'trait_name', 'card_id');
```

#### 4ter.2.3 — `app/Models/DeckCard.php`
`$fillable` passa da `['deck_id', 'id', 'quantity']` a `['deck_id', 'card_id', 'quantity']`, e la relazione diventa:
```php
public function card()
{
    return $this->belongsTo(Card::class, 'card_id', 'id');
}
```

#### 4ter.2.4 — `app/Models/Deck.php`, solo `cards()`
`leader()`/`base()` non si toccano qui: sono da sostituire nello Step 7.2 di [`implementationPlan-06-ricostruzioneMazziDominio.md`](implementationPlan-06-ricostruzioneMazziDominio.md).
```php
public function cards()
{
    return $this->belongsToMany(Card::class, 'deck_cards', 'deck_id', 'card_id')
        ->using(DeckCard::class)
        ->withPivot('quantity')
        ->withTimestamps();
}
```

### Step 4ter.3 — Ricreare il database e verificare
```bash
docker compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed
docker compose -f docker-compose.dev.yml exec app php artisan tinker
>>> Schema::getColumnListing('card_aspect');   // ['card_id', 'aspect_id', 'created_at', 'updated_at']
>>> Schema::getColumnListing('card_trait');    // ['card_id', 'trait_name', 'created_at', 'updated_at']
>>> Schema::getColumnListing('deck_cards');    // ['deck_id', 'card_id', 'quantity', 'created_at', 'updated_at']
```
`migrate:fresh` cancella anche gli utenti: dopo va rifatta la promozione ad admin (vedi README, "Primo utente admin"). Lo scan (`cards:scan`) funziona
solo dopo lo Step 9.2 (`TelegramService`), vedi [`implementationPlan-01-ricostruzioneTelegramService.md`](implementationPlan-01-ricostruzioneTelegramService.md).

### Step 4ter.4 — Verificare `publishedAt` nel payload dell'import
Il job costruisce `release_date` e `legal_date` con `Carbon::parse($cardData['publishedAt'])`, ma `publishedAt` non è nella lista `fields[]` della richiesta. Se l'API
filtra davvero i campi scalari non richiesti, `publishedAt` manca e `Carbon::parse(null)` restituisce **la data di oggi**: ogni carta avrebbe come `release_date` il giorno dell'ultimo scan.

#### 4ter.4.1 — Controllo
```
php artisan tinker
>>> Http::get('https://admin.starwarsunlimited.com/api/card-list', ['locale' => 'it', 'fields' => ['cardUid', 'cardNumber'], 'pagination[page]' => 1, 'pagination[pageSize]' => 1])->json('data.0.attributes.publishedAt');
```
Se restituisce una data, non c'è niente da fare. Se restituisce `null`:

#### 4ter.4.2 — Correzione in `app/Jobs/ImportCardsFromSwuApiJob.php`
- aggiungere `'publishedAt'` all'array `fields` della richiesta in `handle()`;
- in `processCard()` proteggere l'assenza del campo: `'release_date' => isset($cardData['publishedAt']) ? Carbon::parse($cardData['publishedAt'])->toDateString() : null` (il `?? null` dopo `toDateString()` oggi è codice morto: `toDateString()` non ritorna mai `null`).

### Step 4ter.5 — Sistemare `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`

#### 4ter.5.1 — Quarto test: espansione creata con chiave sbagliata
`Expansion::create(['code' => 'SOR', 'rotation' => '0'])` usa una colonna che non esiste (`code`, non presente in `$fillable`): Eloquent scarta l'attributo e la riga resta senza PK. Deve essere:
```php
Expansion::create(['expansion' => 'SOR', 'rotation' => '0']);
```

#### 4ter.5.2 — Nessuna richiesta reale verso i CDN nei test
Le fixture contengono gli URL veri delle immagini e `Http::fake` copre solo `card-list`: le altre richieste escono in rete e i file finiscono in `storage/app/public/cards`. Aggiungere `use Illuminate\Support\Facades\Storage;`, un helper e usarlo al posto dei quattro `Http::fake([...])`:
```php
/** Simula l'API SWU e risponde con un PNG finto a qualunque altro URL (immagini CDN). */
function fakeSwuHttp($cardListResponse): void
{
    Storage::fake('public');
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*' => $cardListResponse,
        '*' => Http::response('fake-image', 200, ['Content-Type' => 'image/png']),
    ]);
}
```
L'ordine delle voci conta: la prima che combacia vince, quindi il catch-all `'*'` va per ultimo.

#### 4ter.5.3 — Secondo test: contare solo le richieste all'API
`Http::assertSentCount(2)` conta anche i download delle immagini. Sostituirlo con:
```php
expect(Http::recorded(fn ($request) => str_contains($request->url(), 'card-list')))->toHaveCount(2);
```

#### 4ter.5.4 — Dipendenza da `TelegramService`
Tutti i test chiamano `app(TelegramService::class)`, classe che non esiste ancora: la suite non gira finché non si esegue lo Step 9.2. Con l'implementazione descritta lì (nessuna richiesta HTTP se
`services.telegram.bot_token` è vuoto) i test non hanno bisogno di mock.

#### 4ter.5.5 — Verifica
```bash
docker compose -f docker-compose.dev.yml exec app php artisan test --filter ImportCardsFromSwuApiJobTest
```
