# Implementation plan 14 — Varianti delle carte e Set Promozionali (una sola carta in lista, tutte le versioni a DB, divisione stile SWUDB)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Lavora sul codice già consegnato dai piani 05 (catalogo), 07 (mazzi), 08 (collezione), 10 (API) e 12 (import per pagina).
> **Va eseguito prima dello Step 13.4 del piano 13** ([`implementationPlan-13-ricostruzioneDeploy.md`](implementationPlan-13-ricostruzioneDeploy.md)): il reset pulito e il reimport completo devono già importare anche le varianti e i set promozionali.
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.
> Data: 2026-10-28. Aggiornato dopo l'analisi del log reale `storage/logs/laravel-2026-10-28.log` (103 pagine complete scansionate) e la consultazione della struttura dei set di SWUDB ([`swudb.com/sets`](https://swudb.com/sets)).

---

## Obiettivo

Oggi l'import chiede all'API solo le carte con `filters[variantOf][id][$null]=true`, cioè solo gli originali standard: tutte le versioni alternative (Hyperspace, Showcase, Foil) e le carte promozionali (Weekly Play, Prerelease, Store Showdown, Planetary Qualifier, Convention, Judge) si perdono. Vogliamo:

1. importarle **tutte** (10.286 carte complessive, togliendo il filtro agli originali);
2. risolvere il disordine dei dati di partenza dell'API organizzando le promo in **espansioni dedicate stile SWUDB** (es. `SORPR`, `SOROP`, `SORSH`, `SHDPQ`... e le ufficiali `JTLP`, `SECP`...), collegate al set base tramite `expansions.group_main_expansion`;
3. salvare a DB per ogni variante il **riferimento alla carta originale** (`original_card_id`, `variant_type`, `variant_code`);
4. mostrare **una sola carta** per ogni gruppo nelle liste pubbliche (una sola Ahsoka, non quattro), con la galleria di tutte le altre versioni (Hyperspace, Showcase, promo) consultabile nella scheda dell'originale;
5. gestire gli effetti collaterali (mazzi, import mazzi da SWUDB, collezione, API, spazio disco con interruttore immagini).

---

## Cosa sappiamo dell'API e confronto con SWUDB

### Dati dell'API Strapi (confermati dal log del 28/10/2026 e test reali)
- **Dimensioni**: 10.286 carte totali (103 pagine da 100). Gli originali standard sono 3.135 (32 pagine); le restanti **7.151 carte** sono varianti e promozioni (circa 2,3 per ogni carta base).
- **Perché nel log del 28/10 ci sono stati 1.485 errori di duplicate entry:**
  Nei primi tre set (`SOR`, `SHD`, `TWI`), la fonte dati ufficiale ha lasciato molte carte promozionali con il codice dell'espansione base (`expansion = SOR`, `SHD`, `TWI`), ma con un `cardNumber` proprio che **riparte da 1, 2, 3...** per ogni circuito torneistico (*Weekly Play*, *Prerelease*, *Store Showdown*, *Planetary Qualifier*). Ad esempio, nel solo set `SHD`, il numero `2` era condiviso da 12 carte diverse (la leader Qi'ra originale, il Take Captive Weekly Play, il Dottor Evazan Top 16, l'Offerta Irrifiutabile Top 8, il Mandaloriano Judge, ecc.).
- **Dai set 4 in poi (`JTL`, `LOF`, `SEC`, `LAW`, `HMW`, `ASH`):**
  La stessa Asmodee/FFG ha corretto la gestione introducendo codici di espansione dedicati con suffisso `P` (`JTLP`, `LOFP`, `SECP`...).
- **Casi limite riscontrati nell'API:**
  - *Espansione nulla:* la promo di Ammiraglio Motti (`3466226801`) arriva con `"expansion": { "data": null }` e `serialCode: 0102007`. Va recuperata l'espansione dall'originale o dal seriale.
  - *`variantTypes` vuoto:* alcune varianti promo Foil (come Mina Bonteri `9380836896`) hanno `variantTypes: { "data": [] }` e solo `hasFoil: true`.
  - *Refusi di numerazione in Strapi:* in `SEC`, due carte diverse hanno entrambe ricevuto il numero `571` per errore nei dati di Strapi (`Willrow Hood` seriale 0571 e `Ornitottero Bardottano` seriale 0572).

