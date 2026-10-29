# Implementation plan 14 — Varianti delle carte e Set Promozionali (una sola carta in lista, tutte le versioni a DB, divisione stile SWUDB)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Lavora sul codice già consegnato dai piani 05 (catalogo), 07 (mazzi), 08 (collezione), 10 (API) e 12 (import per pagina).
> **Va eseguito prima dello Step 13.4 del piano 13** ([`implementationPlan-13-ricostruzioneDeploy.md`](implementationPlan-13-ricostruzioneDeploy.md)): il reset pulito e il reimport completo devono già importare anche le varianti e i set promozionali.
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.
> Data: 2026-10-28. Aggiornato dopo l'analisi del log reale `storage/logs/laravel-2026-10-28.log` (103 pagine complete scansionate) e la consultazione della struttura dei set di SWUDB ([`swudb.com/sets`](https://swudb.com/sets)).

---

## Stato dell'esecuzione (2026-10-28, sera)

Step 1–5 applicati quasi tutti, ma con errori: la suite ha 51 test falliti e le pagine dei mazzi rispondono 500. Cause e correzioni sono nel piano [`14a`](implementationPlan-14a-correzioniVarianti.md): import di `Builder` mancante in `Card`, Step 3.1 finito in `DeckController` invece che in `CardController`, `cards:scan --phase` mai passato al job, migration senza `dropUnique`, test e vista collezione da sistemare. La decisione sul delta delle carte mancanti (Step 2.5) è sviluppata nel piano [`14b`](implementationPlan-14b-deltaCollezioneVarianti.md).

**Applica prima `implementationPlan-14-variantiCarte-Correzione.md` (che riunisce 14a e 14b), poi riprendi da Step 6.** Questo piano si rinomina in `implementationPlan-V-14-variantiCarte.md` solo a 14a e 14b chiusi.

---

## Obiettivo

Oggi l'import chiede all'API solo le carte con `filters[variantOf][id][$null]=true`, cioè solo gli originali standard: tutte le versioni alternative (Hyperspace, Showcase, Foil) e le carte promozionali (Weekly Play, Prerelease, Store Showdown, Planetary Qualifier, Convention, Judge) si perdono. Vogliamo:

1. importarle **tutte** (10.286 carte complessive, togliendo il filtro agli originali);
2. risolvere il disordine dei dati di partenza dell'API organizzando le promo in **espansioni dedicate stile SWUDB** (es. `SORPR`, `SOROP`, `SORSH`, `SHDPQ`... e le ufficiali `JTLP`, `SECP`...), collegate al set base tramite `expansions.group_main_expansion`;
3. salvare a DB per ogni variante il **riferimento alla carta originale** (`original_card_id`, `variant_type`, `variant_code`);
4. mostrare **una sola carta** per ogni gruppo nelle liste pubbliche (una sola Ahsoka, non quattro; eccezioni volute: `/nuove-uscite` e `/collezione` mostrano tutte le carte, varianti comprese), con la galleria di tutte le altre versioni (Hyperspace, Showcase, promo) consultabile nella scheda dell'originale;
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
| Chiave primaria `cards.id` | Sempre `{expansion}{number}` | Con la separazione in sottoespansioni stile SWUDB (es. `SORPR1`, `SOROP2`) la coppia espansione+numero è univoca per costruzione: nessun suffisso con `cid` necessario. La larghezza `VARCHAR(20)` rimane invariata rispetto a oggi. |
| Indice `(expansion, number)` | Indice normale (non unique) | Mantiene le performance delle query senza fallire in caso di errori di battitura nei numeri da parte di Strapi. |
| Importazione | **Due fasi** nello stesso scan: prima gli originali, poi le varianti | Quando arriva una variante, l'originale esiste già a DB e il collegamento si risolve con una semplice query `cid`. |
| Liste e Ricerca | Filtro esplicito `originals()` (`whereNull('original_card_id')`) | Nessun global scope: il catalogo mostra solo gli originali, mentre le schede dettaglio possono caricare le varianti. `/nuove-uscite` e `/collezione` non applicano il filtro e mostrano tutte le carte. |
| Mazzi | Un mazzo contiene **sempre l'originale** | Il limite delle 3 copie opera sulla carta originale. L'import di un mazzo con codici SWUDB (es. `SOROP_1`, `SORPR_2`) li riconduce automaticamente all'originale. |
| Collezione | `collection_cards.variant` viene rimosso; ogni versione (Foil, Hyperspace, Standard) è una riga distinta in `cards` e va aggiunta alla collezione come `card_id` diretto | La colonna `variant` era un workaround per tracciare la finitura sulla carta originale; con le varianti come carte a DB il dato di finitura è implicito nell'`expansion` e nel `number` della carta stessa. Il vincolo unico su `collection_cards` diventa `(user_id, card_id)`. |
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

