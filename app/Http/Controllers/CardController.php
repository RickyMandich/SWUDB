<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Expansion;
use App\Services\CardSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardController extends Controller
{
    public function index(Request $request, CardSearch $search): View
    {
        $cards = $search->apply(Card::query(), $request->only(['nome', 'espansione', 'tipo', 'costo', 'aspetto', 'tratto', 'unique_card']))
            ->orderByDesc(
                Expansion::select('legal_date')->whereColumn('expansions.expansion', 'cards.expansion')
            )
            ->orderBy('expansion')
            ->orderBy('number')
            ->paginate(24)->withQueryString();

        return view('cards.index', ['cards' => $cards, 'filters' => $request->all()]);
    }

    /**
     * Shows the public detail page for a single card, identified by expansion+number (not cid)
     * Mostra la pagina di dettaglio pubblica di una singola carta, identificata da espansione+numero (non cid)
     */
    public function show(string $expansion, int $number): View
    {
        $card = Card::with(['aspects', 'traits', 'expansionModel'])
            ->where('expansion', $expansion)
            ->where('number', $number)
            ->firstOrFail();

        return view('cards.show', compact('card'));
    }
}
