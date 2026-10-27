<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Services\CardSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CardController extends Controller
{
    public function index(Request $request, CardSearch $search): View
    {
        $cards = $search
            ->apply(Card::query()->with(['aspects', 'traits']), $request->only(CardSearch::FILTER_KEYS))
            ->paginate(24)
            ->withQueryString();

        return view('cards.index', [
            'cards' => $cards,
            'filters' => $request->all(),
        ] + $search->filterOptions());
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

    public function newReleases(Request $request)// : View
    {
        $request->validate(['since' => ['nullable', 'date_format:d/m/Y']]);

        $sinceInput = $request->query('since');

        if (! $sinceInput) {
            $maxDate = Card::max('release_date');
            $sinceInput = ($maxDate ? Carbon::parse($maxDate) : Carbon::today())->format('d/m/Y');
        }

        $since = Carbon::createFromFormat('d/m/Y', $sinceInput)->startOfDay()->toDateString();

        $cards = Card::where('release_date', '>=', $since)
            ->orderByDesc('release_date')
            ->withDefaultOrder()
            ->paginate(24)
            ->withQueryString();

        $groups = $cards->getCollection()
            ->groupBy(fn (Card $card) => $card->release_date?->format('d/m/Y') ?? 'nd');

        $dates = Card::where('release_date', '>=', $since)
            ->distinct()
            ->count('release_date');

        $firstRelease = Carbon::parse(Card::min('release_date'))->format('d/m/Y');
        $lastRelease = Carbon::parse(Card::max('release_date'))->format('d/m/Y');

        $since = $sinceInput;

        return view('cards.new-releases', compact('cards', 'groups', 'since', 'firstRelease', 'lastRelease', 'dates'));
    }
}
