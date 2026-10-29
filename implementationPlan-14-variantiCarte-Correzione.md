# Implementation plan 14 (correzione) — Cosa resta da fare dopo l'esecuzione del piano 14

> Riunisce in un solo file ciò che resta di [`14a`](implementationPlan-14a-correzioniVarianti.md) e [`14b`](implementationPlan-14b-deltaCollezioneVarianti.md) (che restano come storico e rimandano qui), aggiornato alle risposte dell'utente del 2026-10-29.
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.
> Prerequisito dello Step 13.4 del piano 13. Dopo questo piano si riprende il piano 14 dallo Step 6.

---

## Già fatto (2026-10-29, dichiarato dall'utente)

- [x] `use Illuminate\Database\Eloquent\Builder;` in `Card.php`
- [x] `show()` messo nei controller giusti (`DeckController::show` del mazzo, `CardController::show` con redirect 301 delle varianti)
- [x] `use` in `tests/Feature/Cards/CardVariantsTest.php`
- [x] `+` residui tolti da `collection/index.blade.php`
- [x] Decisione: **l'indice unico `(expansion, number)` resta** (niente `dropUnique`). Vedi Step 4.

---

## Step 1 — `cards:scan --phase`: passarla al job

L'opzione è nella firma ma `handle()` non la usa. Il job riceve la fase come **terzo argomento del costruttore** (`page`, `runId`, `phase`); per un nuovo scan il `runId` è `null`. `app/Console/Commands/ScanCards.php`, `handle()`:
```php
    public function handle()
    {
        $page = max(1, (int) $this->option('page'));
        $phase = (string) $this->option('phase');

        if (! in_array($phase, [ImportCardsFromSwuApiJob::PHASE_ORIGINALS, ImportCardsFromSwuApiJob::PHASE_VARIANTS], true)) {
            $this->error('--phase deve essere "originals" oppure "variants".');

            return self::FAILURE;
        }

        ImportCardsFromSwuApiJob::dispatch($page, null, $phase);
        $this->info("Cards scan queued from page {$page} (phase: {$phase}).");

        return self::SUCCESS;
    }
```
Uso:
- `cards:scan`: originali dalla pagina 1, poi passa da solo alle varianti.
- `cards:scan --page=N`: riprende gli originali dalla pagina N (poi varianti). Se interrompi gli originali, riprendi sempre così.
- `cards:scan --phase=variants [--page=N]`: solo varianti; richiede gli originali già a DB.

---

## Step 2 — Job: default di `$phase` per i job già in coda

Con `public string $phase` promosso nel costruttore, un job serializzato prima della modifica e ripreso col codice nuovo lancia `must not be accessed before initialization`. In `ImportCardsFromSwuApiJob`:
```php
    /** Il default permette ai job messi in coda prima del piano 14 di ripartire come "originals" */
    public string $phase = self::PHASE_ORIGINALS;

    public function __construct(public int $page = 1, ?string $runId = null, string $phase = self::PHASE_ORIGINALS)
    {
        $this->phase = $phase;
        $this->startsRun = $runId === null;
        $this->runId = $runId ?? (string) Str::uuid();
    }
```
(la dichiarazione della proprietà va accanto alle altre, prima del costruttore.)

---

## Step 3 — Test

#### 3.1 — Definizione di `fakeVariantEntry()` (aggiunta anche al piano 14, Step 5.1bis)
In `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`, subito dopo `fakeCardEntry()`. Serve al test 'assegna le promo dei primi set alle sottoespansioni stile swudb':
```php
/**
 * A variant entry: same skeleton as the original, with its own cardUid/number, the original it points to and its variant type
 * Una variante: stesso scheletro dell'originale, con cardUid e numero propri, l'originale a cui rimanda e il tipo di variante
 */
function fakeVariantEntry(string $cid, int $number, string $originalUid, string $type, string $variantId): array
{
    return fakeCardEntry(['attributes' => [
        'cardUid' => $cid,
        'cardNumber' => $number,
        'variantOf' => ['data' => ['attributes' => ['cardUid' => $originalUid]]],
        'variantTypes' => ['data' => [['attributes' => ['name' => $type, 'variantId' => $variantId]]]],
    ]]);
}
```
`'2579145458'` è il `cardUid` dell'originale di `fakeCardEntry()` (Luke Skywalker).

