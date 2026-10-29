# Implementation plan 14a — Correzioni all'esecuzione del piano 14 (varianti delle carte)

> **Sostituito dal 2026-10-29 da [`implementationPlan-14-variantiCarte-Correzione.md`](implementationPlan-14-variantiCarte-Correzione.md)**, che riunisce ciò che resta di 14a e 14b. Questo file resta come storico dell'analisi.

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Corregge ciò che è emerso verificando l'applicazione di [`implementationPlan-14-variantiCarte.md`](implementationPlan-14-variantiCarte.md) (Step 1–5 applicati, Step 6–8 non ancora).
> **Va applicato prima di proseguire con gli Step 6–8 del piano 14**, e comunque prima dello Step 13.4 del piano 13.
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.
> Data: 2026-10-28 (sera). Fonti rilette: codice (`Card`, `CardSearch`, `CardController`, `DeckController`, `CollectionController`, `DeckImporter`, `TelegramController`, `ImportCardsFromSwuApiJob`, `ScanCards`, migration, viste, route, test), `test.log` (ultima run: **51 falliti, 76 passati**, 211 asserzioni) e `storage/logs/laravel-2026-10-28.log` (import in corso, pagina 22/32).
> Del `test.log` ho letto la parte finale (una trentina dei 51 fallimenti): le cause trovate sono 4 (sotto). Quelli che non ho visto hanno con ogni probabilità le stesse cause, ma **dopo le correzioni rilancia la suite e mandami il nuovo `test.log`**.

---

## Esito della verifica

