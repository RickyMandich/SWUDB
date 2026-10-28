# Implementation plan 14 — Varianti delle carte (una sola carta in lista, tutte le versioni a DB)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Non ha una "Fase" propria: lavora sul codice già consegnato dai piani 05 (catalogo), 07 (mazzi), 08 (collezione), 10 (API) e 12 (import per pagina).
> **Va eseguito prima dello Step 13.4 del piano 13** ([`implementationPlan-13-ricostruzioneDeploy.md`](implementationPlan-13-ricostruzioneDeploy.md)): il reset pulito e il reimport completo devono già importare anche le varianti. Il numero 14 è solo l'ordine di creazione; se preferisci l'ordine di esecuzione si può scambiare con il 13 (come già fatto il 2026-10-26).
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.
> Data: 2026-10-28. Rigenerato lo stesso giorno dopo che `examples/test.json` è stato rifatto **senza** il filtro sulle varianti (prima conteneva solo originali): le ipotesi sull'API sono ora in gran parte confermate da dati veri.

## Obiettivo

Oggi l'import chiede all'API solo le carte con `filters[variantOf][id][$null]=true`, cioè solo gli originali: tutte le versioni alternative (Hyperspace, Showcase, ...) si perdono. Vogliamo:

1. importarle **tutte** (togliere il limite agli originali);
2. salvare a DB per ogni variante il **riferimento alla carta originale**;
3. mostrare **una sola carta** per ogni gruppo nelle liste (una sola Ahsoka, non tre), con le altre versioni raggiungibili dalla scheda dell'originale;
4. gestire gli effetti collaterali (mazzi, import mazzi, collezione, API, mail di scan, Telegram, spazio disco).

## Cosa sappiamo dell'API

Non esiste documentazione ufficiale: tutto viene da `examples/test.json` (richiesta reale di pagina 3, 100 per pagina, `locale=it`, stessi `fields[]` del job, **senza** filtro sulle varianti; il file pesa 3,2 MB e non ho potuto leggerlo tutto, ho letto l'inizio e l'ultima parte con la paginazione) e dal codice del job. Non ho potuto interrogare l'API direttamente.

**Struttura (Strapi v4)**: `data[].attributes`, `meta.pagination`, filtri `filters[campo][...][$operatore]`, `fields[n]`, `pagination[...]`. Le relazioni (`expansion`, `type`, `aspects`, `traits`, `rarity`, `variantOf`, `variantTypes`, ...) arrivano già popolate a un livello come `{ "data": {"id", "attributes": {...}} }`; `fields[]` limita solo i campi scalari della radice, **non** le relazioni.

