<?php

namespace App\Http\Controllers;

use App\Enums\DeckFormat;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\CardSearch;
use App\Services\DeckExporter;
use App\Services\DeckGapCalculator;
use App\Services\DeckValidation\DeckFormatValidatorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeckController extends Controller
{
    /**
     * Card types that can be part of a deck (tokens are excluded)
     * Tipi di carta che possono stare in un mazzo (i segnalini sono esclusi)
     */
    protected const DECK_CARD_TYPES = ['Base', 'Evento', 'Leader', 'Miglioria', 'Unità'];

    /**
     * Risolve il mazzo dall'utente e dal nome mazzo: l'ultima versione, oppure quella indicata da $version
     */
    protected function resolveDeck(string $username, string $deckname, ?int $version = null): Deck
    {
        $user = User::where('name', $username)->firstOrFail();

        return Deck::where('user_id', $user->id)
            ->where('name', $deckname)
            ->when($version !== null, fn ($q) => $q->where('version', $version))
            ->latest('version')
            ->firstOrFail();
    }

    /**
     * Elenca i mazzi pubblici più quelli dell'utente loggato
     */
    public function index(): View
    {
        $decks = Deck::with('leaders', 'baseCard', 'user') // eager load: anteprima nella lista senza N+1
            ->whereDoesntHave('nextVersion') // solo l'ultima versione di ogni catena
            ->where(function ($q) {
                $q->where('is_public', true)
                    ->when(auth()->id(), fn ($q, $userId) => $q->orWhere('user_id', $userId));
            })
            ->latest()
            ->paginate(20);

        return view('decks.index', compact('decks'));
    }

    public function create(): View
    {
        return view('decks.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('decks')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
            'format' => ['required', Rule::enum(DeckFormat::class)],
            'is_public' => ['boolean'],
        ]);

        $deck = Deck::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'format' => $validated['format'],
            'is_public' => $request->boolean('is_public'),
            'version' => 1,
        ]);

        return redirect()->route('decks.edit', [
            'username' => $request->user()->name,
            'deckname' => $deck->name,
        ]);
    }

    /**
     * Calcola i dati statistici di un mazzo (totale, curva costi per tipo, grafici avanzati, tratti, medie)
     */
    protected function getDeckStatisticsData(Deck $deck): array
    {
        // Solo il mazzo vero e proprio: leader e base hanno un ruolo a parte e la base non ha costo
        $main = $deck->cards->reject(fn (Card $card) => in_array($card->type, ['Leader', 'Base']));
        $qty = fn (Card $card): int => (int) $card->pivot->quantity;
        $total = $main->sum($qty);

        // Curva dei costi senza buchi: ogni costo da 0 al massimo
        $curve = $main->groupBy(fn (Card $card) => (int) $card->cost)->map(fn ($cards) => $cards->sum($qty));
        $maxCost = $curve->keys()->max();
        $allCosts = $maxCost !== null ? range(0, (int) $maxCost) : [0];
        $costCurve = collect($allCosts)->mapWithKeys(fn (int $cost) => [$cost => $curve[$cost] ?? 0]);

        // Curve dei costi suddivise per tipo di carta per i toggle interattivi
        $curveForType = function (string $type) use ($main, $allCosts, $qty) {
            $cardsOfType = $main->where('type', $type);
            $typeCurve = $cardsOfType->groupBy(fn (Card $c) => (int) $c->cost)->map(fn ($cards) => $cards->sum($qty));

            return collect($allCosts)->mapWithKeys(fn (int $cost) => [$cost => $typeCurve[$cost] ?? 0])->values()->all();
        };

        $costCurveByType = [
            'Unità' => $curveForType('Unità'),
            'Miglioria' => $curveForType('Miglioria'),
            'Evento' => $curveForType('Evento'),
        ];

        $byType = $main->groupBy('type')->map(fn ($cards) => $cards->sum($qty));

        // Tratti pesati per copie: 3 copie di un'unità con un tratto contano 3.
        // Si chiama $deckTraits (non $traits) per non collidere con le opzioni del filtro carte, che nella vista edit usano $traits
        $deckTraits = $main
            ->flatMap(fn (Card $card) => $card->traits->map(fn ($trait) => ['name' => $trait->name, 'qty' => $qty($card)]))
            ->groupBy('name')
            ->map(fn ($rows) => $rows->sum('qty'))
            ->sortDesc();

        // Medie potenza e vita delle unità complessive (per retrocompatibilità test)
        $units = $main->where('type', 'Unità');
        $unitCount = $units->sum($qty);
        $avgPower = $unitCount ? $units->sum(fn (Card $card) => (int) $card->power * $qty($card)) / $unitCount : null;
        $avgHealth = $unitCount ? $units->sum(fn (Card $card) => (int) $card->health * $qty($card)) / $unitCount : null;

        // Potenza e vita medie per costo delle unità (grafico a diffusione / linee)
        $unitsByCost = $units->groupBy(fn (Card $c) => (int) $c->cost)->sortKeys();
        $powerHealthByCost = [];
        foreach ($unitsByCost as $cost => $cards) {
            $cCount = $cards->sum($qty);
            if ($cCount > 0) {
                $powerHealthByCost[] = [
                    'cost' => (int) $cost,
                    'power' => round($cards->sum(fn (Card $c) => (int) $c->power * $qty($c)) / $cCount, 2),
                    'health' => round($cards->sum(fn (Card $c) => (int) $c->health * $qty($c)) / $cCount, 2),
                ];
            }
        }

        // Grafico a torta degli aspetti (con fette zebrate per carte multi-aspetto)
        $aspectGroups = [];
        foreach ($main as $card) {
            $aspects = $card->aspects->sortBy('pivot.position');
            if ($aspects->isEmpty()) {
                $key = 'Neutrale';
                $colors = ['#9ca3af'];
            } else {
                $key = $aspects->pluck('name')->join(' / ');
                $colors = $aspects->map(fn ($a) => $a->color ?: '#9ca3af')->values()->all();
            }

            if (! isset($aspectGroups[$key])) {
                $aspectGroups[$key] = [
                    'label' => $key,
                    'count' => 0,
                    'colors' => $colors,
                ];
            }
            $aspectGroups[$key]['count'] += $qty($card);
        }
        $aspectStats = array_values($aspectGroups);

        // Grafico a torta delle rarità:
        // Colori specificati: leggendaria#00c8f3, rara#f1c000, non comune#d1d1d1, comune#964d02, speciale#000000
        $rarityColorMap = [
            'leggendaria' => '#00c8f3',
            'rara' => '#f1c000',
            'non comune' => '#d1d1d1',
            'comune' => '#964d02',
            'speciale' => '#000000',
        ];

        $rarityGroups = [];
        foreach ($main as $card) {
            $rawRarity = trim((string) ($card->rarity ?: 'Comune'));
            $normRarity = strtolower($rawRarity);
            $color = $rarityColorMap[$normRarity] ?? '#9ca3af';

            if (! isset($rarityGroups[$rawRarity])) {
                $rarityGroups[$rawRarity] = [
                    'label' => $rawRarity,
                    'count' => 0,
                    'color' => $color,
                ];
            }
            $rarityGroups[$rawRarity]['count'] += $qty($card);
        }

        $rarityOrder = ['Speciale' => 1, 'Comune' => 2, 'Non comune' => 3, 'Rara' => 4, 'Leggendaria' => 5];
        uksort($rarityGroups, fn ($a, $b) => ($rarityOrder[$a] ?? 99) <=> ($rarityOrder[$b] ?? 99));
        $rarityStats = array_values($rarityGroups);

        return compact(
            'total',
            'costCurve',
            'costCurveByType',
            'byType',
            'deckTraits',
            'avgPower',
            'avgHealth',
            'powerHealthByCost',
            'aspectStats',
            'rarityStats'
        );
    }

    /**
     * Visualizzazione in sola lettura (accessibile se mazzo pubblico o se proprietario)
     */
    public function show(string $username, string $deckname, ?int $version = null): View
    {
        $deck = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $deck);

        $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);
        $validationErrors = DeckFormatValidatorFactory::make($deck->format)->validate($deck);
        $isLatest = ! $deck->nextVersion()->exists();
        $stats = $this->getDeckStatisticsData($deck);

        return view('decks.show', compact('deck', 'validationErrors', 'isLatest') + $stats);
    }

    /**
     * Reindirizza alla sezione statistiche incorporata a fondo pagina nella vista show del mazzo
     */
    public function statistics(string $username, string $deckname, ?int $version = null): RedirectResponse
    {
        $deck = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $deck);

        $params = ['username' => $username, 'deckname' => $deckname];
        if ($version !== null) {
            $params['version'] = $version;
        }

        return redirect()->route('decks.show', $params)->withFragment('statistiche');
    }

    /**
     * Pagina di deck-building/modifica (riservata esclusivamente al proprietario)
     */
    public function edit(string $username, string $deckname, CardSearch $search): View
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);

        $options = $search->filterOptions();
        $options['types'] = collect(self::DECK_CARD_TYPES); // niente segnalini tra i badge del filtro
        $stats = $this->getDeckStatisticsData($deck);

        return view('decks.edit', ['deck' => $deck] + $options + $stats);
    }

    /**
     * Salvataggio batch delle carte del mazzo (flusso principale di modifica)
     * Riceve l'elenco completo o delta delle carte, aggiunge/aggiorna quelle con quantity > 0
     * e stacca (detach) quelle rimosse o con quantity = 0.
     * Leader e base sono righe di `deck_cards` come le altre: il form deve inviarle sempre (anche per poterle cambiare),
     * altrimenti `sync()` le stacca dal mazzo.
     */
    public function syncCards(Request $request, string $username, string $deckname): RedirectResponse
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $validated = $request->validate([
            'cards' => ['nullable', 'array'],
            'cards.*.card_id' => ['required_with:cards', 'exists:cards,id'],
            'cards.*.quantity' => ['required_with:cards', 'integer', 'min:0'],
        ]);

        $syncData = collect($validated['cards'] ?? [])
            ->filter(fn ($item) => (int) ($item['quantity'] ?? 0) > 0)
            ->keyBy('card_id')
            ->map(fn ($item) => ['quantity' => (int) $item['quantity']])
            ->all();

        $deck->cards()->sync($syncData);

        $errors = DeckFormatValidatorFactory::make($deck->format)->validate($deck->fresh());

        return redirect()->route('decks.edit', [$username, $deckname])
            ->with('status', 'Modifiche al mazzo salvate con successo.')
            ->with('deck-errors', $errors);
    }

    /**
     * Adds copies of a card: the quantity is added to the one already in the deck, it does not replace it
     * Aggiunge copie di una carta: la quantità si somma a quella già nel mazzo, non la sostituisce
     */
    public function addCard(Request $request, string $username, string $deckname): RedirectResponse
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $validated = $request->validate([
            'card_id' => ['required', 'exists:cards,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $current = $deck->cards()->find($validated['card_id'])?->pivot->quantity ?? 0;

        $deck->cards()->syncWithoutDetaching([
            $validated['card_id'] => ['quantity' => $current + $validated['quantity']],
        ]);

        $errors = DeckFormatValidatorFactory::make($deck->format)->validate($deck->fresh());

        return back()->with('deck-errors', $errors);
    }

    public function removeCard(string $username, string $deckname, Card $card): RedirectResponse
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $deck->cards()->detach($card->id);

        return back();
    }

    public function toggleAssembled(string $username, string $deckname): RedirectResponse
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $deck->update(['assembled' => ! $deck->assembled]);

        return back();
    }

    /**
     * Snapshot on-demand: crea una nuova versione (v2, v3...) clonando le carte del mazzo attuale
     */
    public function createVersion(string $username, string $deckname): RedirectResponse
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $newDeck = DB::transaction(function () use ($deck) {
            $newVersion = $deck->replicate(['assembled']);
            $newVersion->version = $deck->version + 1;
            $newVersion->previous_version_id = $deck->id;
            $newVersion->save();

            // Snapshot delle sole ~25-30 righe di deck_cards
            foreach ($deck->cards as $card) {
                $newVersion->cards()->attach($card->id, [
                    'quantity' => $card->pivot->quantity,
                ]);
            }

            return $newVersion;
        });

        return redirect()->route('decks.edit', [
            'username' => $username,
            'deckname' => $newDeck->name,
        ])->with('status', "Nuova versione v{$newDeck->version} creata con successo.");
    }

    /**
     * Elenca l'intera catena di versioni a cui appartiene il mazzo, dalla più vecchia
     */
    public function versions(string $username, string $deckname): View
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('view', $deck);

        $chain = collect([$deck]);
        for ($d = $deck; $d->previousVersion; $d = $d->previousVersion) {
            $chain->prepend($d->previousVersion);
        }
        for ($d = $deck; $d->nextVersion; $d = $d->nextVersion) {
            $chain->push($d->nextVersion);
        }

        return view('decks.versions', ['deck' => $deck, 'decks' => $chain]);
    }

    /**
     * Searches cards to add to the deck, paginated JSON for the deck-building page (owner only)
     * Cerca carte da aggiungere al mazzo, JSON paginato per la pagina di deck-building (solo proprietario)
     */
    public function searchCards(Request $request, string $username, string $deckname, CardSearch $search): JsonResponse
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $validated = $request->validate([
            'nome' => ['nullable', 'string', 'max:100'],
            'tipi' => ['nullable', 'array'],
            'tipi.*' => ['string', Rule::in(self::DECK_CARD_TYPES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = $request->only(CardSearch::FILTER_KEYS);
        $filters['tipi'] = ($validated['tipi'] ?? []) ?: self::DECK_CARD_TYPES; // senza tipo scelto: tutti i tipi da mazzo, mai i segnalini

        $cards = $search->apply(Card::with('aspect'), $filters)->paginate(30); // il numero di pagina lo legge da solo da ?page=

        return response()->json([
            'data' => $cards->getCollection()->map(fn (Card $card) => [
                'id' => $card->id,
                'name' => $card->name,
                'title' => $card->title,
                'type' => $card->type,
                'cost' => $card->cost,
                'number' => $card->number,
                'aspect_order' => $card->aspect_order_key,
                'label' => $card->snippet,
                'image' => $card->front_art_path ? asset('storage/'.$card->front_art_path) : null,
                'url' => route('cards.show', [$card->expansion, $card->number]),
            ])->values(),
            'current_page' => $cards->currentPage(),
            'last_page' => $cards->lastPage(),
            'total' => $cards->total(),
        ]);
    }

    /**
     * Downloads the deck as a TXT or JSON file (public if the deck is public, otherwise owner only)
     * Scarica il mazzo come file TXT o JSON (pubblico se il mazzo è pubblico, altrimenti solo proprietario)
     */
    public function export(DeckExporter $exporter, string $username, string $deckname, string $format, ?int $version = null): Response
    {
        $deck = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $deck);

        $content = $format === 'json' ? $exporter->toJson($deck) : $exporter->toText($deck);
        $filename = (Str::slug($deck->name) ?: 'mazzo')."-v{$deck->version}.{$format}";

        return response($content, 200, [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Copia un mazzo visibile (nella versione indicata) come nuovo mazzo privato dell'utente loggato
     */
    public function copy(Request $request, string $username, string $deckname, ?int $version = null): RedirectResponse
    {
        $source = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $source);

        $user = $request->user();

        // "copia di X", poi "copia di X (2)", "(3)"...: (user_id, name, version) è unico
        $base = Str::limit('copia di '.$source->name, 240, '');
        $name = $base;
        for ($n = 2; Deck::where('user_id', $user->id)->where('name', $name)->exists(); $n++) {
            $name = "{$base} ({$n})";
        }

        $copy = DB::transaction(function () use ($source, $user, $name) {
            $copy = Deck::create([
                'user_id' => $user->id,
                'name' => $name,
                'format' => $source->format,
                'is_public' => false,
                'version' => 1,
            ]);

            $copy->cards()->attach(
                $source->cards->mapWithKeys(fn (Card $card) => [$card->id => ['quantity' => $card->pivot->quantity]])->all()
            );

            return $copy;
        });

        return redirect()->route('decks.edit', [$user->name, $copy->name])
            ->with('status', "Copia creata da {$username}/{$source->name} v{$source->version}.");
    }

    /**
     * Carte mancanti / presenti del mazzo rispetto alla collezione di chi guarda (non del proprietario)
     */
    public function gap(Request $request, DeckGapCalculator $calculator, string $username, string $deckname, ?int $version = null): View
    {
        $deck = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $deck);

        $mode = $request->query('mostra') === 'presenti' ? 'presenti' : 'mancanti'; // default: mancanti; qualunque altro valore vale "mancanti"
        $gap = $calculator->forDeck($deck, $request->user());

        // totali in copie, sempre mostrati sopra le liste qualunque sia la modalità
        $summary = [
            'needed' => (int) $deck->cards->sum('pivot.quantity'),
            'present' => array_sum($gap['present']),
            'reserved' => array_sum($gap['reservedElsewhere']),
            'missing' => array_sum($gap['missing']),
        ];

        $quantities = $mode === 'presenti'
            ? ['present' => $gap['present']]
            : ['missing' => $gap['missing'], 'reserved' => $gap['reservedElsewhere']];

        // una sola query; `get()` applica l'ordinamento di default di CardBuilder (Leader, Base, aspetti, tipo, costo...) e `keyBy` lo conserva
        $cards = Card::whereIn('id', collect($quantities)->flatMap(fn ($q) => array_keys($q))->unique()->values())
            ->get()->keyBy('id');

        return view('decks.gap', compact('deck', 'mode', 'quantities', 'cards', 'summary'));
    }
}