### Come SWUDB organizza i set ([`swudb.com/sets`](https://swudb.com/sets))
Interrogando l'endpoint `/api/card/getAllSets` di SWUDB, emerge la struttura canonica adottata dalla community:
1. **Core Sets:** `SOR`, `SHD`, `TWI`, `JTL`, `LOF`, `SEC`, `LAW`, `HMW`, `ASH` (contengono le stampe Normal, Hyperspace e Showcase di bustina).
2. **Child Sets Tokens:** `T{SET}` (`TSOR`, `TSHD`, `TTWI`, `TJTL`...).
3. **Child Sets Promo per i primi 3 set:**
   - **Prerelease Promos:** `SORPR`, `SHDPR`, `TWIPR` (le carte promo con timbro prerelease, es. Luke e Vader).
   - **Organized Play / Weekly:** `SOROP`, `SHDOP`, `TWIOP` (carte promo settimanali dei negozi e gli Hyperspace del 2-Player Starter Set di SOR).
   - **Store Showdown:** `SORSH`, `SHDSH`, `TWISH` (carte premio dei tornei Store Showdown).
   - **Planetary Qualifier:** `SHDPQ`, `TWIPQ` (carte premio dei tornei Planetary Qualifier).
   - **Eventi speciali:** `GC23` (*GenCon 2023*), `EE24` (*Essen Spiel 2024*).
4. **Promo Sets Ufficiali (Set 4+):** `JTLP`, `LOFP`, `SECP`, `LAWP`, `HMWP`, `ASHP`.
5. **Set Promozionali Annuali/Indipendenti:** `C24`, `C25`, `C26` (Convention), `J24`, `J25` (Judge), `P25`, `P26` (Promo annuali), `CST` (Costco), `G25` (Gift Box), `IBH` (Intro Battle Hoth).

Separando le carte secondo questo standard SWUDB, **tutte le collisioni di numerazione spariscono**, ogni carta ha una collocazione precisa e i mazzi esportati da SWUDB vengono riconosciuti al 100%.

---

## Scelte di progetto

| Scelta | Decisione | Perché |
|---|---|---|
| Come si chiama il riferimento | colonna `cards.original_card_id`, relazioni `Card::originalCard()` / `Card::variants()`, scope `Card::originals()` | Evita "base" che colliderebbe con il tipo di carta `Base` e `Deck::baseCard()`. |
| Dove punta | `cards.id` della carta originale | Stesso riferimento usato dalle pivot; se l'originale venisse cancellato, la variante si elimina in cascata. |
| Risoluzione espansione promo | Metodo `resolveExpansionCode()` nel job di import | Intercetta le promo dei set 1-3 e le assegna alle sotto-espansioni figlie (`SORPR`, `SOROP`, `SORSH`, `SHDPQ`...) con `group_main_expansion` valorizzato. |
| Chiave primaria `cards.id` | `{expansion}{number}` per gli originali; `{expansion}{number}-{cid}` per le varianti | Garantisce univocità assoluta anche in caso di refusi dell'API (es. SEC 571). La colonna `id` e le FK collegate vengono portate a `VARCHAR(36)`. |
| Indice `(expansion, number)` | Indice normale (non unique) | Mantiene le performance delle query senza fallire in caso di errori di battitura nei numeri da parte di Strapi. |
| Importazione | **Due fasi** nello stesso scan: prima gli originali, poi le varianti | Quando arriva una variante, l'originale esiste già a DB e il collegamento si risolve con una semplice query `cid`. |
| Liste e Ricerca | Filtro esplicito `originals()` (`whereNull('original_card_id')`) | Nessun global scope: il catalogo mostra solo gli originali, mentre le schede dettaglio possono caricare le varianti. |
| Mazzi | Un mazzo contiene **sempre l'originale** | Il limite delle 3 copie opera sulla carta originale. L'import di un mazzo con codici SWUDB (es. `SOROP_1`, `SORPR_2`) li riconduce automaticamente all'originale. |
| Collezione | Invariata: quantità per finitura (`normal`, `foil`, `hyper`...) sulla carta originale | L'eventuale associazione visiva 1:1 tra la collezione e le singole carte-variante importate è rimandata a un piano successivo. |
| Immagini | Interruttore `SWU_VARIANT_IMAGES` in configurazione | Circa 7.151 varianti pesano ~1,5 GB: permette di decidere se scaricare subito le immagini o solo i dati. |

---

## Step 0 — Verifica preliminare dell'API (facoltativa)

Se vuoi verificare rapidamente la risposta dell'API per i primi set prima di iniziare, lancia da PowerShell:
```powershell
$r = Invoke-RestMethod -Uri 'https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[cardNumber][$eq]=2&pagination[pageSize]=20'
$r.data | Select-Object -Property @{N='CID';E={$_.attributes.cardUid}}, @{N='Title';E={$_.attributes.title}}, @{N='Exp';E={$_.attributes.expansion.data.attributes.code}}, @{N='Serial';E={$_.attributes.serialCode}}, @{N='vType';E={$_.attributes.variantTypes.data[0].attributes.name}} | Format-Table -AutoSize
```
Vedrai le diverse carte con numero 2 e i rispettivi seriali/tipi promozionali.