| Step del piano 14 | Stato | Note |
|---|---|---|
| 1.1 Migration | applicata, **con una differenza** | Manca `dropUnique(['expansion', 'number'])`: l'indice unico di `create_cards_table` resta. Anche il `down()` è incoerente (ri-aggiunge un unico che `up()` non ha mai tolto). → Step 5 |
| 1.2 `Card` | applicato, **errore** | Manca `use Illuminate\Database\Eloquent\Builder;`: nel namespace `App\Models` il type-hint `Builder` diventa `App\Models\Builder` (inesistente) e `originals()` lancia `TypeError`. È la causa della maggior parte dei test rossi (CardSearch, DeckImporter, DeckExporter, DeckPages, DeckStatistics `edit`, ...) e dell'errore di tinker. Il testo del piano 14 non diceva di aggiungere l'import. → Step 1 |
| 1.3 `Expansion` | ok | |
| 1.4 `CardImageDownloader` | nessuna modifica prevista | |
| 2.1 `CardSearch` | ok | Funziona appena sistemato lo Step 1 |
| 2.2 `newReleases` | invariato **per scelta** | Le "Ultime uscite" mostrano tutte le carte, varianti comprese (nota dell'utente, ora riportata nel piano 14) |
| 2.3 Telegram, 2.4 validazione mazzi, 2.6 `DeckImporter` | ok | |
| 2.5 Collezione | applicato, con **residui `+`** nella vista | `collection/index.blade.php` ha due `+` rimasti dal diff (`value="{{ $owned }}" +` e `data-card="{{ $card->id }}" +`). → Step 6. `DeckGapCalculator` (decisione dell'utente) → piano [`14b`](implementationPlan-14b-deltaCollezioneVarianti.md) |
| 3.1 `CardController::show` | **applicato nel file sbagliato** | Il metodo è finito al posto di `DeckController::show`: `route('decks.show')` risponde 500 (`Argument #2 ($number) must be of type int, string given`), il test `DeckStatisticsTest` e le pagine dei mazzi sono rotti; `CardController::show` è rimasto quello vecchio (nessun redirect delle varianti, nessun `variants` caricato). → Step 2 |
| 3.2 Vista `cards/show` ("Altre versioni") | ok | |
| 3.3 API (`CardResource`, `Api\CardController`) | ok | |
| 4.1–4.6 Job a due fasi | ok | Con una modifica **fuori piano dell'utente**: `Fase {phase}` nel messaggio Telegram di avanzamento e nel `Log::info` per pagina (coerente, la tengo). Un rischio sui job già in coda → Step 4 |
| 4.7 `cards:scan` | applicato **ma inefficace** | La firma ha `--phase`, ma `handle()` non lo passa al job: `cards:scan --phase=variants` (Step 6.4 e 7 del piano 14) lancerebbe la fase *originali*. Errore del piano. → Step 3 |
| 5 Test | **da correggere** | `fakeVariantEntry()` non è definita (il piano 14 la usa senza dichiararla); 'pagina correttamente su piu\' pagine' e 'riprende da una pagina' vanno adeguati alla seconda fase; `CardVariantsTest` non ha gli `use`. → Step 7 |
| 6, 7, 8 | non eseguiti | L'import locale sta girando (vedi Step 8) |

Il resto della suite (76 test) passa, compresi i test del job che non toccano la seconda fase.

---

## Step 1 — `app/Models/Card.php`: import di `Builder`

In cima al file, dopo `use App\Models\Builders\CardBuilder;`:
```php
use Illuminate\Database\Eloquent\Builder;
```
`CardBuilder` estende `Illuminate\Database\Eloquent\Builder`, quindi `scopeOriginals(Builder $query): Builder` accetta il `CardBuilder` che Laravel gli passa (`newEloquentBuilder`). Non serve altro.

Verifica subito, prima di toccare altro:
```bash
docker compose -f docker-compose.dev.yml exec app php artisan tinker --execute="echo App\Models\Card::originals()->count().PHP_EOL;"
```
Deve stampare il numero di carte importate finora (non l'errore).

---

## Step 2 — Rimettere a posto `DeckController::show` e `CardController::show`

Lo Step 3.1 del piano 14 riguarda **`CardController`**, non `DeckController`. Il file `app/Http/Controllers/DeckController.php` ha perso il suo `show()` (visualizzazione del mazzo): va ripristinato.

#### 2.1 — `app/Http/Controllers/DeckController.php`
Sostituisci l'attuale `show(string $expansion, int $number): View|RedirectResponse` (quello con `Card::with([... 'originalCard'])`, che sta sopra `statistics()`) con la versione del mazzo. Ricostruita dal piano 07 (Step 7.6.2, 7.6.5.3, 7.8) e dalla vista `decks/show.blade.php`, che usa `$deck`, `$validationErrors`, `$isLatest` e le variabili delle statistiche:
```php
    /**
     * Visualizzazione in sola lettura (accessibile se mazzo pubblico o se proprietario)
     */
    public function show(string $username, string $deckname, ?int $version = null): View
    {
        $deck = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $deck);

        $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);
        $validationErrors = DeckFormatValidatorFactory::make($deck->format)->validate($deck);
        $isLatest = ! $deck->nextVersion()->exists();

        return view('decks.show', [
            'deck' => $deck,
            'validationErrors' => $validationErrors,
            'isLatest' => $isLatest,
        ] + $this->getDeckStatisticsData($deck));
    }
```
Gli `use` di `DeckController` (`Card`, `RedirectResponse`, `Gate`, `DeckFormatValidatorFactory`, `View`) ci sono già. **Controlla** che non ti manchi altro di quel metodo: se avevi una versione tua diversa (per esempio con `compact(...)` di altre variabili), la vista `decks/show.blade.php` ti dice cosa serve; `git diff app/Http/Controllers/DeckController.php` mostra il `show()` originale.

#### 2.2 — `app/Http/Controllers/CardController.php`
Aggiungi `use Illuminate\Http\RedirectResponse;` e sostituisci `show()` con il codice dello Step 3.1 del piano 14:
```php
    public function show(string $expansion, int $number): View|RedirectResponse
    {
        $query = Card::with(['aspects', 'traits', 'expansionModel', 'originalCard'])
            ->where('expansion', $expansion)
            ->where('number', $number);

        $card = (clone $query)->originals()->first() ?? $query->firstOrFail();

        // Se l'URL puntava a una variante/promo, rimanda all'originale
        if ($card->original_card_id !== null) {
            $original = $card->originalCard;

            return redirect()->route('cards.show', ['expansion' => $original->expansion, 'number' => $original->number], 301);
        }

        $card->load(['variants' => fn ($q) => $q->orderBy('variant_code')->orderBy('expansion')->orderBy('number')]);

        return view('cards.show', compact('card'));
    }
```
Il resto di `CardController` (compreso `newReleases`) non cambia: "Ultime uscite" mostra tutte le carte, anche le varianti, come voluto. Un click su una variante da lì porta all'originale (redirect 301), dove la variante compare nella galleria "Altre versioni".

Verifica: `/mazzi`, `/mazzo/{utente}/{mazzo}`, `/carte`, `/carte/SOR/5` rispondono 200; `/carte/SORPR/1` (dopo la fase varianti) fa 301 verso l'originale.

---

## Step 3 — `cards:scan --phase`: passarla al job

`app/Console/Commands/ScanCards.php`: `handle()` oggi ignora l'opzione. Sostituiscilo:
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
Significato delle opzioni:
- `cards:scan` = fase originali dalla pagina 1, poi passa da sola alle varianti.
- `cards:scan --page=N` = riprende la fase originali dalla pagina N (e poi passa alle varianti).
- `cards:scan --phase=variants [--page=N]` = solo le varianti (dalla pagina N); richiede che gli originali siano già a DB (altrimenti ogni variante dà "Carta originale ... non trovata").

Se interrompi gli originali a metà, riprendi **sempre** con `--page=N` (fase originali), non con `--phase=variants`.

---

## Step 4 — `ImportCardsFromSwuApiJob`: default di `$phase` per i job già in coda

Con `public string $phase` come parametro promosso del costruttore (Step 4.1 del piano 14) la proprietà non ha un valore di default: un job **serializzato prima della modifica** e ripreso da un worker col codice nuovo lancia `must not be accessed before initialization` e fallisce tre volte (poi `failed()` ferma lo scan). Succede, per esempio, se riavvii il worker mentre in coda ci sono già le pagine successive dell'import in corso. Dichiara la proprietà con il suo default e togli la promozione:
```php
    /** Fase dello scan: il default permette ai job messi in coda prima del piano 14 di ripartire come "originals" */
    public string $phase = self::PHASE_ORIGINALS;

    public function __construct(public int $page = 1, ?string $runId = null, string $phase = self::PHASE_ORIGINALS)
    {
        $this->phase = $phase;
        $this->startsRun = $runId === null;
        $this->runId = $runId ?? (string) Str::uuid();
    }
```
(La dichiarazione `public string $phase` va messa accanto alle altre proprietà della classe, prima del costruttore.)

---

## Step 5 — Migration: indice unico `(expansion, number)`

`create_cards_table` ha `unique(['expansion', 'number'])`. Il piano 14 (Step 1.1) lo toglie lasciando un indice normale: con la divisione stile SWUDB le collisioni spariscono per costruzione, ma un refuso dei dati (`SEC` 571 con due carte) o un caso non previsto darebbero ancora `Duplicate entry` su quella singola carta (un `SystemError`, non l'arresto dello scan). La migration applicata ha creato l'indice normale `cards_expansion_number_index` ma **non** ha tolto l'unico.

Scegli una delle due:

**A) Il DB locale va mantenuto** (import in corso, vedi Step 8): lascia com'è la migration già eseguita e aggiungi una migration nuova, `database/migrations/2026_10_29_090000_drop_unique_expansion_number_from_cards.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drops the unique (expansion, number): the plain index cards_expansion_number_index (previous migration) already
     * supports the foreign key on `expansion`, so the drop is allowed.
     * Toglie l'unico (expansion, number): l'indice normale cards_expansion_number_index (migration precedente) sostiene già la FK su `expansion`.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique(['expansion', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unique(['expansion', 'number']);
        });
    }
};
```
e nel `down()` della migration `2026_10_28_170709_add_variants_and_drop_collection_variant.php` togli la riga `$table->unique(['expansion', 'number']);` (quella migration non l'ha mai tolto, quindi il suo rollback non deve rimetterlo: altrimenti un `migrate:rollback` completo fallisce con indice duplicato).
Esegui la migration con il worker fermo o tra una pagina e l'altra: `ALTER` su `cards` aspetta i lock delle scritture dell'import.

**B) Reset pulito** (`migrate:fresh`, che il piano 13 Step 13.4 prevede comunque): aggiungi `$table->dropUnique(['expansion', 'number']);` subito dopo `$table->index(['expansion', 'number'], 'cards_expansion_number_index');` nella migration applicata (l'ordine conta: prima l'indice che sostiene la FK, poi il drop) e **non** creare la migration nuova.

Verifica: `SHOW INDEX FROM cards;` non elenca più `cards_expansion_number_unique` (resta `cards_expansion_number_index`).

---

## Step 6 — `resources/views/collection/index.blade.php`: due `+` rimasti

Nel blocco `<input type="number" ...>` ci sono due `+` a fine riga (resti del diff del piano). Il browser li legge come attributi senza senso: tolti, il blocco è:
```blade
                            <input type="number" min="0" max="65535" value="{{ $owned }}"
                                data-collection data-card="{{ $card->id }}"
                                class="mt-1 w-full rounded border-gray-300 text-center text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
```
Il resto della vista e lo script (senza `data-variant`) sono corretti. Nota: nei `console.log` dello script ci sono ancora i messaggi di debug (non del piano 14).

---

## Step 7 — Test

#### 7.1 — `fakeVariantEntry()` in `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`
Il test 'assegna le promo dei primi set alle sottoespansioni stile swudb' (Step 5.2 del piano 14) la usa ma il piano non la definiva. Aggiungila subito dopo `fakeCardEntry()`:
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
`'2579145458'` è il `cardUid` dell'originale di `fakeCardEntry()` (Luke Skywalker, già usato dal test 'non manda la mail agli admin per una carta gia\' presente').

#### 7.2 — 'pagina correttamente su piu' pagine'
Ora lo scan fa tre richieste (due pagine di originali + la prima delle varianti) e il test falliva con `OutOfBoundsException: A request was made, but the response sequence is empty`. Il client HTTP di Laravel interroga **tutti** gli stub che combaciano con l'URL e poi prende il primo non nullo: la richiesta della fase varianti combacia anche con la sequenza di `card-list*` e la svuota, anche se a rispondere è lo stub `*notNull*`. Quindi la sequenza deve avere un terzo elemento, e le richieste registrate sono 3:
```php
it('pagina correttamente su piu\' pagine', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::sequence()
            ->push(['data' => [fakeCardEntry()], 'meta' => ['pagination' => ['pageCount' => 2]]])
            ->push(['data' => [fakeCardEntry(['attributes' => ['cardUid' => '1111111111', 'cardNumber' => 12]])], 'meta' => ['pagination' => ['pageCount' => 2]]])
            // la richiesta della fase varianti consuma questo elemento (la risposta vera arriva da fakeSwuHttp, `*notNull*`)
            ->push(['data' => [], 'meta' => ['pagination' => ['pageCount' => 0]]]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'card-list')))->toHaveCount(3); // 2 pagine di originali + 1 di varianti
    expect(Card::count())->toBe(2);
});
```

#### 7.3 — 'riprende da una pagina con cards:scan --page'
Dopo la pagina 2 (l'ultima) la fase varianti parte dalla pagina 1 per costruzione, quindi `assertNotSent(... page=1)` era sbagliato. Va ristretto alla fase originali (filtro `[$null]`, che non è contenuto in `[$notNull]`):
```php
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'pagination[page]=2'));
    // la fase originali non riparte dalla pagina 1 (quella delle varianti sì, è voluto)
    Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), '[$null]=true')
        && str_contains(urldecode($request->url()), 'pagination[page]=1'));