#### 3.2 — 'pagina correttamente su piu' pagine' (in che senso va adeguato)
Prima lo scan faceva 2 richieste (2 pagine). Ora, finiti gli originali, parte la fase varianti: **3 richieste**. Il test falliva con `A request was made, but the response sequence is empty` perché il client HTTP di Laravel invoca tutti gli stub che combaciano con l'URL e poi sceglie il primo non nullo: la richiesta delle varianti combacia anche con `card-list*`, quindi consuma un terzo elemento della sequenza (anche se risponde `*notNull*`). Va aggiunto un terzo `push` e il conteggio diventa 3:
```php
it('pagina correttamente su piu\' pagine', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::sequence()
            ->push(['data' => [fakeCardEntry()], 'meta' => ['pagination' => ['pageCount' => 2]]])
            ->push(['data' => [fakeCardEntry(['attributes' => ['cardUid' => '1111111111', 'cardNumber' => 12]])], 'meta' => ['pagination' => ['pageCount' => 2]]])
            // consumato dalla richiesta della fase varianti (la risposta vera arriva da fakeSwuHttp, `*notNull*`)
            ->push(['data' => [], 'meta' => ['pagination' => ['pageCount' => 0]]]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'card-list')))->toHaveCount(3); // 2 originali + 1 varianti
    expect(Card::count())->toBe(2);
});
```

#### 3.3 — 'riprende da una pagina con cards:scan --page'
Dopo l'ultima pagina degli originali la fase varianti riparte **dalla pagina 1** (è voluto), quindi `assertNotSent(... page=1)` falliva. Va ristretto alla fase originali (`[$null]`, che non è contenuto in `[$notNull]`):
```php
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'pagination[page]=2'));
    Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), '[$null]=true')
        && str_contains(urldecode($request->url()), 'pagination[page]=1'));
```

#### 3.4 — Test nuovi (stesso file; aggiungi `use Illuminate\Support\Facades\Queue;`)
```php
it('cards:scan --phase=variants accoda la fase varianti', function () {
    Queue::fake();

    Artisan::call('cards:scan', ['--phase' => 'variants']);

    Queue::assertPushed(ImportCardsFromSwuApiJob::class, fn ($job) => $job->phase === ImportCardsFromSwuApiJob::PHASE_VARIANTS && $job->page === 1);
});

it('cards:scan rifiuta una fase sconosciuta', function () {
    Queue::fake();

    expect(Artisan::call('cards:scan', ['--phase' => 'boh']))->toBe(1);

    Queue::assertNothingPushed();
});

it('un job in coda serializzato prima del piano 14 riparte dalla fase originali', function () {
    $job = (new ReflectionClass(ImportCardsFromSwuApiJob::class))->newInstanceWithoutConstructor();

    expect($job->phase)->toBe(ImportCardsFromSwuApiJob::PHASE_ORIGINALS);
});
```

---

## Step 4 — Unico `(expansion, number)` mantenuto (decisione dell'utente)

Scelta: non togliere l'unico, perché con le sottoespansioni stile SWUDB le collisioni non dovrebbero esistere. Conseguenze da tenere presenti:
- un refuso dei dati (es. `SEC` 571 su due carte) o una collisione imprevista dà `Duplicate entry` su **quella** carta: finisce in un `SystemError`, lo scan continua. Dopo l'import controlla `/admin/errori`.
- l'indice normale `cards_expansion_number_index` aggiunto dalla migration è ora ridondante con l'unico (non dannoso). Se vuoi toglierlo, elimina l'`index(...)` dalla migration e rifai `migrate:fresh` (comunque previsto al 13.4).
- il `down()` della migration `2026_10_28_170709_add_variants_and_drop_collection_variant.php` **non deve** ricreare l'unico (non l'ha mai tolto): rimuovi da lì `$table->unique(['expansion', 'number']);`, altrimenti `migrate:rollback` fallisce con indice duplicato.
- il piano 14 è stato allineato (Step 1.1 senza `dropUnique`).

---

## Step 5 — `DeckGapCalculator`: le varianti contano come copie dell'originale

Decisione dell'utente (nota dello Step 2.5 del piano 14). Esempio: il mazzo chiede 3 × `SOR134`; in collezione 1 × `SOR134` e 1 × `SOROP2` (Hyperspace) → presenti 2, mancanti 1. Le copie impegnate in altri mazzi assemblati restano per carta originale.

`app/Services/DeckGapCalculator.php` (cambiano solo `$required` e `$owned`):
```php
<?php

namespace App\Services;

use App\Models\CollectionCard;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\User;

class DeckGapCalculator
{
    /**
     * Copies missing / reserved elsewhere / present, per ORIGINAL card: the copies of every variant owned by the user count as copies of the original.
     * Copie mancanti / impegnate altrove / presenti, per carta ORIGINALE: le copie di ogni variante possedute dall'utente contano come copie dell'originale.
     *
     * @return array{missing: array<string,int>, reservedElsewhere: array<string,int>, present: array<string,int>}
     */
    public function forDeck(Deck $deck, User $user): array
    {
        // Il mazzo ha solo originali; un mazzo vecchio con l'id di una variante si riconduce comunque all'originale
        $required = $deck->cards
            ->groupBy(fn ($card) => $card->original_card_id ?? $card->id)
            ->map(fn ($cards) => $cards->sum(fn ($card) => (int) $card->pivot->quantity));

        // Copie possedute per carta originale: la riga dell'originale più quelle di tutte le sue varianti
        $owned = CollectionCard::where('collection_cards.user_id', $user->id)
            ->join('cards', 'cards.id', '=', 'collection_cards.card_id')
            ->selectRaw('COALESCE(cards.original_card_id, cards.id) as base_id, SUM(collection_cards.quantity) as qty')
            ->groupByRaw('COALESCE(cards.original_card_id, cards.id)')
            ->pluck('qty', 'base_id');

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
Controller e vista `decks/gap` non cambiano. Test, nuovo file `tests/Feature/Services/DeckGapCalculatorTest.php`:
```php
<?php