**Confermato dai dati veri** (l'ultima carta del file è una variante):
- **Dimensioni**: `meta.pagination.total` senza filtro = **10286** (103 pagine da 100). Gli originali sono 3135 (ultimo import completo, 32 pagine): le varianti sono quindi circa **7151**, più del doppio degli originali (≈ 2,3 varianti per originale), in circa 72 pagine.
- Una variante è una **voce separata**, con i propri `cardUid`, `cardNumber`, `id`. Esempio: la Hyperspace dei "Simpatizzanti del Settore" ha `cardNumber` **501**, `serialCode` `0102501`, `cardUid` `7440807459`, `variantTypes` = `Hyperspace` con `variantId` `"02"` e `foil` `false`; il suo originale ha `cardNumber` **243**, `serialCode` `0101243`, `cardUid` `1081012039`.
- `variantOf.data` è popolato anche con `fields[]` attivi e contiene `attributes.cardUid` dell'originale (insieme a tutti i suoi scalari: titolo, numero, costo, ecc.) e l'`id` Strapi. Per gli originali è `{"data": null}`.
- Quindi, per questo esempio: il `cardUid` della variante è **diverso** da quello dell'originale (`cid` unico e `updateOrCreate` per `cid` reggono) e la coppia espansione + numero è **diversa** (la PK `cards.id` = `{expansion}{number}` e `UNIQUE(expansion, number)` reggono). Il `serialCode` è `set + variantId + numero`, dove il numero della variante è un numero a sé (501), non quello dell'originale.
- **Eccezione nota: Luke e Vader** (log del test del 2026-10-27, pagina 3/103 senza filtro, e verifica a mano dell'utente): 3 carte su 100 hanno fallito con `Duplicate entry 'SOR1'` / `'SOR2' for key 'PRIMARY'` (Darth Vader `7759451019` n. 1, Darth Vader `8730520110` n. 2, Luke Skywalker `3445221668` n. 2). Sono varianti di Luke e Vader con una **numerazione propria, limitata solo a loro due**, ma con espansione SOR: i loro numeri (1, 2) coincidono con quelli di **altre carte** SOR già a DB (non con quello del proprio originale), e due di loro coincidono anche tra loro. Tutte le altre varianti viste hanno numeri propri (253+, 501+). Quindi `id` = `{expansion}{number}` e `UNIQUE(expansion, number)` non reggono per queste poche carte: si gestiscono con un id suffissato (Step 1.3), senza ridisegnare lo schema. Anche il path delle immagini `cards/{expansion}/{number}-{side}` collide e **sovrascriverebbe l'immagine dell'originale**: va cambiato (Step 1.3). Che non ci siano altri tipi di variante con la stessa abitudine lo dice lo Step 0. Nel test non sono state create: l'import degli originali, fatto con il filtro, ha sempre dato 0 problemi, quindi hanno tutte `variantOf` valorizzato.
- Le varianti hanno la localizzazione italiana (il filtro `locale=it` restituisce la variante con il suo originale `it` in `variantOf`).
- `cardUid` è uguale tra le localizzazioni (it/en/de/fr...) della stessa carta; le localizzazioni sono elencate nelle voci annidate e **non** vanno importate.
- `reprintOf` esiste ed è `null` negli esempi visti.

**Ancora non verificato** (solo un campione di una variante): se **tutti** i tipi di variante (Showcase, Prestige, Serialized, foil, promo) rispettano le stesse regole (numero e `cardUid` propri, `variantOf` popolato), e se tutti gli originali delle varianti esistono tra gli originali italiani. Lo controlla lo Step 0, ora ridotto a una verifica sola.

## Scelte di progetto

| Scelta | Decisione | Perché |
|---|---|---|
| Come si chiama il riferimento | colonna `cards.original_card_id`, relazioni `Card::originalCard()` / `Card::variants()`, scope `Card::originals()` | **Non** "base": `Base` è già un tipo di carta del gioco (`cards.type`) e `Deck::baseCard()` è la base del mazzo. "Originale" evita ogni ambiguità. |
| Dove punta | `cards.id` (stringa `{expansion}{number}`), FK su sé stessa, `cascadeOnDelete` | stesso riferimento che usano già tutte le pivot; una variante senza originale non avrebbe senso. |
| Cosa si salva oltre al riferimento | `variant_type` (nome della variante come arriva dall'API, in italiano, es. `Hyperspace`) e `variant_code` (`variantId`, es. `"02"`; `"01"` = standard) | il nome serve alla UI, il codice è indipendente dalla lingua e serve per ordinare e, in futuro, per collegare le varianti alla colonna `collection_cards.variant`. |
| Importazione | **due fasi** nello stesso scan: prima gli originali, poi le varianti | così, quando arriva una variante, il suo originale esiste già e il riferimento si risolve con una sola query: nessuna seconda passata e nessun problema di ordine tra pagine (nella lista senza filtro originali e varianti sono mescolati). |
| Liste | si filtra con `whereNull('original_card_id')` (scope `originals()`) | nessun global scope: le query dell'import e della pagina di dettaglio devono poter vedere anche le varianti, e uno scope esplicito si vede nel codice. |
| Mazzi | un mazzo contiene **sempre l'originale** | il piano 08 dice già che le varianti di stampa vivono solo in `collection_cards`; così i validator (limite copie per carta) non vanno toccati. L'import di un mazzo con l'id di una variante la converte nell'originale. |
| Collezione | invariata: resta `collection_cards.variant` (`normal`, `foil`, `hyper`, ...) sulla carta originale | collegare quelle varianti alle carte-variante importate è un lavoro a parte (vedi "Fuori da questo piano"). |

## Step 0 — Verifica residua sui tipi di variante (consigliata, 5 minuti)

Le ipotesi principali sono già confermate (vedi sopra); questo controllo copre i tipi di variante che non compaiono nel campione. Da PowerShell sul tuo PC (parentesi quadre e `$` letterali tra apici singoli). Scarica tutte le pagine delle varianti (circa 72 richieste) e degli originali (32):
```powershell
$base = 'https://admin.starwarsunlimited.com/api/card-list?locale=it&fields[0]=cardUid&fields[1]=cardNumber&fields[2]=title&pagination[pageSize]=100'
function Get-All($filter) {
  $all = @(); $p = 1
  do { $r = Invoke-RestMethod -Uri ("$base&$filter&pagination[page]=$p"); $all += $r.data; $p++ } while ($p -le $r.meta.pagination.pageCount)
  $all
}
$orig = Get-All 'filters[variantOf][id][$null]=true'
$vari = Get-All 'filters[variantOf][id][$notNull]=true'
"originali: $($orig.Count)  (atteso 3135)   varianti: $($vari.Count)  (atteso circa 7151)"

"senza variantOf.cardUid: " + @($vari | ? { -not $_.attributes.variantOf.data.attributes.cardUid }).Count                       # atteso 0
"cardUid uguale all'originale: " + @($vari | ? { $_.attributes.cardUid -eq $_.attributes.variantOf.data.attributes.cardUid }).Count   # atteso 0
function Key($e) { "$($e.attributes.expansion.data.attributes.code)-$($e.attributes.cardNumber)" }
$origKeys = @{}; foreach ($o in $orig) { $origKeys[(Key $o)] = 1 }
"numero uguale a quello di un originale: " + @($vari | ? { $origKeys.ContainsKey((Key $_)) }).Count                           # atteso 0
$varKeys = @($vari | % { Key $_ })
"coppie espansione-numero duplicate tra le varianti: " + ($varKeys.Count - @($varKeys | Select -Unique).Count)                 # atteso 0
$varUids = @($vari | % { $_.attributes.cardUid })
"cardUid duplicati tra le varianti: " + ($varUids.Count - @($varUids | Select -Unique).Count)                                  # atteso 0
$origUid = @{}; foreach ($o in $orig) { $origUid[[string]$o.attributes.cardUid] = 1 }
"varianti il cui originale non è tra gli originali it: " + @($vari | ? { -not $origUid.ContainsKey([string]$_.attributes.variantOf.data.attributes.cardUid) }).Count   # atteso 0

$vari | % { $_.attributes.variantTypes.data.attributes.name } | Group-Object | Sort Count -Desc | ft Name, Count   # tipi di variante
"varianti con più di un variantTypes: " + @($vari | ? { $_.attributes.variantTypes.data.Count -gt 1 }).Count
"codici espansione solo nelle varianti: " + ((@($vari | % { $_.attributes.expansion.data.attributes.code } | Sort -Unique) | ? { $c = $_; -not ($orig | ? { $_.attributes.expansion.data.attributes.code -eq $c }) }) -join ', ')
```
Cosa decidere:
| Risultato | Cosa fare |
|---|---|
| tutti gli "atteso 0" a zero | prosegui con il piano così com'è. |
| `senza variantOf.cardUid` o `cardUid uguale all'originale` > 0 | **fermati** e ripassami il risultato: per quei tipi di variante serve un'altra chiave di collegamento (l'`id` Strapi in `variantOf.data.id`) o `cid` non può essere unico. |
| `numero uguale a quello di un originale` o coppie duplicate > 0 | **atteso per Luke e Vader di SOR** (nello script, per questi due controlli "atteso 0" va letto "pochi"): lo Step 1.3 li gestisce con l'id suffissato. Controlla che siano pochi e solo quelli; se i numeri sovrapposti sono decine o riguardano altri tipi di variante, ripassami l'elenco prima di procedere. |
| `varianti il cui originale non è tra gli originali it` > 0 | non blocca: quelle varianti finiranno in `SystemError` ("Carta originale ... non trovata") e nel report agli admin; decidi se ignorarle. |
| `varianti con più di un variantTypes` > 0 | oggi si salva il primo (`data[0]`); decidi se serve altro. |
| tipi con nome "Foil" come carte a sé | si importano come tutte le altre (righe nascoste); la collezione gestisce già il foil come attributo. Decidi se escluderle (nel piano non c'è un filtro per tipo). |
| codici espansione solo nelle varianti | il job crea quelle espansioni (con `rotation` = ultima); controllale da `/admin/espansioni`. |

## Step 1 — Schema e modello

#### 1.1 — Migration `database/migrations/2026_10_28_090000_add_original_card_id_to_cards_table.php`
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the link from a variant card to its original card
     * Aggiunge il collegamento dalla carta variante alla sua carta originale
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('original_card_id', 20)->nullable()->after('cid')
                ->comment('cards.id della carta originale di cui questa è una variante (Hyperspace, Showcase...). NULL = è lei l\'originale: solo gli originali compaiono nelle liste e nei mazzi.');
            $table->string('variant_type', 50)->nullable()->after('original_card_id')
                ->comment('Nome della variante come arriva dall\'API (variantTypes), es. Hyperspace. NULL per gli originali.');
            $table->string('variant_code', 10)->nullable()->after('variant_type')
                ->comment('variantId dell\'API (es. 02 = Hyperspace, 01 = standard): indipendente dalla lingua. NULL per gli originali.');

            $table->foreign('original_card_id')->references('id')->on('cards')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['original_card_id']);
            $table->dropColumn(['original_card_id', 'variant_type', 'variant_code']);
        });
    }
};
```
Note:
- La colonna ha lo stesso tipo di `cards.id` (`string(20)`), come `collection_cards.card_id`: MariaDB crea da solo l'indice della FK.
- Sui test (SQLite) Laravel non aggiunge la FK con una `ALTER TABLE`: la colonna c'è, il vincolo no. Non serve fare nulla, ma tienilo a mente se un test dovesse contare sul vincolo.
- È una migration nuova e non una modifica di `create_cards_table`: il sito di test ha già dati (le carte importate), che restano intatti.

#### 1.2 — `app/Models/Card.php`
Aggiungi in cima `use Illuminate\Database\Eloquent\Builder;` e:
```diff
     protected $fillable = [
         'expansion',
         'number',
         'cid',
+        'original_card_id',
+        'variant_type',
+        'variant_code',
         'unique_card',
```
e, dopo `expansionModel()`:
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
     * Alternative versions (Hyperspace, Showcase...) of this card
     * Versioni alternative (Hyperspace, Showcase...) di questa carta
     */
    public function variants()
    {
        return $this->hasMany(Card::class, 'original_card_id');
    }

    /**
     * Only original cards: the ones that appear in lists, decks and the collection
     * Solo le carte originali: quelle che compaiono in liste, mazzi e collezione
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
`Card` usa `CardBuilder` (sottoclasse di `Eloquent\Builder`): lo scope funziona uguale con `Card::originals()` e con `Card::query()->originals()`.

#### 1.3 — Numerazioni sovrapposte (Luke e Vader di SOR): id suffissato e immagini separate

Tre modifiche, tutte piccole, per le varianti il cui `{expansion}{number}` è già di un'altra carta.

**a) Migration `database/migrations/2026_10_28_090100_make_cards_expansion_number_non_unique.php`**: la coppia espansione + numero non può più essere unica. Il nuovo indice si crea **prima** di togliere l'unico, perché la FK su `expansion` ha bisogno di un indice che parta da quella colonna (altrimenti MariaDB rifiuta il `DROP`).
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some variants (Luke and Vader of SOR) use a numbering that overlaps other cards of the same expansion
     * Alcune varianti (Luke e Vader di SOR) usano una numerazione che si sovrappone ad altre carte della stessa espansione
     */
    public function up(): void
    {
        Schema::table('cards', fn (Blueprint $table) => $table->index(['expansion', 'number']));
        Schema::table('cards', fn (Blueprint $table) => $table->dropUnique(['expansion', 'number']));
    }

    public function down(): void
    {
        // fallisce se a DB ci sono già due carte con la stessa espansione e lo stesso numero (le varianti sovrapposte)
        Schema::table('cards', fn (Blueprint $table) => $table->unique(['expansion', 'number']));
        Schema::table('cards', fn (Blueprint $table) => $table->dropIndex(['expansion', 'number']));
    }
};
```
L'unicità vera resta quella della chiave primaria `id` e di `cid`. Per gli **originali** il numero resta unico di fatto (l'id non viene mai suffissato, vedi b).