#### 1.1 — Migration `database/migrations/2026_10_28_090000_add_variants_and_drop_collection_variant.php`
Crea una migration che:
1. Aggiunge le colonne `original_card_id`, `variant_type`, `variant_code` sulla tabella `cards`.
2. Sostituisce l'indice unique `['expansion', 'number']` con un indice normale (robusto ai refusi dell'API come SEC 571).
3. Rimuove la colonna `variant` da `collection_cards` e aggiorna il vincolo unico a `(user_id, card_id)`.

> [!NOTE]
> **Nessun allargamento di `cards.id` o delle FK**: con la separazione in sottoespansioni stile SWUDB la coppia `(expansion, number)` è già univoca per costruzione, quindi `id` rimane `VARCHAR(20)` (verificato nella migration `create_cards_table`) e non si devono toccare le tabelle pivot (`card_aspect`, `card_trait`, `deck_cards`, `collection_cards`).

> [!WARNING]
> Il nuovo unique `(user_id, card_id)` di `collection_cards` fallisce se esistono righe con la stessa carta in finiture diverse, e le quantità per finitura non si possono mappare sulle carte-variante. Sui database con dati di collezione serve `migrate:fresh` (lo prevede già lo Step 13.4) oppure una decisione sul trasferimento dei dati.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds variant fields to cards, drops the unique (expansion, number) constraint,
     * and removes the obsolete collection_cards.variant column.
     *
     * Aggiunge i campi variante a cards, rimuove il vincolo unique su (expansion, number)
     * e droppa la colonna collection_cards.variant ora superflua.
     */
    public function up(): void
    {
        // 1. Modifiche sulla tabella cards
        Schema::table('cards', function (Blueprint $table) {
            // Sostituisce l'indice unique con un indice normale
            // (sicuro in caso di refusi di numerazione nei dati Strapi come SEC 571)
            $table->index(['expansion', 'number'], 'cards_expansion_number_index');
            // Unico (expansion, number) mantenuto per scelta dell'utente (2026-10-29): nessun dropUnique

            // Nuovi campi variante
            $table->string('original_card_id', 20)->nullable()->after('cid')
                ->comment('cards.id della carta originale di cui questa è una variante. NULL = è lei l\'originale.');
            $table->string('variant_type', 50)->nullable()->after('original_card_id')
                ->comment('Nome della variante (Hyperspace, Showcase, Weekly Play...). NULL per gli originali.');
            $table->string('variant_code', 10)->nullable()->after('variant_type')
                ->comment('Codice variante dell\'API (02 = Hyperspace, 03 = Showcase, 04 = Prerelease...). NULL per gli originali.');

            $table->foreign('original_card_id')->references('id')->on('cards')->cascadeOnDelete();
        });

        // 2. collection_cards: drop variant, aggiorna vincolo unico.
        // L'ordine conta: il vecchio unique (user_id, card_id, variant) può essere l'unico indice che
        // sostiene la FK su user_id, quindi il nuovo unique va creato PRIMA di togliere il vecchio.
        Schema::table('collection_cards', function (Blueprint $table) {
            // Ogni carta (anche variante/promo) può stare una sola volta nella collezione dell'utente
            $table->unique(['user_id', 'card_id']);
        });

        Schema::table('collection_cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id', 'variant']);
            $table->dropColumn('variant');
        });
    }

    public function down(): void
    {
        Schema::table('collection_cards', function (Blueprint $table) {
            $table->enum('variant', ['normal', 'foil', 'hyper', 'prestige', 'hyper_foil'])
                ->default('normal')->after('card_id');
            $table->unique(['user_id', 'card_id', 'variant']);
        });

        Schema::table('collection_cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['original_card_id']);
            $table->dropColumn(['original_card_id', 'variant_type', 'variant_code']);
            $table->unique(['expansion', 'number']);
            $table->dropIndex('cards_expansion_number_index');
        });
    }
};
```

> [!IMPORTANT]
> Decisione dell'utente (2026-10-29): l'unico `(expansion, number)` resta, quindi niente `dropUnique`; nel `down()` non va ricreato. Vedi `implementationPlan-14-variantiCarte-Correzione.md`, Step 4.

#### 1.2 — Modello `app/Models/Card.php`

> **Aggiungi in cima al file `use Illuminate\Database\Eloquent\Builder;`** (14a Step 1): senza, nel namespace `App\Models` il type-hint `Builder` punta a una classe inesistente e `originals()` lancia `TypeError`.
Aggiorna `$fillable`, il boot dell'ID e aggiungi le relazioni e lo scope:

```diff
     protected static function booted(): void
     {
         static::creating(function (Card $card) {
-            $card->id = "{$card->expansion}{$card->number}";
+            // Invariato: espansione+numero è sempre univoco grazie alla separazione in sottoespansioni SWUDB
+            $card->id = "{$card->expansion}{$card->number}";
         });
     }