---

## Step 1 — Schema e modello

#### 1.1 — Migration `database/migrations/2026_10_28_090000_add_original_card_id_and_expand_card_id.php`
Crea una migration che:
1. Allarga `cards.id` a `VARCHAR(36)` (e aggiorna le tabelle pivot collegate).
2. Aggiunge le colonne `original_card_id`, `variant_type`, `variant_code`.
3. Sostituisce l'indice unique `['expansion', 'number']` con un indice normale.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds variant fields and widens card IDs to accommodate variant identifiers safely.
     * Aggiunge i campi per le varianti e allarga gli ID per gestire gli identificatori delle varianti.
     */
    public function up(): void
    {
        // 1. Allargamento FK nelle tabelle collegate
        Schema::table('card_aspect', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->string('card_id', 36)->change();
        });

        Schema::table('card_trait', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->string('card_id', 36)->change();
        });

        Schema::table('deck_cards', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->string('card_id', 36)->change();
        });

        Schema::table('collection_cards', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->string('card_id', 36)->change();
        });

        // 2. Modifiche sulla tabella cards
        Schema::table('cards', function (Blueprint $table) {
            $table->string('id', 36)->change();

            // Sostituzione indice unique con indice normale
            $table->index(['expansion', 'number'], 'cards_expansion_number_index');
            $table->dropUnique(['expansion', 'number']);

            // Nuovi campi variante
            $table->string('original_card_id', 36)->nullable()->after('cid')
                ->comment('cards.id della carta originale di cui questa è una variante. NULL = è lei l\'originale.');
            $table->string('variant_type', 50)->nullable()->after('original_card_id')
                ->comment('Nome della variante (Hyperspace, Showcase, Weekly Play...). NULL per gli originali.');
            $table->string('variant_code', 10)->nullable()->after('variant_type')
                ->comment('Codice variante dell\'API (02 = Hyperspace, 03 = Showcase, 04 = Prerelease...). NULL per gli originali.');

            $table->foreign('original_card_id')->references('id')->on('cards')->cascadeOnDelete();
        });

        // 3. Ripristino vincoli FK
        Schema::table('card_aspect', fn (Blueprint $table) => $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete());
        Schema::table('card_trait', fn (Blueprint $table) => $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete());
        Schema::table('deck_cards', fn (Blueprint $table) => $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete());
        Schema::table('collection_cards', fn (Blueprint $table) => $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete());
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['original_card_id']);
            $table->dropColumn(['original_card_id', 'variant_type', 'variant_code']);
            $table->unique(['expansion', 'number']);
            $table->dropIndex('cards_expansion_number_index');
        });
    }
};
```

#### 1.2 — Modello `app/Models/Card.php`
Aggiorna `$fillable`, il boot dell'ID e aggiungi le relazioni e lo scope:

```diff
     protected static function booted(): void
     {
         static::creating(function (Card $card) {
-            $card->id = "{$card->expansion}{$card->number}";
+            // Se è una variante, il suffisso con cid garantisce unicità assoluta anche per refusi API
+            $id = "{$card->expansion}{$card->number}";
+            if ($card->original_card_id !== null) {
+                $id .= "-{$card->cid}";
+            }
+            $card->id = $id;
         });
     }
```

Aggiungi nei campi `$fillable`:
```diff
         'expansion',
         'number',
         'cid',
+        'original_card_id',
+        'variant_type',
+        'variant_code',
         'unique_card',
```

Aggiungi i metodi di relazione e lo scope:
```php
    /**
     * Original card this card is a variant of (null when this card is the original)
     * Carta originale di cui questa è una variante (null se è lei l'originale)
     */
    public function originalCard()
    {
        return $this->belongsTo(Card::class, 'original_card_id');
    }

    /**
     * Alternative versions (Hyperspace, Showcase, Promos...) of this card
     * Versioni alternative (Hyperspace, Showcase, Promo...) di questa carta
     */
    public function variants()
    {
        return $this->hasMany(Card::class, 'original_card_id');
    }

    /**
     * Only original cards: the ones that appear in lists, decks and standard views
     * Solo le carte originali: quelle che compaiono in liste, mazzi e catalogo
     */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('cards.original_card_id');
    }

    public function isVariant(): bool
    {
        return $this->original_card_id !== null;
    }
```

#### 1.3 — Modello `app/Models/Expansion.php`
Aggiungi la relazione verso l'espansione principale e le sotto-espansioni:
```php
    public function mainExpansion()
    {
        return $this->belongsTo(Expansion::class, 'group_main_expansion', 'expansion');
    }

    public function subExpansions()
    {
        return $this->hasMany(Expansion::class, 'group_main_expansion', 'expansion');
    }
