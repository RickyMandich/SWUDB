<?php

namespace App\Http\Controllers;

use App\Enums\DeckFormat;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckValidation\DeckFormatValidatorFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeckController extends Controller
{
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
            ->where('is_public', true)
            ->when(auth()->id(), fn ($q, $userId) => $q->orWhere('user_id', $userId))
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
     * Visualizzazione in sola lettura (accessibile se mazzo pubblico o se proprietario)
     */
    public function show(string $username, string $deckname, ?int $version = null): View
    {
        $deck = $this->resolveDeck($username, $deckname, $version);
        Gate::authorize('view', $deck);

        $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);
        $validationErrors = DeckFormatValidatorFactory::make($deck->format)->validate($deck);

        return view('decks.show', compact('deck', 'validationErrors'));
    }

    /**
     * Pagina di deck-building/modifica (riservata esclusivamente al proprietario)
     */
    public function edit(string $username, string $deckname): View
    {
        $deck = $this->resolveDeck($username, $deckname);
        Gate::authorize('update', $deck);

        $deck->load(['cards.aspects', 'cards.traits', 'leaders', 'baseCard', 'user']);

        return view('decks.edit', compact('deck'));
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
}