```

Aggiungi nei campi `$fillable`:
```diff
         'expansion',
         'number',
         'cid',
+        'original_card_id',  // VARCHAR(20): es. "SOR134"
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
Il path dell'immagine usa già `{expansion}/{number}` e rimane invariato: con le sottoespansioni SWUDB ogni carta ha espansione e numero propri (es. `cards/SOROP/1-front.png` o `cards/SOR/260-front.png`).

> [!NOTE]
> Non serve nessuna modifica a `CardImageDownloader.php`: la coppia `(expansion, number)` è già univoca per le varianti, quindi nessun suffisso cid da aggiungere al percorso file.

---

## Step 2 — Liste e Mazzi (solo originali), Collezione (tutte le carte)

Catalogo, API, ricerca dell'editor mazzi e mazzi lavorano solo sulle carte originali. **La collezione e le Ultime uscite (2.2) sono le eccezioni**: l'utente deve poter aggiungere anche le singole varianti, quindi `/collezione` le elenca tutte (vedi 2.5).

#### 2.1 — `app/Services/CardSearch.php`
In `apply()`, `filterOptions()` e `getBounds()`. `apply()` riceve il parametro `$includeVariants` (default `false`): lo passa a `true` solo `CollectionController::index()` (vedi 2.5). Codice attuale verificato: `CollectionController::index()` usa `CardSearch::apply()`, quindi mettere `originals()` dentro `apply()` senza parametro nasconderebbe le varianti in `/collezione`.
```diff
-    public function apply(Builder $query, array $filters): Builder
+    public function apply(Builder $query, array $filters, bool $includeVariants = false): Builder
```
```diff
-        return $query
+        return $query
+            ->when(! $includeVariants, fn ($q) => $q->originals())
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

> **Decisione dell'utente, applicata coerentemente nel piano (2026-10-28)**: `/nuove-uscite` mostra tutte le carte, varianti comprese, e nessun altro punto del piano la contraddice (Obiettivo punto 4, tabella delle scelte e intro dello Step 2 sono stati allineati). Un click su una variante porta all'originale con un redirect 301 (Step 3.1) dove compare nella galleria 'Altre versioni'. La mail delle carte nuove (`NewCardsEmail`) elenca già tutte le carte create dallo scan, varianti comprese.
qui non faccio modifiche perchè nella pagina new releases ritengo sensato vedere tutte le carte, non solo le versioni originali (se sei un AI: se da qualche parte trovi discordanza con questo approccio di mostrare tutte le uscite e non solo le originali sistema l'altro)

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
La collezione ora accetta **qualsiasi carta** (originale o variante/promo), poiché ogni versione è una riga distinta in `cards`. La colonna `variant` non esiste più. File toccati (stato attuale riletto sul codice): `CollectionController`, `CollectionCard`, `resources/views/collection/index.blade.php`.

`CollectionController::index()`: elenca anche le varianti.
```diff
-            ->apply(Card::query()->with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS))
+            ->apply(Card::query()->with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS), includeVariants: true)
```

`CollectionController::update()`: `card_id` resta `exists:cards,id` (accetta originali e varianti), via `variant` dalla validazione e dalla chiave di ricerca.
```diff
             'card_id' => ['required', 'exists:cards,id'],
-            'variant' => ['required', 'in:normal,foil,hyper,prestige,hyper_foil'],
             'quantity' => ['required', 'integer', 'min:0', 'max:65535'],
```
```diff
-        $key = ['user_id' => $request->user()->id, 'card_id' => $validated['card_id'], 'variant' => $validated['variant']];
+        $key = ['user_id' => $request->user()->id, 'card_id' => $validated['card_id']];
```

`app/Models/CollectionCard.php`:
```diff
-    protected $fillable = ['user_id', 'card_id', 'variant', 'quantity'];
+    protected $fillable = ['user_id', 'card_id', 'quantity'];
```

`resources/views/collection/index.blade.php`: una sola quantità per carta al posto dei 5 campi per finitura.
```diff
-@php
-    $variants = [
-        'normal' => 'Normale',
-        'foil' => 'Foil',
-        'hyper' => 'Hyperspace',
-        'hyper_foil' => 'Hyperfoil',
-        'prestige' => 'Prestige',
-    ];
-@endphp
```
```diff
-            @php($owned = $card->collectionCards->pluck('quantity', 'variant'))
+            @php($owned = $card->collectionCards->first()?->quantity ?? 0)
```
```diff
-                    <div
-                        class="grid grid-cols-5 gap-2 rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
-                        @foreach ($variants as $key => $label)
-                            <label class="text-center text-xs text-gray-500 dark:text-gray-400">
-                                {{ $label }}
-                                <input type="number" min="0" max="65535" value="{{ $owned[$key] ?? 0 }}"
-                                    data-collection data-card="{{ $card->id }}" data-variant="{{ $key }}"
-                                    class="mt-1 w-full rounded border-gray-300 text-center text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
-                            </label>
-                        @endforeach
-                    </div>
+                    <div
+                        class="rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
+                        <label class="block text-center text-xs text-gray-500 dark:text-gray-400">
+                            {{ $card->variant_type ?? 'Originale' }}: copie possedute
+                            <input type="number" min="0" max="65535" value="{{ $owned }}"
+                                data-collection data-card="{{ $card->id }}"
+                                class="mt-1 w-full rounded border-gray-300 text-center text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
+                        </label>
+                    </div>
```
Nello script della vista, nel `body` del `fetch`:
```diff
                         card_id: input.dataset.card,