**b) `app/Models/Card.php`, hook `creating`**: l'id resta `{expansion}{number}`; solo se la carta è una variante e quell'id è già di un'altra carta, si aggiunge `-{cid}`. Gli originali sono sempre importati per primi (fase 1), quindi tengono sempre l'id semplice.
```diff
         static::creating(function (Card $card) {
-            $card->id = "{$card->expansion}{$card->number}";
+            $id = "{$card->expansion}{$card->number}";
+
+            // numerazioni sovrapposte (es. Luke e Vader di SOR): la variante prende un id suffissato, l'originale non cambia.
+            // Per un originale il conflitto resta un errore, come oggi (sarebbe un dato sbagliato dell'API).
+            if ($card->original_card_id !== null && static::whereKey($id)->exists()) {
+                $id .= "-{$card->cid}";
+            }
+
+            $card->id = $id;
         });
```
Lunghezza: `cards.id` è `string(20)`; il caso peggiore (espansione di 5 lettere, numero a 4 cifre, `cid` di 10 cifre) fa esattamente 20. Se un giorno servisse di più, si allarga la colonna insieme alle FK che la usano.

**c) `app/Services/CardImageDownloader.php`**: il path usa il numero, quindi due carte con lo stesso numero scriverebbero sullo stesso file e la variante **sovrascriverebbe l'immagine dell'originale**. Si usa invece la parte dell'`id` dopo l'espansione: per gli originali è il numero (stessi path di oggi, le immagini già scaricate restano valide), per le varianti suffissate è `2-3445221668`.
```diff
-        // 3. $path = "cards/{$card->expansion}/{$card->number}-{$side}.{$ext}"
-        $path = "cards/{$card->expansion}/{$card->number}-{$side}.{$ext}";
+        // 3. path = cards/{espansione}/{parte dell'id dopo l'espansione}-{lato}.{ext}: due carte con lo stesso numero non si sovrascrivono
+        $path = 'cards/'.$card->expansion.'/'.Str::after($card->id, $card->expansion).'-'.$side.'.'.$ext;
```
(Aggiorna anche il docblock del metodo e il commento del campo `front_art_path`/`back_art_path` nel README. Se un test del downloader costruisce un `Card` senza `id`, usa `makeCard()` che lo salva e quindi lo valorizza.)

**Dove si cercano le carte per espansione + numero**: ora la stessa coppia può esistere due volte, quindi vince sempre l'originale (Step 3.1 e 3.3 per le schede e l'API; `DeckImporter` e gli altri punti si controllano con lo Step 2.8). La ricerca per `id` (`Card::find('SOR2')`) trova sempre l'originale.

**Cosa ti aspetti dopo lo scan**: le tre carte del log (`7759451019`, `8730520110`, `3445221668`) esistono come varianti con id tipo `SOR1-7759451019` e `SOR2-8730520110`, legate al loro originale; `SOR1` e `SOR2` sono invariate, con le loro immagini.

## Step 2 — Le liste mostrano solo gli originali

Con le varianti a DB (circa 7000 righe in più, più del doppio degli originali), ogni punto che oggi legge `cards` per mostrare o scegliere carte deve decidere se le vuole. Quelli che ho letto sono sotto; quelli che non ho letto (viste Blade, `Deck::cards()` nelle viste, validator) vanno cercati con lo Step 2.8.

