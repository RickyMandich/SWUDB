<?php

namespace App\Services\DeckValidation;

use App\Models\Deck;
use App\Models\Expansion;

class PremierFormatValidator implements DeckFormatValidator
{
    public function validate(Deck $deck): array
    {
        $errors = [];

        if ($deck->leaders()->count() !== 1) {
            $errors[] = 'Il formato Premier richiede esattamente 1 leader.';
        }
        if ($deck->baseCard()->count() !== 1) {
            $errors[] = 'Il formato Premier richiede esattamente 1 base.';
        }

        $size = $deck->cards->whereNotIn('type', ['Leader', 'Base'])->sum('pivot.quantity');

        if ($size < 50 || ($deck->baseCard->first()?->id === 'JTL24' && $size < 60)) {
            $errors[] = 'Il formato Premier richiede almeno 50 carte (60 per i deck con Data Vault come base).';
        }

        $validExpansions = Expansion::validExpansions();
        foreach ($deck->cards as $card) {
            $limit = $card->max_copies ?? 3; // 3 è il limite standard SWU, max_copies lo sovrascrive per le eccezioni (es. JTL #256 = 15)
            if ($card->pivot->quantity > $limit) {
                $errors[] = "Troppe copie di {$card->snippet} ({$card->pivot->quantity}/{$limit}).";
            }
            if (! $validExpansions->contains($card->expansion)) {
                $errors[] = "{$card->snippet} è fuori rotazione per il formato Premier.";
            }
        }

        return $errors;
    }
}