```

#### 7.4 — Test nuovi (stesso file; serve `use Illuminate\Support\Facades\Queue;` in testa)
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
    // unserialize() non esegue il costruttore: la proprietà deve avere un default
    $job = (new ReflectionClass(ImportCardsFromSwuApiJob::class))->newInstanceWithoutConstructor();

    expect($job->phase)->toBe(ImportCardsFromSwuApiJob::PHASE_ORIGINALS);
});
```

#### 7.5 — `tests/Feature/Cards/CardVariantsTest.php`: gli `use`
I file Pest non importano nulla da soli; il file del piano 14 (Step 5.3) usa `User`, `DeckImporter`, `DeckFormat` e `CollectionCard` senza dichiararli. In testa al file:
```php
<?php

use App\Enums\DeckFormat;
use App\Models\CollectionCard;
use App\Models\User;
use App\Services\DeckImporter;
```
(`makeCard()` e `makeDeck()` sono helper globali di `tests/Pest.php`.)

#### 7.6 — Esegui
```bash
php artisan test
```
da host, non nel container (come da nota in `todo.md`). Atteso: nessun `TypeError` su `originals()`, nessun 500 su `decks.show`. Allega il nuovo `test.log` se resta qualcosa di rosso.

---

## Step 8 — Import locale in corso (operativo, nessun codice)