#### 2.1 — `app/Services/CardSearch.php`
`apply()` copre in un colpo solo `/carte`, la collezione, la ricerca dell'editor mazzi e la ricerca dell'API:
```diff
-        return $query
-            ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
+        return $query
+            ->originals()
+            ->when($filters['nome'] ?? null, fn ($q, $nome) => $q->where('name', 'like', "%{$nome}%"))
```
`filterOptions()` e `getBounds()` leggono le carte per i tipi e per i limiti degli slider: stesso insieme di `apply()`.
```diff
-            'types' => Card::query()->distinct()->orderBy('type')->pluck('type'),
+            'types' => Card::originals()->distinct()->orderBy('type')->pluck('type'),
```
```diff
-            $boundsRaw = Card::query()->toBase()->selectRaw('
+            $boundsRaw = Card::originals()->toBase()->selectRaw('
```
(`getBounds()` è in cache per un'ora con chiave `cards_bounds`: dopo il deploy `php artisan cache:forget cards_bounds`.)

#### 2.2 — `app/Http/Controllers/CardController.php`, `newReleases()`
Quattro query sulle carte, tutte sugli originali (altrimenti ogni uscita mostrerebbe la carta e le sue varianti, e il conteggio nell'avviso sarebbe gonfiato):
```diff
-        $cards = Card::where('release_date', '>=', $since)
+        $cards = Card::originals()->where('release_date', '>=', $since)
```
```diff
-            $maxDate = Card::max('release_date');
+            $maxDate = Card::originals()->max('release_date');
```
```diff
-        $dates = Card::where('release_date', '>=', $since)
+        $dates = Card::originals()->where('release_date', '>=', $since)
```
```diff
-        $firstRelease = Carbon::parse(Card::min('release_date'))->format('d/m/Y');
-        $lastRelease = Carbon::parse(Card::max('release_date'))->format('d/m/Y');
+        $firstRelease = Carbon::parse(Card::originals()->min('release_date'))->format('d/m/Y');
+        $lastRelease = Carbon::parse(Card::originals()->max('release_date'))->format('d/m/Y');
```

#### 2.3 — `app/Http/Controllers/TelegramController.php`, `handleSearch()`
```diff
-        $card = Card::where('name', 'like', "%{$query}%")->first();
+        $card = Card::originals()->where('name', 'like', "%{$query}%")->first();
```
(Senza, `/search ahsoka` potrebbe rispondere con la foto di una variante.)

#### 2.4 — `app/Http/Controllers/DeckController.php`: un mazzo contiene solo originali
Due regole di validazione oggi accettano qualunque `cards.id`. Con le varianti a DB un id di variante passerebbe la validazione e finirebbe in `deck_cards`, dove i validator contano le copie per `card_id` (una Ahsoka normale + una Hyperspace sarebbero "due carte diverse" e aggirerebbero il limite di 3 copie).
`syncCards()`:
```diff
-            'cards.*.card_id' => ['required_with:cards', 'exists:cards,id'],
+            'cards.*.card_id' => ['required_with:cards', Rule::exists('cards', 'id')->whereNull('original_card_id')],
```
`addCard()`:
```diff
-            'card_id' => ['required', 'exists:cards,id'],
+            'card_id' => ['required', Rule::exists('cards', 'id')->whereNull('original_card_id')],
```
(`Rule` è già importato nel controller.) `searchCards()`, `gap()`, `copy()`, `createVersion()` e `removeCard()` non cambiano: la ricerca passa da `CardSearch::apply()` e le altre lavorano su carte già nel mazzo.

#### 2.5 — `app/Http/Controllers/CollectionController.php`, `update()`
La collezione tiene le quantità per variante di stampa (`normal`, `foil`, `hyper`, ...) **sulla carta originale**:
```diff
-            'card_id' => ['required', 'exists:cards,id'],
+            'card_id' => ['required', Rule::exists('cards', 'id')->whereNull('original_card_id')],
```
con `use Illuminate\Validation\Rule;` in testa. `index()` passa già da `CardSearch::apply()`.

#### 2.6 — `app/Services/DeckImporter.php`
Chi importa un mazzo da swudb.com o da un JSON ufficiale può avere id di varianti (per esempio `SOR_501`, l'Hyperspace di prima): oggi finirebbero in `notFound`, con le varianti a DB verrebbero trovate e bisogna riportarle all'originale. In `persist()`:
```diff
             } elseif ($row['count'] > 0) {
-                $quantities[$row['card']->id] = ($quantities[$row['card']->id] ?? 0) + $row['count'];
+                // un mazzo contiene sempre la carta originale: l'id di una variante vale quello della sua originale
+                $cardId = $row['card']->original_card_id ?? $row['card']->id;
+                $quantities[$cardId] = ($quantities[$cardId] ?? 0) + $row['count'];
             }
```
(Somma le copie se nel file compaiono sia l'originale sia una sua variante.) Nella ricerca per nome di `findByText()`:
```diff
-        return Card::query()
+        return Card::originals()
             ->where('name', $name)
```
`DeckExporter` non cambia: i mazzi hanno solo originali, quindi esporta solo id di originali.

#### 2.7 — Cosa non cambia (verificato sul codice letto)
I validator in `app/Services/DeckValidation/` (lavorano su carte già nel mazzo, che sono originali), `DeckGapCalculator`, le statistiche del mazzo, `CardBuilder` (ordinamento di default).

#### 2.8 — Controllo dei punti non letti
Da `cmd.exe` nella radice del progetto:
```
findstr /s /n /c:"Card::" app\*.php routes\*.php
findstr /s /n /c:"cards,id" app\*.php
findstr /s /n /c:"'number'" app\*.php
findstr /s /n /c:"Card::" resources\views\*.php
```
Per ogni risultato non coperto sopra decidi: "elenca/sceglie carte per un utente" → aggiungi `->originals()`; "cerca una carta per id o per cid" (import, mail, test) → resta com'è. Annota in `todo.md` ciò che trovi.

## Step 3 — Scheda carta, API

#### 3.1 — `CardController::show()`
Una variante non ha una pagina propria: chi arriva con il suo `{expansion}/{number}` (Telegram, API, un link salvato) va alla scheda dell'originale. L'originale mostra le sue altre versioni.
```php
    /**
     * Shows the public detail page for a single card, identified by expansion+number (not cid)
     * A variant redirects to its original card, which lists the alternative versions
     * Mostra la pagina di dettaglio pubblica di una singola carta, identificata da espansione+numero (non cid)
     * Una variante rimanda alla sua carta originale, che elenca le versioni alternative
     */
    public function show(string $expansion, int $number): View|RedirectResponse
    {
        // la stessa coppia espansione+numero può esistere due volte (Luke e Vader di SOR, Step 1.3): vince l'originale
        $query = Card::with(['aspects', 'traits', 'expansionModel', 'originalCard'])
            ->where('expansion', $expansion)
            ->where('number', $number);
        $card = (clone $query)->originals()->first() ?? $query->firstOrFail();

        if ($card->original_card_id !== null) {
            $original = $card->originalCard;

            return redirect()->route('cards.show', ['expansion' => $original->expansion, 'number' => $original->number], 301);
        }

        $card->load(['variants' => fn ($q) => $q->orderBy('variant_code')->orderBy('number')]);

        return view('cards.show', compact('card'));
    }
```
con `use Illuminate\Http\RedirectResponse;` in testa.

#### 3.2 — `resources/views/cards/show.blade.php`
Dopo il blocco "Abilità Da Schierato" (dentro il `div.card-ability`) aggiungi la sezione, nello stile degli altri blocchi (classi `dark:` esplicite, come da convenzioni Bladewind):
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
                                        {{ $variant->variant_type ?? 'Variante' }}
                                        <span class="text-gray-500 dark:text-gray-400">{{ $variant->expansion }}-{{ $variant->number }}</span>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </x-bladewind.card>
                @endif
```
`Storage::url()` è già usato in questa vista. Il blocco "Aspetti/Tratti" e il resto non cambiano.

#### 3.3 — API: `app/Http/Resources/CardResource.php` e `Api\CardController::show()`
La ricerca (`/api/cards/search`) restituisce già solo originali (Step 2.1). Il dettaglio resta raggiungibile anche per una variante (l'API è un riferimento stabile per chi ha già id salvati), e dice di chi è variante; per un originale elenca le varianti.
`CardResource::toArray()`, dopo `'back_image'`:
```php
            'variant_type' => $this->variant_type,
            // presenti solo nel dettaglio (relazioni caricate): evitano query in più nella ricerca
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
`Api\CardController::show()`:
```diff
-        $card = Card::with(['aspects', 'traits'])
+        $card = Card::with(['aspects', 'traits', 'originalCard', 'variants'])
```
Controlla `tests/Feature/Api/PublicApiTest.php`: se un test confronta la struttura JSON esatta della carta, aggiungi le chiavi nuove. In `show()` vale la stessa regola "prima l'originale" dello Step 3.1 (stessa coppia espansione+numero può esistere due volte).

## Step 4 — Import: due fasi, originali poi varianti

Le fasi sono due valori di una proprietà del job; il job di una pagina accoda il successivo come oggi. Quando finisce l'ultima pagina degli originali (32 pagine), accoda la pagina 1 delle varianti (circa 72 pagine) con lo **stesso `runId`** (stesso lock, stesso stato in cache, stesso messaggio Telegram di avanzamento). Solo l'ultima pagina delle varianti chiude lo scan (mail, riepilogo). Lo scan completo diventa quindi lungo circa 3,2 volte quello di oggi (103 pagine contro 32): misura la durata nello Step 6.

#### 4.1 — `app/Jobs/ImportCardsFromSwuApiJob.php`: costanti e costruttore
```diff
     public const API_URL = 'https://admin.starwarsunlimited.com/api/card-list';
 
+    /** Prima fase: le carte originali (filters[variantOf][id][$null]). */
+    public const PHASE_ORIGINALS = 'originals';
+
+    /** Seconda fase: le varianti (filters[variantOf][id][$notNull]); richiede che gli originali siano già a DB. */
+    public const PHASE_VARIANTS = 'variants';
+
     public const PAGE_SIZE = 100;
```
```diff
-    public function __construct(public int $page = 1, ?string $runId = null)
+    public function __construct(public int $page = 1, ?string $runId = null, public string $phase = self::PHASE_ORIGINALS)
```
Aggiorna anche il docblock di classe: "importa UNA pagina, prima degli originali poi delle varianti".

#### 4.2 — `handle()`
Stato iniziale con il contatore delle varianti:
```diff
             $state = [
                 'started_at' => now()->startOfSecond()->toIso8601String(),
                 'message_id' => $progress?->messageId,
                 'processed' => 0,
+                'processed_variants' => 0,
             ];
```
Filtro della richiesta (il `$` è letterale: apici singoli):
```diff
-        $response = Http::timeout(30)->retry(3, 2000, throw: false)->get(self::API_URL, [
+        $variantFilter = $this->phase === self::PHASE_VARIANTS ? '$notNull' : '$null';
+
+        $response = Http::timeout(30)->retry(3, 2000, throw: false)->get(self::API_URL, [
             'locale' => 'it',
-            'filters[variantOf][id][$null]' => 'true',
+            "filters[variantOf][id][{$variantFilter}]" => 'true',
```
Con zero varianti Strapi risponde `pageCount: 0`:
```diff
-        $lastPage = (int) ($payload['meta']['pagination']['pageCount'] ?? $this->page);
+        $lastPage = max(1, (int) ($payload['meta']['pagination']['pageCount'] ?? $this->page));
```
Messaggio di avanzamento e log con la fase:
```diff
-        $telegram->editMessage($chatId, $state['message_id'], "Scan in corso: pagina {$this->page}/{$lastPage}...");
+        $what = $this->phase === self::PHASE_VARIANTS ? 'varianti' : 'carte';
+        $telegram->editMessage($chatId, $state['message_id'], "Scan in corso ({$what}): pagina {$this->page}/{$lastPage}...");
```
```diff
-        Log::info("Scan {$this->runId}: pagina {$this->page}/{$lastPage} elaborata", [
+        Log::info("Scan {$this->runId}: pagina {$this->page}/{$lastPage} elaborata ({$this->phase})", [
```
Contatori e coda di fine pagina:
```diff
-        $state['processed'] += $processed;
+        $counter = $this->phase === self::PHASE_VARIANTS ? 'processed_variants' : 'processed';
+        $state[$counter] = ($state[$counter] ?? 0) + $processed;
         Cache::put($this->stateKey(), $state, now()->addDay());
 
         if ($this->page < $lastPage) {
-            self::dispatch($this->page + 1, $this->runId);
+            self::dispatch($this->page + 1, $this->runId, $this->phase);
 
             return;
         }
 
+        if ($this->phase === self::PHASE_ORIGINALS) {
+            // finiti gli originali: ora le varianti (hanno bisogno che l'originale sia già a DB)
+            self::dispatch(1, $this->runId, self::PHASE_VARIANTS);
+
+            return;
+        }
+
         $this->finish($telegram, $state, $chatId);
```

#### 4.3 — `processCard()` e il collegamento all'originale
Dopo `$this->createExpansionIfMissing(...)`, il valore da salvare solo per le varianti:
```diff
-            $card = Card::updateOrCreate(
+            $variantAttributes = $this->phase === self::PHASE_VARIANTS ? [
+                'original_card_id' => $this->resolveOriginalCardId($cardData),
+                // primo elemento di variantTypes (vedi Step 0 se ce n'è più d'uno): nome (es. Hyperspace) e variantId (es. 02)
+                'variant_type' => $cardData['variantTypes']['data'][0]['attributes']['name'] ?? null,
+                'variant_code' => $cardData['variantTypes']['data'][0]['attributes']['variantId'] ?? null,
+            ] : [];
+
+            $card = Card::updateOrCreate(
                 ['cid' => $cid],
-                [
+                $variantAttributes + [
                     'expansion' => $cardData['expansion']['data']['attributes']['code'] ?? null,
```
(Per gli originali `$variantAttributes` è vuoto: l'`updateOrCreate` non tocca mai `original_card_id`.) Nuovo metodo privato:
```php
    /**
     * cards.id of the original card of a variant, found through the originals' cid (cardUid of variantOf)
     * cards.id della carta originale di una variante, cercata con il cid (cardUid di variantOf)
     *
     * @throws \RuntimeException if the payload has no variantOf or the original is not in the database
     */
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
Se un'originale manca, la variante va in un `SystemError` (come ogni altro errore di carta) e finisce nel report agli admin, senza fermare lo scan. Nota: per le varianti il `SystemError` salva il payload intero (`context.raw`), che include tutta la carta originale dentro `variantOf`: con migliaia di varianti l'eventuale errore di massa pesa; se vuoi, in quel caso togli `variantOf` dal `raw` prima di salvare.

#### 4.4 — Immagini delle varianti (interruttore)
Ogni variante porta una o due immagini: con circa 7151 varianti lo spazio su disco più che raddoppia (stima nello Step 7; la VM è già al 79%). Un interruttore permette di importare prima i dati e scaricare le immagini dopo.
`config/services.php`, nell'array principale:
```php
    'swu' => [
        'variant_images' => env('SWU_VARIANT_IMAGES', true),
    ],
```
`.env.example`: `SWU_VARIANT_IMAGES=true`. In `downloadImages()` come prima istruzione:
```php
        if ($this->phase === self::PHASE_VARIANTS && ! config('services.swu.variant_images')) {
            return; // immagini delle varianti rimandate (SWU_VARIANT_IMAGES=false): si scaricano a una scansione successiva
        }
```
Rimettendo `true` e rilanciando `cards:scan --phase=variants` si scaricano le immagini mancanti (`downloadImages()` salta già i lati con il path valorizzato).

#### 4.5 — `failed()` e `finish()`
`failed()`: il comando per riprendere deve riportare la fase.
```diff
-            "Scan interrotto alla pagina {$this->page}: {$e->getMessage()}. Per riprendere: php artisan cards:scan --page={$this->page}"
+            "Scan interrotto alla pagina {$this->page} ({$this->phase}): {$e->getMessage()}. Per riprendere: php artisan cards:scan --page={$this->page} --phase={$this->phase}"
```
e nel `context` del `SystemError` aggiungi `'phase' => $this->phase`.

`finish()`: le carte "nuove" sono quelle originali nuove; le varianti nuove si contano a parte. La mail `NewCardsEmail` elenca solo originali (una nuova Hyperspace di una carta già nota non è una "carta nuova").
```diff
-        $newCount = Card::where('created_at', '>=', $since)->count();
+        $newCount = Card::originals()->where('created_at', '>=', $since)->count();
+        $newVariants = Card::whereNotNull('original_card_id')->where('created_at', '>=', $since)->count();
         $errorCount = $this->runErrors($since)->count();
```
```diff
-        $summary = "Scan completato: {$state['processed']} carte elaborate, {$newCount} nuove, {$errorCount} problemi.";
+        $summary = "Scan completato: {$state['processed']} carte e ".($state['processed_variants'] ?? 0)." varianti elaborate, {$newCount} carte nuove, {$newVariants} varianti nuove, {$errorCount} problemi.";
```
`sendNotifications()`:
```diff
-            $cards = Card::where('created_at', '>=', $since)
+            $cards = Card::originals()->where('created_at', '>=', $since)
```

#### 4.6 — `app/Console/Commands/ScanCards.php`
`--phase=originals` (default) = scansione completa (originali, poi varianti); `--phase=variants` = **solo** le varianti (utile subito dopo questo piano: gli originali sono già a DB).
```php
    protected $signature = 'cards:scan {--page=1 : Pagina da cui far partire la scansione} {--phase=originals : Fase da cui partire: originals (poi varianti) oppure variants (solo varianti)}';

    public function handle(): int
    {
        $page = max(1, (int) $this->option('page'));
        $phase = (string) $this->option('phase');

        if (! in_array($phase, [ImportCardsFromSwuApiJob::PHASE_ORIGINALS, ImportCardsFromSwuApiJob::PHASE_VARIANTS], true)) {
            $this->error('--phase deve essere "originals" oppure "variants".');

            return self::FAILURE;
        }

        ImportCardsFromSwuApiJob::dispatch($page, null, $phase);
        $this->info("Cards scan queued from page {$page} ({$phase}).");

        return self::SUCCESS;
    }
```
`TelegramController::handleScan()` resta `ImportCardsFromSwuApiJob::dispatch()`: scansione completa.

## Step 5 — Test Pest

#### 5.1 — Aggiornare i test esistenti di `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php`
Oggi `fakeSwuHttp()` risponde con la stessa pagina a qualunque richiesta `card-list`: con le due fasi la fase delle varianti riceverebbe gli stessi originali e li tratterebbe come varianti (errore `variantOf senza cardUid`), facendo fallire anche i test che si aspettano zero errori. Il nuovo helper risponde **vuoto** alla fase delle varianti, salvo che il test passi le sue varianti. Il pattern `*notNull*` va per primo perché in `Http::fake` vince il primo che combacia.
```php
/** Simula l'API SWU (originali e varianti) e risponde con un PNG finto a qualunque altro URL (immagini CDN). */
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
Due test esistenti contano le richieste e vanno ristretti a quelle degli originali:
- `pagina correttamente su piu' pagine`: `Http::recorded(fn ($request) => str_contains($request->url(), 'card-list') && ! str_contains($request->url(), 'notNull'))` → `toHaveCount(2)`.
- `riprende da una pagina con cards:scan --page`: `Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), 'pagination[page]=1') && ! str_contains($request->url(), 'notNull'))` (la fase delle varianti parte da pagina 1 per costruzione).
Cerca nel file anche `assertSent`, `assertSentCount`, `Http::recorded` e un eventuale controllo sul testo `Scan completato: ...` e aggiornali di conseguenza.

#### 5.2 — Test nuovi del job, in fondo allo stesso file
La forma della variante qui sotto è quella vera vista in `examples/test.json`: `variantOf.data.attributes.cardUid` per il collegamento, `variantTypes.data[0].attributes` con `name` e `variantId` (`"02"` per Hyperspace), numero proprio (501 contro 243 dell'originale nell'esempio reale).
```php
/** Una variante come la restituisce l'API: stessa struttura dell'originale, più variantOf e variantTypes. */
function fakeVariantEntry(string $uid, int $number, string $originalUid = '2579145458', string $type = 'Hyperspace'): array
{
    return fakeCardEntry(['attributes' => [
        'cardUid' => $uid,
        'cardNumber' => $number,
        'variantOf' => ['data' => ['id' => 7, 'attributes' => ['cardUid' => $originalUid]]],
        'variantTypes' => ['data' => [0 => ['attributes' => ['name' => $type, 'variantId' => '02']]]],
    ]]);
}

it('importa le varianti collegandole alla carta originale', function () {
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        [fakeVariantEntry('v-1', 270)],
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    $original = Card::where('cid', '2579145458')->firstOrFail();
    $variant = Card::where('cid', 'v-1')->firstOrFail();
    expect($original->original_card_id)->toBeNull()
        ->and($variant->original_card_id)->toBe($original->id)
        ->and($variant->variant_type)->toBe('Hyperspace')
        ->and($variant->variant_code)->toBe('02')
        ->and($original->variants->pluck('cid')->all())->toBe(['v-1']);
});

it('la fase originali usa $null e quella delle varianti $notNull', function () {
    fakeSwuHttp(['admin.starwarsunlimited.com/api/card-list*' => Http::response([
        'data' => [fakeCardEntry()],
        'meta' => ['pagination' => ['pageCount' => 1]],
    ])]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'filters[variantOf][id][$null]=true'));
    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'filters[variantOf][id][$notNull]=true'));
});

it('segnala un errore se l\'originale di una variante non esiste', function () {
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        [fakeVariantEntry('v-orfana', 271, originalUid: 'inesistente')],
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::where('cid', 'v-orfana')->exists())->toBeFalse();
    expect(SystemError::where('message', 'like', '%v-orfana%')->exists())->toBeTrue();
});

it('le varianti non contano come carte nuove nella mail', function () {
    User::factory()->create();
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        [fakeVariantEntry('v-1', 270), fakeVariantEntry('v-2', 271, type: 'Showcase')],
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertQueued(NewCardsEmail::class, fn ($mail) => $mail->total === 1 && $mail->newCards->count() === 1);
});

it('cards:scan --phase=variants importa solo le varianti e rifiuta fasi sconosciute', function () {
    fakeSwuHttp([]);
    Mail::fake();

    $this->artisan('cards:scan', ['--phase' => 'pippo'])->assertFailed();

    $this->artisan('cards:scan', ['--phase' => 'variants'])->assertSuccessful();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'card-list') && ! str_contains($request->url(), 'notNull'));
});

it('senza SWU_VARIANT_IMAGES non scarica le immagini delle varianti', function () {
    config(['services.swu.variant_images' => false]);
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [],
            'meta' => ['pagination' => ['pageCount' => 0]],
        ])],
        [fakeVariantEntry('v-1', 270)],
    );
    Mail::fake();
    // l'originale deve già esistere perché la variante si colleghi
    Expansion::create(['expansion' => 'SOR', 'rotation' => '0']);
    Card::create(['cid' => '2579145458', 'expansion' => 'SOR', 'number' => 5, 'name' => 'Luke Skywalker', 'type' => 'Leader', 'rarity' => 'Speciale']);

    $this->artisan('cards:scan', ['--phase' => 'variants'])->assertSuccessful();

    expect(Card::where('cid', 'v-1')->firstOrFail()->front_art_path)->toBeNull();
});
```
Dettagli da controllare scrivendoli:
- `fakeCardEntry()` parte dal fixture `storage/app/private/api-example-result.json`, dove `variantOf` è `['data' => null]` (come per gli originali veri): `array_replace_recursive` lo sostituisce con l'array della variante senza problemi.
- L'ultimo test dipende dal `cid` e dal numero dell'originale come in `fakeCardEntry()` (Luke Skywalker, `2579145458`, n. 5, già usato nel test "carta già presente"): se cambiano, adegua.
- Per gli originali il job non scrive mai `original_card_id`, quindi i test esistenti sulle carte originali non cambiano.

#### 5.2bis — Test delle numerazioni sovrapposte (Step 1.3), nello stesso file del job
```php
it('una variante con il numero di un\'altra carta prende un id suffissato e non tocca l\'originale', function () {
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        // stessa espansione e stesso numero di fakeCardEntry() (SOR5), ma carta e cardUid diversi
        [fakeVariantEntry('v-collide', 5)],
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    $original = Card::where('cid', '2579145458')->firstOrFail();
    $variant = Card::where('cid', 'v-collide')->firstOrFail();
    expect($original->id)->toBe('SOR5')
        ->and($variant->id)->toBe('SOR5-v-collide')
        ->and($variant->original_card_id)->toBe('SOR5')
        ->and($original->front_art_path)->toStartWith('cards/SOR/5-front')
        ->and($variant->front_art_path)->toStartWith('cards/SOR/5-v-collide-front')
        ->and(Card::find('SOR5')->cid)->toBe('2579145458');
    Storage::disk('public')->assertExists($original->front_art_path);
    Storage::disk('public')->assertExists($variant->front_art_path);
});

it('una scansione ripetuta non duplica la variante con id suffissato', function () {
    fakeSwuHttp(
        ['admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ])],
        [fakeVariantEntry('v-collide', 5)],
    );
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));
    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::where('cid', 'v-collide')->count())->toBe(1);
});
```
(Il numero 5 e il `cid` `2579145458` sono quelli di `fakeCardEntry()` come usati nel test "carta già presente": se differiscono, adegua. Se il lock dello scan impedisce la seconda `handle()` nello stesso test, chiudi il lock come fanno gli altri test di ripetizione.)

Aggiungi anche a `CardVariantsTest` (punto successivo) un test che con due carte con la stessa espansione e lo stesso numero, `route('cards.show')` mostri l'originale, e, se esistono test del downloader, uno che verifichi che il path di una carta con id `SOR5-v-collide` sia `cards/SOR/5-v-collide-front.png`.

#### 5.3 — Test nuovi delle liste e dei mazzi: `tests/Feature/Cards/CardVariantsTest.php`
Usa gli helper `makeCard()`/`makeDeck()` di `tests/Pest.php` (accettano `original_card_id` perché ora è `fillable`) e lo stesso modo di creare e autenticare un utente degli altri test dei mazzi (`DeckPagesTest`).
```php
<?php

use App\Models\Card;
use App\Models\User;
use App\Services\DeckImporter;
use App\Enums\DeckFormat;

it('il catalogo mostra solo le carte originali', function () {
    $original = makeCard(['name' => 'Ahsoka Tano']);
    makeCard(['name' => 'Ahsoka Tano', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace', 'variant_code' => '02']);
    makeCard(['name' => 'Ahsoka Tano', 'original_card_id' => $original->id, 'variant_type' => 'Showcase', 'variant_code' => '03']);

    $response = $this->get(route('cards.index'))->assertOk();

    expect($response->viewData('cards')->total())->toBe(1);
});

it('la scheda di una variante rimanda all\'originale, che elenca le altre versioni', function () {
    $original = makeCard(['name' => 'Ahsoka Tano']);
    $variant = makeCard(['name' => 'Ahsoka Tano', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);

    $this->get(route('cards.show', [$variant->expansion, $variant->number]))
        ->assertRedirect(route('cards.show', [$original->expansion, $original->number]));

    $this->get(route('cards.show', [$original->expansion, $original->number]))
        ->assertOk()
        ->assertSee('Altre versioni')
        ->assertSee('Hyperspace');
});

it('la ricerca dell\'API restituisce solo originali e il dettaglio elenca le varianti', function () {
    $original = makeCard(['name' => 'Ahsoka Tano']);
    $variant = makeCard(['name' => 'Ahsoka Tano', 'original_card_id' => $original->id, 'variant_type' => 'Hyperspace']);

    $this->getJson('/api/cards/search')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("/api/cards/{$original->expansion}/{$original->number}")
        ->assertOk()
        ->assertJsonPath('data.variants.0.cid', $variant->cid);
    $this->getJson("/api/cards/{$variant->expansion}/{$variant->number}")
        ->assertOk()
        ->assertJsonPath('data.original.cid', $original->cid);
});

it('un mazzo non accetta l\'id di una variante', function () {
    $user = User::factory()->create();
    $original = makeCard();
    $variant = makeCard(['original_card_id' => $original->id]);
    $deck = makeDeck($user);

    $this->actingAs($user)
        ->post(route('decks.add-card', [$user->name, $deck->name]), ['card_id' => $variant->id, 'quantity' => 1])
        ->assertSessionHasErrors('card_id');

    $this->actingAs($user)
        ->post(route('decks.add-card', [$user->name, $deck->name]), ['card_id' => $original->id, 'quantity' => 1])
        ->assertSessionHasNoErrors();
});

it('l\'import di un mazzo converte l\'id di una variante nella sua originale', function () {
    $user = User::factory()->create();
    $original = makeCard(['expansion' => 'SOR', 'number' => 134]);
    makeCard(['expansion' => 'SOR', 'number' => 501, 'original_card_id' => $original->id]);

    $result = app(DeckImporter::class)->fromContent(
        json_encode(['deck' => [['id' => 'SOR_134', 'count' => 1], ['id' => 'SOR_501', 'count' => 2]]]),
        $user,
        'Con varianti',
        DeckFormat::Premier,
    );

    expect($result['deck']->cards->pluck('pivot.quantity', 'id')->all())->toBe([$original->id => 3]);
});

it('la collezione accetta solo carte originali', function () {
    $user = User::factory()->create();
    $original = makeCard();
    $variant = makeCard(['original_card_id' => $original->id]);

    $this->actingAs($user)->patchJson(route('collection.update'), ['card_id' => $variant->id, 'variant' => 'hyper', 'quantity' => 1])
        ->assertJsonValidationErrors('card_id');
    $this->actingAs($user)->patchJson(route('collection.update'), ['card_id' => $original->id, 'variant' => 'hyper', 'quantity' => 1])
        ->assertOk();
});
```
Controlla in particolare: (a) `makeCard()` fissa `number` da un contatore statico ma accetta `number` negli attributi (`$attributes + [...]`); (b) il nome del case dell'enum (`DeckFormat::Premier`: guarda `app/Enums/DeckFormat.php`); (c) se `User::factory()` crea già utenti con email verificata (le rotte hanno il middleware `verified`: se no, usa lo stato che usano i test di `DeckPagesTest`).

#### 5.4 — Esecuzione
```
php artisan test tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php
php artisan test tests/Feature/Cards/CardVariantsTest.php
composer test
```
Atteso: suite intera verde (oggi: 122 passati, 388 asserzioni). Annota esito e data in `README.md` (sezione Test) e `todo.md`, come per i piani precedenti.

## Step 6 — Applicazione in locale (compose di sviluppo)

1. Applica gli Step 1–5, poi `docker compose -f docker-compose.dev.yml restart worker` (`queue:work` tiene il vecchio codice in memoria finché non riparte).
2. Migration e cache dei limiti del filtro:
```
docker compose -f docker-compose.dev.yml exec app php artisan migrate
docker compose -f docker-compose.dev.yml exec app php artisan cache:forget cards_bounds
```
3. **Prima delle varianti**, con il DB invariato, controlla che il comportamento sia identico a oggi: `/carte` mostra lo stesso numero di carte (3135 secondo l'ultimo import), `/nuove-uscite`, un mazzo, `/collezione` e `/api/cards/search` come prima. È il test che gli scope non abbiano cambiato nulla.
4. Importa **solo le varianti** (gli originali ci sono già; circa 72 pagine, annota quanto dura: serve per stimare lo scan completo di produzione):
```
docker compose -f docker-compose.dev.yml exec app php artisan cards:scan --phase=variants
```
5. Verifica a fine scan (messaggio Telegram "Scan completato: ..."):
```
docker compose -f docker-compose.dev.yml exec app php artisan tinker --execute="echo App\Models\Card::originals()->count().' originali, '.App\Models\Card::whereNotNull('original_card_id')->count().' varianti'.PHP_EOL; print_r(App\Models\Card::whereNotNull('original_card_id')->selectRaw('variant_type, count(*) n')->groupBy('variant_type')->pluck('n','variant_type')->all());"
```
Atteso: gli originali restano 3135; le varianti circa 7151 (totale 10286); la tabella per tipo coincide con quella dello Step 0.
6. A mano: `/carte` ha ancora lo stesso numero di carte e una sola Ahsoka; la scheda dell'Ahsoka mostra "Altre versioni" con le immagini; l'URL `{espansione}/{numero}` di una variante (per esempio `SOR/501`) rimanda all'originale; nell'editor di un mazzo la ricerca non propone varianti; `/collezione` idem; `/api/cards/search` ha lo stesso totale di prima.
7. `/admin/errori`: nessun errore "Carta originale ... non trovata" (se ci sono, vedi la riga dello Step 0 sulle varianti con originale mancante).
8. Prova la ripresa: `cards:scan --phase=variants --page=3` e controlla che il messaggio Telegram di interruzione riporti il comando con `--phase`.

## Step 7 — Rilascio sulla VM

1. **Prima del push**, sulla VM misura lo spazio e quanto pesa una carta:
```
df -h /
du -sh ~/sites/unlimiteddb/storage/app/public/cards
```
Stima di partenza dai dati dell'API: il formato `card` delle immagini pesa circa 200–230 KB (nell'esempio `size` 202,01 e 231,01 in KB), quindi circa 7151 varianti × 1 immagine frontale ≈ 1,4–1,7 GB, più i retro dei Leader. Confronta con `du` diviso 3135 (peso reale medio per carta) per precisarla. Se il risultato sta stretto nel disco libero, metti `SWU_VARIANT_IMAGES=false` nel `.env` della VM prima di lanciare lo scan delle varianti, e scarica le immagini in un secondo momento (Step 4.4).
2. Push su `new`: la pipeline del piano 11 ricostruisce l'immagine e `deploy/post-deploy.sh` lancia `migrate --force` e `queue:restart`. Verifica la riga della migration in `docker compose logs`.
3. Sulla VM, nella cartella del sito, `docker compose exec app php artisan cache:forget cards_bounds`.
4. **Non lanciare lo scan durante un altro scan** (c'è il lock `cards-import:running`, ma non fare deploy a metà import): poi `docker compose exec app php artisan cards:scan --phase=variants` (oppure `/scan` da Telegram per la scansione completa, che adesso fa anche le varianti).
5. Controlli finali come allo Step 6.5–6.7; poi `du -sh storage/app/public/cards` e `df -h /` per confrontare con la stima.
6. **Relazione con il piano 13, Step 13.4**: il reset pulito con `migrate:fresh --seed --force` e il reimport con `cards:scan` farà in automatico originali + varianti (due fasi, 103 pagine). Il numero totale di carte atteso a fine scan non sarà più 3135 ma circa 10286 (3135 carte e circa 7151 varianti); il riepilogo Telegram ora le riporta separate. Aggiorna di conseguenza il testo dello Step 13.4 quando lo esegui.

## Step 8 — Documentazione

1. **README.md**: nella sezione "Stato attuale → Funziona" il catalogo, la scheda carta (sezione "Altre versioni"), la collezione, i mazzi e l'API (nuove chiavi `variant_type`, `original`, `variants` di `CardResource`); nella sezione "Database" le tre colonne nuove di `cards`; nella sezione "Scansione carte" le due fasi, `--phase` e il riepilogo con le varianti; nella tabella delle variabili `SWU_VARIANT_IMAGES`; nella sezione Test il nuovo esito. Togli la voce "Varianti delle carte: ... non ancora implementato" aggiunta alla stesura di questo piano.
2. **todo.md**: spunta la sezione 14 e annota nel piano 13 (Step 13.4) che il conteggio atteso cambia.
3. **Indice dei piani** (`implementationPlan-ricostruzioneUnlimitedDB.md`): stato di questo piano a completato; nello "Schema del database" le righe `original_card_id`, `variant_type`, `variant_code` di `cards` perdono la nota "piano 14, non ancora applicato".
4. Rinomina questo file in `implementationPlan-V-14-variantiCarte.md`.

## Fuori da questo piano (note per dopo)

- **Ristampe (`reprintOf`)**: una carta ristampata in un'espansione successiva ha un `cardUid` e un numero propri ed è oggi importata come originale a sé stante (`variantOf` nullo): in lista compare quindi ancora due volte. È un problema diverso (stessa carta in set diversi, non stessa carta in versioni diverse) con la stessa soluzione possibile (un riferimento `reprint_of`); la API espone già la relazione `reprintOf`. Il suo effetto sulle regole di rotazione dei formati va deciso a parte.
- **Collezione e varianti importate**: `collection_cards.variant` (`normal`, `foil`, `hyper`, `prestige`, `hyper_foil`) resta un attributo del possesso sulla carta originale. Collegarlo alle carte-variante importate (mostrare l'immagine Hyperspace per le copie `hyper`, ad esempio) usando `variant_code`/`variant_type` è possibile ma non è in questo piano.
- **Indicatore "N versioni" su `<x-card>`**: con `withCount('variants')` nei controller delle liste si può mostrare un piccolo badge; rimandato per non toccare tre viste.
- **Foil**: se il foil arriva come voce `variantTypes` della stessa carta (non come carta separata), non serve nulla; se arriva come carte separate, la collezione lo gestisce già come attributo e le righe in più sono solo varianti nascoste (lo dice lo Step 0).
- **`sort` esplicito dell'API**: la paginazione dell'API non specifica un ordinamento; con 103 pagine e voci mescolate, `sort[0]=id:asc` renderebbe le pagine stabili anche se l'API cambia durante lo scan. Non l'ho aggiunto perché non so se l'endpoint personalizzato lo accetta: prova con `…&sort[0]=id:asc` nello Step 0 e, se risponde 200, aggiungilo a entrambe le fasi.
