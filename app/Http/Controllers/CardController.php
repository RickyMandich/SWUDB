<?php

namespace App\Http\Controllers;

use App\Models\Aspect;
use App\Models\Card;
use App\Models\CardTrait;
use App\Models\Expansion;
use App\Services\CardSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardController extends Controller
{
    public function index(Request $request, CardSearch $search): View
    {
        $cards = $search
            ->apply(
                Card::query()->with(['aspects', 'traits']),
                $request->only([
                    'nome',
                    'espansioni',
                    'tipi',
                    'aspetti',
                    'tratti',
                    'costo_min',
                    'costo_max',
                    'vita_min',
                    'vita_max',
                    'potenza_min',
                    'potenza_max',
                    'unique_card',
                ])
            )
            ->orderByDesc(
                Expansion::select('legal_date')
                    ->whereColumn('expansions.expansion', 'cards.expansion')
            )
            ->orderBy('expansion')
            ->orderBy('number')
            ->paginate(config('app.env') === 'production' ? 24 : 3)
            ->withQueryString();

        return view('cards.index', [
            'cards' => $cards,
            'filters' => $request->all(),
            'bounds' => $search->getBounds(),
            'expansions' => Expansion::orderByDesc('legal_date')
                ->where(function ($q) {
                    $q->whereColumn('group_main_expansion', 'expansion')
                        ->orWhereNull('group_main_expansion');
                })
                ->pluck('expansion'),
            'types' => Card::query()->distinct()->orderBy('type')->pluck('type'),
            'aspects' => Aspect::orderBy('order')->get(['id', 'name']),
            'traits' => CardTrait::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * Shows the public detail page for a single card, identified by expansion+number (not cid)
     * Mostra la pagina di dettaglio pubblica di una singola carta, identificata da espansione+numero (non cid)
     */
    public function show(string $expansion, int $number)
    {
        $card = Card::with(['aspects', 'traits', 'expansionModel'])
            ->where('expansion', $expansion)
            ->where('number', $number)
            ->firstOrFail();

        return view('cards.show', compact('card'));
    }
}
