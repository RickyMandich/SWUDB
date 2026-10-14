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
        $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo']))
            ->with(['collectionCards' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->paginate(24)->withQueryString();

        return view('collection.index', compact('cards'));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'variant' => ['required', 'in:normal,foil,hyper,prestige,hyper_foil'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $key = ['user_id' => $request->user()->id, 'card_id' => $validated['card_id'], 'variant' => $validated['variant']];

        if ($validated['quantity'] === 0) {
            CollectionCard::where($key)->delete(); // niente righe a zero: la somma per card_id resta corretta senza filtrarle ovunque
        } else {
            CollectionCard::updateOrCreate($key, ['quantity' => $validated['quantity']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
