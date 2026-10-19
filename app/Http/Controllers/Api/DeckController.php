<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeckResource;
use App\Models\Deck;

class DeckController extends Controller
{
    public function show(string $userName, string $deckName): DeckResource
    {
        $deck = Deck::with('cards.aspects', 'cards.traits')
            ->whereHas('user', fn ($q) => $q->where('name', $userName))
            ->whereDoesntHave('nextVersion')
            ->where('name', $deckName)
            ->where('is_public', true)
            ->firstOrFail();

        return new DeckResource($deck);
    }
}