```

#### 1.4 — Servizio immagini `app/Services/CardImageDownloader.php`
Aggiorna il percorso di salvataggio immagini per usare `id` pulito e deterministico:
```diff
-        $path = "cards/{$card->expansion}/{$card->number}-{$side}.{$ext}";
+        // Le immagini usano l'espansione assegnata (es. cards/SOROP/1-front.png o cards/SOR/260-front.png)
+        // Se l'id ha un suffisso cid (variante sovrapposta), usa la parte dell'id per non sovrascrivere file
+        $subPath = Str::after($card->id, $card->expansion);
+        $path = "cards/{$card->expansion}/{$subPath}-{$side}.{$ext}";
```

---

## Step 2 — Liste, Mazzi e Collezione (solo originali)

Ogni query rivolta all'utente finale deve lavorare solo sulle carte originali.

#### 2.1 — `app/Services/CardSearch.php`
In `apply()`, `filterOptions()` e `getBounds()`:
```diff
-        return $query
+        return $query
+            ->originals()
             ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
```
```diff
-            'types' => Card::query()->distinct()->orderBy('type')->pluck('type'),
+            'types' => Card::originals()->distinct()->orderBy('type')->pluck('type'),
```
```diff
-            $boundsRaw = Card::query()->toBase()->selectRaw('
+            $boundsRaw = Card::originals()->toBase()->selectRaw('
```
*(Ricorda `php artisan cache:forget cards_bounds` dopo il deploy).*

#### 2.2 — `app/Http/Controllers/CardController.php`, `newReleases()`
```diff
-        $cards = Card::where('release_date', '>=', $since)
+        $cards = Card::originals()->where('release_date', '>=', $since)
```
E lo stesso per `$maxDate`, `$firstRelease`, `$lastRelease` con `Card::originals()`.

#### 2.3 — `app/Http/Controllers/TelegramController.php`, `handleSearch()`
```diff
-        $card = Card::where('name', 'like', "%{$query}%")->first();
+        $card = Card::originals()->where('name', 'like', "%{$query}%")->first();
```

#### 2.4 — Validazione Mazzi (`DeckController.php`)
Un mazzo accetta solo id di carte originali:
In `syncCards()` e `addCard()`:
```diff
-            'cards.*.card_id' => ['required_with:cards', 'exists:cards,id'],
+            'cards.*.card_id' => ['required_with:cards', Rule::exists('cards', 'id')->whereNull('original_card_id')],
```
```diff
-            'card_id' => ['required', 'exists:cards,id'],
+            'card_id' => ['required', Rule::exists('cards', 'id')->whereNull('original_card_id')],
```

#### 2.5 — Validazione Collezione (`CollectionController.php`)
In `update()`:
```diff
-            'card_id' => ['required', 'exists:cards,id'],
+            'card_id' => ['required', Rule::exists('cards', 'id')->whereNull('original_card_id')],
```

#### 2.6 — `app/Services/DeckImporter.php`: supporto codici SWUDB e conversione varianti
Chi esporta un mazzo da SWUDB potrebbe avere codici di espansioni promozionali (es. `SOROP_1`, `SORPR_2`) o codici standard (`SOR_134`):
1. In `findById()`: cerca prima l'originale, e se non c'è cerca tra tutte le carte.
2. In `findByText()`: prioritizza gli originali.
3. In `persist()`: se la carta trovata è una variante promozionale, la riconduce al suo `original_card_id`.

```php
    private function findById(string $id): ?Card
    {
        if (! preg_match('/^([A-Za-z0-9]+)_(\d+)$/', $id, $m)) {
            return null;
        }

        $exp = strtoupper($m[1]);
        $num = (int) $m[2];

        // Prima cerca tra gli originali; se è un codice promo (es. SOROP_1 o JTLP_1), trova la variante
        return Card::originals()->where('expansion', $exp)->where('number', $num)->first()
            ?? Card::where('expansion', $exp)->where('number', $num)->first();
    }
```

In `findByText()`:
```diff
         if (preg_match('/^([A-Za-z0-9]+)-(\d+)$/', $name, $m)) {
-            return Card::where('expansion', strtoupper($m[1]))->where('number', (int) $m[2])->first();
+            $exp = strtoupper($m[1]);
+            $num = (int) $m[2];
+            return Card::originals()->where('expansion', $exp)->where('number', $num)->first()
+                ?? Card::where('expansion', $exp)->where('number', $num)->first();
         }

-        return Card::query()
+        return Card::originals()
              ->where('name', $name)
```

In `persist()`:
```diff
              } elseif ($row['count'] > 0) {
-                 $quantities[$row['card']->id] = ($quantities[$row['card']->id] ?? 0) + $row['count'];
+                 // Se il file conteneva l'id di una variante/promo, si riconduce sempre all'originale
+                 $cardId = $row['card']->original_card_id ?? $row['card']->id;
+                 $quantities[$cardId] = ($quantities[$cardId] ?? 0) + $row['count'];
              }
```

---

## Step 3 — Scheda carta e API

#### 3.1 — `CardController::show()`
Se si accede con l'URL di una variante (es. `/carte/SORPR/1`), si viene reindirizzati con 301 alla scheda della carta originale. L'originale carica e mostra le sue varianti ordinate per codice variante e numero.
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

#### 3.2 — Vista `resources/views/cards/show.blade.php`
Dopo il blocco "Abilità Da Schierato" (all'interno di `div.card-ability`), aggiungi la sezione "Altre versioni":
```blade
                @if ($card->variants->isNotEmpty())
                    <x-bladewind.card title="Altre versioni" has_shadow="false" radius="medium"
                        class="bg-white dark:bg-gray-800 m-4">
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                            @foreach ($card->variants as $variant)
                                <figure class="text-center text-sm text-gray-800 dark:text-gray-200">
                                    @if ($variant->front_art_path)
                                        <img src="{{ Storage::url($variant->front_art_path) }}"
                                            alt="{{ $variant->name }} ({{ $variant->variant_type }})"
                                            class="mx-auto block h-auto w-full rounded-lg" loading="lazy">
                                    @else
                                        <div
                                            class="flex aspect-[5/7] w-full items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                            Immagine non disponibile
                                        </div>
                                    @endif
                                    <figcaption class="mt-1">
                                        <span class="font-medium">{{ $variant->variant_type ?? 'Variante' }}</span><br>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $variant->expansion }}-{{ $variant->number }}</span>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </x-bladewind.card>
                @endif