Dal log del 2026-10-28: scan `b5170eb9-…`, pagina **22/32** elaborata alle 19:33, picco di memoria 88–104 MB (limite del worker `--memory=100`). Due cose da sapere:

1. **Il worker sta ancora eseguendo la versione precedente del job**: le righe di log non riportano "Fase originals", che il file su disco invece scrive. `queue:work` carica il codice una volta sola e il container dev non ricarica il job da solo. Conseguenze:
   - a pagina 32 il vecchio codice chiude lo scan (`finish()`) **senza passare alla fase varianti**: le varianti vanno lanciate a parte con `cards:scan --phase=variants` (Step 3, e con Step 1–2 già applicati);
   - i segnalini (`type` che contiene "Segnalino") importati dal vecchio job hanno `expansion` uguale al codice dell'API (es. `SOR`), mentre il job nuovo li metterebbe in `TSOR`/`TSHD`/...; e un `updateOrCreate` successivo cambia `expansion` ma **non** `cards.id` (l'id si imposta solo in `creating`): mescolare le due versioni nello stesso DB lascia ID e coppia espansione+numero incoerenti.
2. Se riavvii il worker con i job già in coda, senza lo Step 4 le pagine rimaste falliscono per `phase` non inizializzata.

Cosa fare, in ordine:
- Lascia finire gli originali (mancano 10 pagine) **oppure** fermati e riparti da `--page=N` dopo aver applicato gli Step 1–4 e riavviato il worker (`docker compose -f docker-compose.dev.yml restart worker`). Se ti chiede di ripartire dalla pagina N, `cards:scan --page=N` riprende la fase originali.
- Tratta questo DB come **provvisorio**: il reset pulito dello Step 13.4 del piano 13 (`migrate:fresh --seed` + reimport completo) rifà tutto col job nuovo. Prima di farlo ti serve solo per provare il resto (liste, mazzi, scheda, API).
- Per misurare quanto è sporco, a import finito:
```bash
docker compose -f docker-compose.dev.yml exec app php artisan tinker --execute="echo App\Models\Card::where('type','like','%Segnalino%')->where('expansion','not like','T%')->count().' segnalini non in T*'.PHP_EOL;"
```
Se è 0 il DB è coerente anche con la divisione in sottoespansioni; se è > 0, ripeti l'import dopo un `migrate:fresh` (o aspetta il 13.4).