use App\Models\CollectionCard;
use App\Models\User;
use App\Services\DeckGapCalculator;

function ownCopies(User $user, $card, int $quantity): void
{
    CollectionCard::create(['user_id' => $user->id, 'card_id' => $card->id, 'quantity' => $quantity]);
}

it('conta le copie delle varianti come copie dell\'originale', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $hyperspace = makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);
    $deck = makeDeck($user, [[$original, 3]]);
    ownCopies($user, $original, 1);
    ownCopies($user, $hyperspace, 1);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['present'])->toEqual([$original->id => 2])
        ->and($gap['missing'])->toEqual([$original->id => 1])
        ->and($gap['reservedElsewhere'])->toBe([]);
});

it('chi ha solo una variante non ha l\'originale tra le mancanti', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $showcase = makeCard(['expansion' => 'SORSH', 'original_card_id' => $original->id, 'variant_type' => 'Showcase']);
    $deck = makeDeck($user, [[$original, 1]]);
    ownCopies($user, $showcase, 1);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['missing'])->toBe([])
        ->and($gap['present'])->toEqual([$original->id => 1]);
});

it('le copie impegnate in un altro mazzo assemblato si sottraggono anche se la copia posseduta è una variante', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $hyperspace = makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);
    makeDeck($user, [[$original, 1]], ['name' => 'Altro mazzo', 'assembled' => true]);
    $deck = makeDeck($user, [[$original, 1]], ['name' => 'Questo mazzo']);
    ownCopies($user, $hyperspace, 1);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['present'])->toBe([])
        ->and($gap['reservedElsewhere'])->toEqual([$original->id => 1])
        ->and($gap['missing'])->toBe([]);
});

it('le copie di un altro utente non contano', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $deck = makeDeck($user, [[$original, 2]]);
    ownCopies($other, $original, 5);

    $gap = app(DeckGapCalculator::class)->forDeck($deck->load('cards'), $user);

    expect($gap['missing'])->toEqual([$original->id => 2]);
});
```
Se `Deck` non ha `assembled` tra i campi riempibili, nel terzo test imposta `Deck::where('name', 'Altro mazzo')->update(['assembled' => true]);` dopo la creazione.

Fuori scope: dettaglio per versione in `decks/gap`, badge "totale copie" nella collezione.

---

## Step 6 — Import locale in corso (operativo)

Il worker girava la versione precedente del job (nei log manca "Fase"): a pagina 32 chiude lo scan senza passare alle varianti, e i segnalini importati finora stanno sotto `SOR` invece di `TSOR`. Il DB locale è provvisorio: il reset di 13.4 (`migrate:fresh --seed` + reimport) rifà tutto col job nuovo. Dopo gli Step 1–2: `docker compose -f docker-compose.dev.yml restart worker`, poi `cards:scan --page=N` (N = prima pagina non importata) oppure, a originali completi, `cards:scan --phase=variants`. Misura la coerenza a fine import:
```bash
docker compose -f docker-compose.dev.yml exec app php artisan tinker --execute="echo App\Models\Card::where('type','like','%Segnalino%')->where('expansion','not like','T%')->count().PHP_EOL;"
```
0 = coerente; > 0 = ripeti dopo `migrate:fresh`.

---

## Step 7 — Verifica e chiusura

1. `php artisan test` da host: nessun fallimento (allega `test.log` se resta del rosso).
2. `/carte`, `/carte/SOR/5`, `/mazzi`, un mazzo, `/collezione`, `/nuove-uscite` rispondono 200; `/carte/SORPR/1` (dopo le varianti) fa 301 all'originale.
3. Tinker dello Step 6.5 del piano 14 stampa i conteggi.
4. Riprendi il piano 14 dagli Step 6–8. README riallineato a codice funzionante (varianti, `original_card_id`, promo stile SWUDB, collezione per singola carta, delta per carta originale, `SWU_VARIANT_IMAGES`, `cards:scan --page --phase`, "Fase" nei messaggi, "Ultime uscite" e "Collezione" con varianti).
5. Rinomina questo file in `implementationPlan-V-14-variantiCarte-Correzione.md` e, con lui, 14a e 14b in `V-`.
