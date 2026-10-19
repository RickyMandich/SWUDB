<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Services\CardSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CardController extends Controller
{
    public function show(string $expansion, int $number): CardResource
    {
        $card = Card::with(['aspects', 'traits'])
            ->where('expansion', $expansion)->where('number', $number)->firstOrFail();

        return new CardResource($card);
    }

    public function search(Request $request, CardSearch $search): AnonymousResourceCollection
    {
        $cards = $search
            ->apply(
                Card::with(['aspects', 'traits']),
                $request->only(CardSearch::FILTER_KEYS)
            )
            ->paginate(24)
            ->withQueryString();

        return CardResource::collection($cards);
    }
}
