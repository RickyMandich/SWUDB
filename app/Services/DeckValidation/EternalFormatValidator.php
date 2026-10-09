<?php

namespace App\Services\DeckValidation;

use App\Models\Deck;

class EternalFormatValidator implements DeckFormatValidator
{
    public function validate(Deck $deck): array
    {
        $errors = [];

        if ($deck->leaders()->count() !== 1) {
            $errors[] = 'Il formato Eternal richiede esattamente 1 leader.';
        }
        if ($deck->baseCard()->count() !== 1) {
            $errors[] = 'Il formato Eternal richiede esattamente 1 base.';
        }

        $size = $deck->cards->whereNotIn('type', ['Leader', 'Base'])->sum('pivot.quantity');

        if ($size < 50 || ($deck->baseCard->first()?->id === 'JTL24' && $size < 60)) {
            $errors[] = 'Il formato Eternal richiede almeno 50 carte (60 per i deck con Data Vault come base).';
        }

        foreach ($deck->cards as $card) {
            $limit = $card->max_copies ?? 3;
            if ($card->pivot->quantity > $limit) {
                $errors[] = "Troppe copie di {$card->name} ({$card->pivot->quantity}/{$limit}).";
            }
        }

        return $errors;
    }
}