```

#### 3.3 — API: `CardResource.php` e `Api\CardController`
In `CardResource::toArray()`, aggiungi i metadati della variante:
```php
            'variant_type' => $this->variant_type,
            'variant_code' => $this->variant_code,
            'original' => $this->whenLoaded('originalCard', fn () => $this->originalCard ? [
                'cid' => $this->originalCard->cid,
                'expansion' => $this->originalCard->expansion,
                'number' => $this->originalCard->number,
            ] : null),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'cid' => $variant->cid,
                'expansion' => $variant->expansion,
                'number' => $variant->number,
                'variant_type' => $variant->variant_type,
                'front_image' => $variant->front_art_path ? asset('storage/'.$variant->front_art_path) : null,
                'back_image' => $variant->back_art_path ? asset('storage/'.$variant->back_art_path) : null,
            ])),
```

In `Api\CardController::show()` carica le relazioni e dai precedenza agli originali:
```php
    public function show(string $expansion, int $number): CardResource
    {
        $query = Card::with(['aspects', 'traits', 'originalCard', 'variants'])
            ->where('expansion', $expansion)->where('number', $number);

        $card = (clone $query)->originals()->first() ?? $query->firstOrFail();

        return new CardResource($card);
    }
```

---

## Step 4 — Importazione con normalizzazione set promozionali (stile SWUDB)

#### 4.1 — Costanti e proprietà in `ImportCardsFromSwuApiJob.php`
```diff
     public const API_URL = 'https://admin.starwarsunlimited.com/api/card-list';

+    public const PHASE_ORIGINALS = 'originals';
+    public const PHASE_VARIANTS = 'variants';

     public const PAGE_SIZE = 100;
