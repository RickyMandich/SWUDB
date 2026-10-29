# Implementation plan 14b — Carte mancanti: le varianti contano come copie dell'originale

> **Sostituito dal 2026-10-29 da [`implementationPlan-14-variantiCarte-Correzione.md`](implementationPlan-14-variantiCarte-Correzione.md)** (Step 5). Questo file resta come storico.

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Nasce dalla nota dell'utente nello Step 2.5 di [`implementationPlan-14-variantiCarte.md`](implementationPlan-14-variantiCarte.md).
> **Prerequisito**: Step 1 e 2 di [`implementationPlan-14a-correzioniVarianti.md`](implementationPlan-14a-correzioniVarianti.md) (senza l'import di `Builder` in `Card` e senza `DeckController::show` ripristinato le pagine dei mazzi non rispondono), e le migration del piano 14 (colonna `cards.original_card_id`).
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.
> Data: 2026-10-28.

---

## Obiettivo e decisione

Un mazzo contiene sempre carte **originali** (Step 2.4 del piano 14), ma nella collezione l'utente può avere anche le singole versioni: Hyperspace, Showcase, promo (Step 2.5: ogni versione è una riga di `collection_cards` con il proprio `card_id`).

Decisione dell'utente: per ogni `card_id` di un mazzo si contano anche tutte le sue varianti. Chi ha una copia dell'originale e una di una variante possiede **2 copie** di quella carta ai fini del delta; chi ha solo una Hyperspace e il mazzo vuole l'originale non la deve più vedere tra le mancanti.

Esempio: il mazzo chiede 3 copie di Ahsoka (`SOR134`). In collezione: 1 × `SOR134`, 1 × `SOROP2` (Hyperspace, variante di `SOR134`). Risultato: presenti 2, mancanti 1.

Resta com'è: le copie "impegnate" in altri mazzi assemblati (`deck_cards` dei mazzi con `assembled = true`) si contano per carta originale, perché i mazzi contengono solo originali.

---

## Step 1 — `app/Services/DeckGapCalculator.php`

Cambia solo il calcolo di `$required` e di `$owned`; il resto del metodo (le copie impegnate altrove, `$present`, `$missing`, `$reservedElsewhere`) è invariato. Metodo completo:
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
     * Copies missing / reserved elsewhere / present for a deck, per ORIGINAL card:
     * the copies of every variant (Hyperspace, Showcase, promos...) owned by the user count as copies of the original.
     * Copie mancanti / impegnate altrove / presenti per un mazzo, per carta ORIGINALE:
     * le copie di ogni variante (Hyperspace, Showcase, promo...) possedute dall'utente contano come copie dell'originale.
     *
     * @return array{missing: array<string,int>, reservedElsewhere: array<string,int>, present: array<string,int>}
     */
    public function forDeck(Deck $deck, User $user): array
    {
        // Un mazzo contiene solo originali (Step 2.4 del piano 14), ma un mazzo creato prima del piano 14
        // potrebbe avere l'id di una variante: si riconduce comunque all'originale
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
Differenze rispetto a oggi, solo due:
- `$required`: raggruppa per `original_card_id ?? id` e somma le quantità (prima era `mapWithKeys` sull'id);
- `$owned`: `JOIN cards` e somma per `COALESCE(original_card_id, id)` invece di `GROUP BY card_id`. Il `COALESCE` identico in `SELECT` e `GROUP BY` è accettato da MariaDB con `ONLY_FULL_GROUP_BY` e da SQLite (test).

Il controller (`DeckController::gap`) e la vista `decks/gap` non cambiano: le chiavi di `missing`, `present` e `reservedElsewhere` sono ancora gli id delle carte del mazzo (originali).

---

## Step 2 — Test

Nuovo file `tests/Feature/Services/DeckGapCalculatorTest.php` (non esisteva nessun test su `DeckGapCalculator`: vedi `todo.md`, sezione 08). `makeCard()` e `makeDeck()` sono helper globali di `tests/Pest.php`:
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
Se `Deck` non ha `assembled` tra i campi riempibili, `makeDeck(..., ['assembled' => true])` lo ignorerebbe: in quel caso imposta `Deck::where('name', 'Altro mazzo')->update(['assembled' => true])` dopo la creazione.
```bash
php artisan test tests/Feature/Services/DeckGapCalculatorTest.php
```

---

## Step 3 — Fuori scope (decidi a parte)

- **Dettaglio per versione** in `decks/gap`: oggi la vista dice "mancano 1 copia di Ahsoka" senza dire quali versioni possiedi; per mostrare "possiedi: 1 normale + 1 Hyperspace" servirebbe che `forDeck()` restituisca anche il dettaglio per variante.
- **Collezione**: ogni carta mostra solo le copie di quella versione. Un badge "totale copie (originale + varianti)" sulla scheda dell'originale è una rifinitura a parte.
- **Quale versione si "usa" nel mazzo**: la scelta è solo di conteggio, nessuna versione viene assegnata al mazzo.

---

## Step 4 — Documentazione e chiusura

1. **README**: nella sezione sulla collezione e sulle carte mancanti, scrivi che il delta è calcolato per carta originale sommando le copie di tutte le sue varianti. Si aggiorna insieme al README del piano 14 (Step 9 del 14a), a codice applicato e test verdi.
2. **todo.md**: spunta la sezione 14b.
3. Rinomina il file in `implementationPlan-V-14b-deltaCollezioneVarianti.md`.
