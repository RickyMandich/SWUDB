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
