<?php

namespace App\Services\DeckValidation;

use App\Models\Card;
use App\Models\Deck;

class TwinSunsFormatValidator implements DeckFormatValidator
{
    public function validate(Deck $deck): array
    {
        $errors = [];

        if ($deck->leaders()->count() !== 2) {
            $errors[] = 'Il formato Twin Suns richiede esattamente 2 leader.';
        }

        $secondaries = $deck->leaders()->with('aspects')->get()
            ->map(fn (Card $leader) => $leader->secondaryAspect())
            ->filter()          // scarta i leader senza aspetto secondario
            ->unique('id');     // due leader con lo stesso aspetto contano come uno

        if ($secondaries->count() > 1) {
            $errors[] = 'I due leader hanno aspetti secondari diversi.';
        }

        if ($deck->baseCard()->count() !== 1) {
            $errors[] = 'Il formato Twin Suns richiede esattamente 1 base.';
        }

        $size = $deck->cards->whereNotIn('type', ['Leader', 'Base'])->sum('pivot.quantity');

        if ($size < 80 || ($deck->baseCard->first()?->id === 'JTL24' && $size < 90)) {
            $errors[] = 'Il formato Twin Suns richiede almeno 80 carte (90 per i deck con Data Vault come base).';
        }

        foreach ($deck->cards as $card) {
            $limit = $card->max_copies ?? 1;
            if ($card->pivot->quantity > $limit) {
                $errors[] = "Troppe copie di {$card->name} ({$card->pivot->quantity}/{$limit}).";
            }
        }

        return $errors;
    }
}
