<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CollectionCard;
use App\Services\CardSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request, CardSearch $search): View
    {
        // stessi filtri e stesse opzioni di /carte (CardController::index): il form <x-cards-filter> li vuole tutti
        $cards = $search
            ->apply(Card::query()->with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS), includeVariants: true)
            ->with(['collectionCards' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->paginate(24)->withQueryString();

        return view('collection.index', [
            'cards' => $cards,
            'filters' => $request->all(),
        ] + $search->filterOptions());
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:65535'], // la colonna è unsignedSmallInteger
        ]);

        $key = ['user_id' => $request->user()->id, 'card_id' => $validated['card_id']];

        if ((int) $validated['quantity'] === 0) {
            CollectionCard::where($key)->delete(); // niente righe a zero: la somma per card_id resta corretta senza filtrarle ovunque
        } else {
            CollectionCard::updateOrCreate($key, ['quantity' => $validated['quantity']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