```
```diff
-    public function __construct(public int $page = 1, ?string $runId = null)
+    public function __construct(public int $page = 1, ?string $runId = null, public string $phase = self::PHASE_ORIGINALS)
```

#### 4.2 — Risoluzione dell'espansione promo (stile SWUDB)
Aggiungi in `ImportCardsFromSwuApiJob.php` il metodo privato di risoluzione che normalizza le carte promozionali:

```php
    /**
     * Resolves the canonical expansion code for a card, separating promo sets like SWUDB
     * Risolve il codice espansione canonico per una carta, separando i set promo come fa SWUDB
     */
    private function resolveExpansionCode(array $cardData): string
    {
        $rawExp = $cardData['expansion']['data']['attributes']['code'] ?? null;
        $vTypeName = $cardData['variantTypes']['data'][0]['attributes']['name'] ?? '';
        $vId = $cardData['variantTypes']['data'][0]['attributes']['variantId'] ?? '';
        $serial = $cardData['serialCode'] ?? '';
        $cardNum = (int) ($cardData['cardNumber'] ?? 0);

        // 1. Segnalini (Tokens): T{EXP}
        if (str_contains($cardData['type']['data']['attributes']['name'] ?? '', 'Segnalino')) {
            return 'T' . ($rawExp ?: 'SOR');
        }

        // 2. Set promo già ufficiali o standalone (JTLP, SECP, LOFP, C24, J24, P25, etc.)
        if ($rawExp && ! in_array($rawExp, ['SOR', 'SHD', 'TWI'], true)) {
            return $rawExp;
        }

        // 3. Determinazione del set genitore (fallback se rawExp è null come per Ammiraglio Motti)
        $parentSet = $rawExp;
        if (! $parentSet) {
            $parentSet = match (true) {
                str_starts_with($serial, '01') => 'SOR',
                str_starts_with($serial, '02') => 'SHD',
                str_starts_with($serial, '03') => 'TWI',
                default => 'SOR',
            };
        }

        // 4. Mappatura promozionale stile SWUDB per i primi 3 set (SOR, SHD, TWI):

        // Prerelease (vId 04 o nome Prerelease) -> SORPR, SHDPR, TWIPR
        if ($vId === '04' || str_contains($vTypeName, 'Prerelease')) {
            if (str_contains($vTypeName, 'Judge')) {
                return $parentSet === 'SOR' ? 'J24' : 'J25';
            }
            return "{$parentSet}PR";
        }

        // Planetary Qualifier (vId 51, 52, 56 o sigla PQ) -> SHDPQ, TWIPQ
        if (in_array($vId, ['51', '52', '56'], true) || str_contains($vTypeName, 'PQ') || str_contains($vTypeName, 'Planetary')) {
            return "{$parentSet}PQ";
        }

        // Store Showdown (vId 40..45, 63..66 o sigla SS) -> SORSH, SHDSH, TWISH
        if (in_array($vId, ['40', '41', '42', '43', '44', '45', '63', '64', '65', '66'], true) || str_contains($vTypeName, 'SS') || str_contains($vTypeName, 'Store Showdown')) {
            return "{$parentSet}SH";
        }

        // Weekly Play / Organized Play (vId 60, 35) -> SOROP, SHDOP, TWIOP
        if ($vId === '60' || $vId === '35' || str_contains($vTypeName, 'Weekly Play') || str_contains($vTypeName, 'Organized Play')) {
            return "{$parentSet}OP";
        }

        // Hyperspace del 2-Player Starter Set di SOR (numeri 1-11 in Hyperspace) -> assegnati a SOROP come su SWUDB
        if ($parentSet === 'SOR' && $cardNum <= 11 && $vId === '02') {
            return 'SOROP';
        }

        // Event Promos (GenCon 2023, Essen 2024)
        if (str_contains($serial, 'GC') || str_contains($vTypeName, 'Gen Con')) {
            return 'GC23';
        }
        if (str_contains($serial, 'EE') || str_contains($vTypeName, 'Essen')) {
            return 'EE24';
        }

        return $parentSet;
    }
```

#### 4.3 — Creazione espansioni figlie (`createExpansionIfMissing`)
Quando viene registrata un'espansione figlia (es. `SOROP`, `SORPR`), la colonna `group_main_expansion` viene automaticamente collegata al set base (`SOR`, `SHD`, `TWI`):

```php
    private function createExpansionIfMissing(string $expansionCode, ?array $rawExpansionData, string $latestRotation): void
    {
        if (! Expansion::where('expansion', $expansionCode)->exists()) {
            // Se è un child set (es. SOROP, TSOR, JTLP), individua il core set genitore
            $mainExpansion = null;
            if (preg_match('/^(T)?(SOR|SHD|TWI|JTL|LOF|SEC|LAW|HMW|ASH)/', $expansionCode, $m)) {
                $mainExpansion = $m[2];
            }

            Expansion::create([
                'expansion' => $expansionCode,
                'rotation' => $latestRotation,
                'legal_date' => $rawExpansionData['publishedAt'] ?? now()->toDateString(),
                'confirmed' => false,
                'group_main_expansion' => $mainExpansion,
            ]);
        }
    }