-                        variant: input.dataset.variant,
                         quantity,
```

> [!WARNING]
> **`DeckGapCalculator`** sommava le quantità per `card_id` su tutte le finiture. Con le varianti come carte distinte, chi possiede solo la Hyperspace non risulterebbe avere l'originale richiesta dal mazzo. **Decisione dell'utente** (sotto): le varianti contano come copie dell'originale. Implementazione, test e codice completo nel piano [`14b`](implementationPlan-14b-deltaCollezioneVarianti.md), da applicare insieme a questo step.
>
>facciamo in modo che per ogni card_id vengano considerate anche tutte le varianti nella conta, perciò chi ha una copia di una variante e una di un'altra di quella card_id avrà 2 copie di quella card_id.
> *(Piano sviluppato il 2026-10-28: `implementationPlan-14b-deltaCollezioneVarianti.md`.)*


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

> **Attenzione (14a Step 2)**: questo metodo va in `app/Http/Controllers/CardController.php`, **non** in `DeckController` (dove è finito alla prima applicazione, rompendo le pagine dei mazzi). Serve anche `use Illuminate\Http\RedirectResponse;`.
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

> Il parametro promosso `public string $phase` non ha un default di proprietà e fa fallire i job già in coda serializzati senza `phase`: usa la versione di 14a Step 4.
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

    // La firma da sola non basta: handle() deve passare --phase al job (14a Step 3), altrimenti cards:scan --phase=variants lancia gli originali.
```

---

## Step 5 — Test Pest

#### 5.1 — Aggiornare i mock in `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`

> I test di questo step (5.1–5.3) vanno corretti come in 14a Step 7: `fakeVariantEntry()` non era definita, 'pagina correttamente su piu pagine' e 'riprende da una pagina' non tenevano conto della seconda fase, `CardVariantsTest` non aveva gli `use`.
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

#### 5.1bis — Helper `fakeVariantEntry()`
Il test 5.2 la usa: va definita in `ImportCardsFromSwuApiJobTest.php` subito dopo `fakeCardEntry()`:
```php
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

it('l\'utente può aggiungere sia l\'originale che la variante alla collezione separatamente', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    $hyperspace = makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);

    // La rotta di aggiornamento è PATCH /collezione e non riceve più `variant`
    $this->actingAs($user)
        ->patchJson(route('collection.update'), ['card_id' => $original->id, 'quantity' => 2])
        ->assertOk();

    $this->actingAs($user)
        ->patchJson(route('collection.update'), ['card_id' => $hyperspace->id, 'quantity' => 1])
        ->assertOk();

    expect(CollectionCard::where('user_id', $user->id)->count())->toBe(2);
});

it('la collezione elenca anche le varianti', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR']);
    makeCard(['expansion' => 'SOROP', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);

    $response = $this->actingAs($user)->get(route('collection.index'))->assertOk();

    expect($response->viewData('cards')->total())->toBe(2);
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
4. Esegui lo scan solo delle varianti in locale (richiede il fix di `cards:scan --phase`, 14a Step 3, e gli originali già a DB):
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

1. **README.md**: documenta la suddivisione delle promo stile SWUDB, la relazione con `group_main_expansion`, la visualizzazione delle varianti nella scheda carta, la collezione per singola carta (senza `collection_cards.variant`, unique `(user_id, card_id)`) e la nuova variabile d'ambiente `SWU_VARIANT_IMAGES`; aggiorna anche la sezione "Database" e la rotta `PATCH /collezione`.
2. **todo.md**: spunta la sezione 14 e aggiorna le note per il piano 13.
3. Rinomina il file in `implementationPlan-V-14-variantiCarte.md`, solo dopo aver chiuso anche 14a e 14b.