---

## Step 9 — Verifica finale e chiusura

1. Suite verde (`php artisan test`, da host).
2. `/carte`, `/carte/SOR/5`, `/mazzi`, un mazzo (`decks.show`), `/collezione`, `/nuove-uscite` rispondono 200.
3. Il tinker dello Step 6.5 del piano 14 stampa i conteggi (originali e varianti) senza errore.
4. Poi **riprendi il piano 14 dallo Step 6** (esecuzione in locale) e dallo Step 7 (VM); il piano 14 si rinomina in `implementationPlan-V-14-variantiCarte.md` solo quando sono chiusi anche questo piano e il [14b](implementationPlan-14b-deltaCollezioneVarianti.md).
5. **README**: aggiornalo a correzioni finite (non prima: oggi descriverebbe codice che non funziona). Va riportato: `cards.original_card_id`/`variant_type`/`variant_code`, `Card::originals()`, divisione delle promo stile SWUDB e `group_main_expansion`, collezione per singola carta (`collection_cards` senza `variant`, unico `(user_id, card_id)`), scheda con "Altre versioni" e redirect 301 delle varianti, `SWU_VARIANT_IMAGES`, `cards:scan --page=N --phase=...`, import a due fasi con "Fase" nel messaggio Telegram, "Ultime uscite" e "Collezione" che mostrano anche le varianti.
6. `todo.md`: sezione 14a e 14b, spunte a lavoro finito (le ho già aggiunte con lo stato di oggi).
7. Rinomina questo file in `implementationPlan-V-14a-correzioniVarianti.md`.