```

#### 4.4 — Salvataggio carta e collegamento originale in `processCard()`
```php
        $cid = $cardData['cardUid'] ?? null;
        $card = null;

        try {
            if (! $cid) {
                throw new \RuntimeException('cardUid mancante nel payload');
            }

            $expansionCode = $this->resolveExpansionCode($cardData);
            $this->createExpansionIfMissing($expansionCode, $cardData['expansion']['data']['attributes'] ?? null, $latestRotation);

            $variantAttributes = [];
            if ($this->phase === self::PHASE_VARIANTS) {
                $vType = $cardData['variantTypes']['data'][0]['attributes']['name'] ?? null;
                $vCode = $cardData['variantTypes']['data'][0]['attributes']['variantId'] ?? null;

                // Fallback per foil senza variantTypes nominale (es. Mina Bonteri)
                if (! $vType && ! empty($cardData['hasFoil'])) {
                    $vType = 'Foil';
                }

                $variantAttributes = [
                    'original_card_id' => $this->resolveOriginalCardId($cardData),
                    'variant_type' => $vType,
                    'variant_code' => $vCode,
                ];
            }

            $card = Card::updateOrCreate(
                ['cid' => $cid],
                $variantAttributes + [
                    'expansion' => $expansionCode,
                    'number' => $cardData['cardNumber'],
                    'unique_card' => $cardData['unique'] ?? false,
                    'name' => $cardData['title'],
                    'title' => $cardData['subtitle'] ?? null,
                    'type' => $cardData['type']['data']['attributes']['name'] ?? null,
                    'rarity' => $cardData['rarity']['data']['attributes']['name'] ?? null,
                    'cost' => $cardData['cost'] ?? null,
                    'health' => $cardData['hp'] ?? null,
                    'power' => $cardData['power'] ?? null,
                    'text' => $cardData['textStyled'] ?? null,
                    'deploy_text' => $cardData['deployBoxStyled'] ?? null,
                    'arena' => $cardData['arenas']['data'][0]['attributes']['name'] ?? null,
                    'artist' => $cardData['artist'] ?? null,
                    'release_date' => isset($cardData['publishedAt']) ? Carbon::parse($cardData['publishedAt'])->toDateString() : null,
                    'max_copies' => $cardData['cardNumber'] == 256 && $expansionCode == 'JTL' ? 15 : null,
                ]
            );

            $this->syncAspectsAndTraits($card, $cardData);
            $this->downloadImages($card, $cardData, $imageDownloader);

            return true;
```

Aggiungi il metodo privato `resolveOriginalCardId()`:
```php
    private function resolveOriginalCardId(array $cardData): string
    {
        $originalCid = $cardData['variantOf']['data']['attributes']['cardUid'] ?? null;

        if (! $originalCid) {
            throw new \RuntimeException('variantOf senza cardUid nel payload');
        }

        $originalId = Card::where('cid', $originalCid)->value('id');

        if (! $originalId) {
            throw new \RuntimeException("Carta originale {$originalCid} non trovata: gli originali sono stati importati?");
        }

        return $originalId;
    }
```

#### 4.5 — Configurazione per immagini varianti
In `config/services.php`:
```php
    'swu' => [
        'variant_images' => env('SWU_VARIANT_IMAGES', true),
    ],
```
In `.env.example`: `SWU_VARIANT_IMAGES=true`.
In `downloadImages()` all'inizio del metodo:
```php
    if ($this->phase === self::PHASE_VARIANTS && ! config('services.swu.variant_images')) {
        return;
    }
```

#### 4.6 — Transizione a due fasi in `handle()`
```php
        $variantFilter = $this->phase === self::PHASE_VARIANTS ? '$notNull' : '$null';

        $response = Http::timeout(30)->retry(3, 2000, throw: false)->get(self::API_URL, [
            'locale' => 'it',
            "filters[variantOf][id][{$variantFilter}]" => 'true',
            // ...
```
E al completamento dell'ultima pagina degli originali:
```php
        if ($this->page < $lastPage) {
            self::dispatch($this->page + 1, $this->runId, $this->phase);

            return;
        }

        if ($this->phase === self::PHASE_ORIGINALS) {
            // Finiti gli originali: parte la fase varianti dalla pagina 1
            self::dispatch(1, $this->runId, self::PHASE_VARIANTS);

            return;
        }

        $this->finish($telegram, $state, $chatId);
```

#### 4.7 — `cards:scan` command (`app/Console/Commands/ScanCards.php`)
```php
    protected $signature = 'cards:scan {--page=1 : Pagina da cui far partire la scansione} {--phase=originals : originals (completo) oppure variants}';
```

---

## Step 5 — Test Pest

#### 5.1 — Aggiornare i mock in `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`
Aggiorna `fakeSwuHttp()` affinché simuli le richieste con `$notNull` restituendo le varianti di test:
```php
function fakeSwuHttp(array $routes, array $variants = []): void
{
    Storage::fake('public');
    Http::fake([
        'admin.starwarsunlimited.com/api/card-list*notNull*' => Http::response([
            'data' => $variants,
            'meta' => ['pagination' => ['pageCount' => $variants === [] ? 0 : 1]],
        ]),
    ] + $routes + ['*' => Http::response('fake-image', 200, ['Content-Type' => 'image/png'])]);
}
```

#### 5.2 — Test nuovi del job e della mappatura promo
Aggiungi in `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`:
```php
it('assegna le promo dei primi set alle sottoespansioni stile swudb', function () {
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()], // originale SOR
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        [
            fakeVariantEntry('v-pr', 2, originalUid: '2579145458', type: 'Promo Prerelease', variantId: '04'),
            fakeVariantEntry('v-op', 2, originalUid: '2579145458', type: 'Weekly Play', variantId: '60'),
            fakeVariantEntry('v-sh', 2, originalUid: '2579145458', type: 'Store Showdown', variantId: '41'),
        ]
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    $pr = Card::where('cid', 'v-pr')->firstOrFail();
    $op = Card::where('cid', 'v-op')->firstOrFail();
    $sh = Card::where('cid', 'v-sh')->firstOrFail();

    expect($pr->expansion)->toBe('SORPR')
        ->and($op->expansion)->toBe('SOROP')
        ->and($sh->expansion)->toBe('SORSH')
        ->and(Expansion::where('expansion', 'SOROP')->value('group_main_expansion'))->toBe('SOR');
});
```

#### 5.3 — Test di mazzi, catalogo e collezione (`tests/Feature/Cards/CardVariantsTest.php`)
```php
it('il catalogo mostra solo gli originali', function () {
    $original = makeCard(['name' => 'Ahsoka Tano']);
    makeCard(['name' => 'Ahsoka Tano', 'original_card_id' => $original->id, 'expansion' => 'SOROP']);

    $response = $this->get(route('cards.index'))->assertOk();
    expect($response->viewData('cards')->total())->toBe(1);
});

it('l\'import di un mazzo converte codici promo swudb nell\'originale', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR', 'number' => 10]);
    makeCard(['expansion' => 'SORPR', 'number' => 1, 'original_card_id' => $original->id]);

    $result = app(DeckImporter::class)->fromContent(
        json_encode(['deck' => [['id' => 'SORPR_1', 'count' => 1]]]),
        $user,
        'Mazzo Promo',
        DeckFormat::Premier
    );

    expect($result['deck']->cards->first()->id)->toBe($original->id);
});
```

---

## Step 6 — Esecuzione e Verifica in Locale

1. Applica le modifiche degli Step 1–5 e riavvia il worker di sviluppo:
   ```bash
   docker compose -f docker-compose.dev.yml restart worker
   ```
2. Esegui la migration e pulisci la cache:
   ```bash
   docker compose -f docker-compose.dev.yml exec app php artisan migrate
   docker compose -f docker-compose.dev.yml exec app php artisan cache:forget cards_bounds
   ```
3. Lancia i test Pest:
   ```bash
   php artisan test tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php
   php artisan test tests/Feature/Cards/CardVariantsTest.php
   ```
4. Esegui lo scan solo delle varianti in locale:
   ```bash
   docker compose -f docker-compose.dev.yml exec app php artisan cards:scan --phase=variants
   ```
5. Verifica su tinker:
   ```bash
   docker compose -f docker-compose.dev.yml exec app php artisan tinker --execute="echo App\Models\Card::originals()->count().' originali, '.App\Models\Card::whereNotNull('original_card_id')->count().' varianti'.PHP_EOL; print_r(App\Models\Card::whereNotNull('original_card_id')->selectRaw('expansion, count(*) n')->groupBy('expansion')->pluck('n','expansion')->all());"
   ```
   Atteso: zero errori SQL, espansioni promo create (`SORPR`, `SOROP`, `SORSH`, `SHDPQ`...) con le rispettive carte assegnate.

---

## Step 7 — Rilascio sulla VM

1. Verifica lo spazio libero su disco (`df -h /`). Se lo spazio è limitato (< 3 GB liberi), imposta temporaneamente `SWU_VARIANT_IMAGES=false` nel `.env` prima di lanciare la scansione.
2. Push su `new` e verifica l'esecuzione della migration dal log di deploy.
3. Esegui la scansione delle varianti o la scansione completa da Telegram `/scan`.
4. Verifica che `/admin/errori` sia a zero.

---

## Step 8 — Documentazione

1. **README.md**: documenta la suddivisione delle promo stile SWUDB, la relazione con `group_main_expansion`, la visualizzazione delle varianti nella scheda carta e la nuova variabile d'ambiente `SWU_VARIANT_IMAGES`.
2. **todo.md**: spunta la sezione 14 e aggiorna le note per il piano 13.
3. Rinomina il file in `implementationPlan-V-14-variantiCarte.md`.
