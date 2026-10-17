<?php

namespace App\Services;

use App\Models\CollectionCard;
use App\Models\Deck;
use App\Models\DeckCard;
use App\Models\User;

class DeckGapCalculator
{
    /**
     * @return array{missing: array<string,int>, reservedElsewhere: array<string,int>, present: array<string,int>}
     */
    public function forDeck(Deck $deck, User $user): array
    {
        $required = $deck->cards->mapWithKeys(fn ($c) => [$c->id => $c->pivot->quantity]);

        $owned = CollectionCard::where('user_id', $user->id)
            ->selectRaw('card_id, SUM(quantity) as qty')->groupBy('card_id')->pluck('qty', 'card_id');

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
