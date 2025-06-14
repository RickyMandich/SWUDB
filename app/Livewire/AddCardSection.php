<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Livewire component for always-visible card addition section in deck management
 * Componente Livewire per sezione sempre visibile di aggiunta carte nella gestione mazzi
 *
 * This component provides a persistent interface for adding cards to decks with:
 * - Integration with SearchFilter component for card filtering
 * - Real-time availability checking based on current deck state
 * - Copy limit validation and enforcement
 * - Event-driven communication with parent DeckManager component
 */
class AddCardSection extends Component
{
    public $selectedCardId = '';
    public $copiesAmount = 1;
    public $maxCopies = 1;
    public $availableCards = [];
    public $filteredCards = [];
    public $deckId;
    public $userId;
    public $currentDeckCards = [];

    protected $listeners = [
        'cardsFiltered' => 'updateFilteredCards',
        'updateAvailableCards' => 'updateAvailableCards'
    ];

    /**
     * Initialize the add card section component
     * Inizializza il componente sezione aggiunta carte
     *
     * @param string $userId User ID who owns the deck
     * @param string $deckId Deck identifier
     * @param array $currentDeckCards Current cards in deck with their counts
     * @param array $availableCards All available cards from database
     * @return void
     */
    public function mount($userId, $deckId, $currentDeckCards = [], $availableCards = [])
    {
        $this->userId = $userId;
        $this->deckId = $deckId;
        $this->currentDeckCards = $currentDeckCards;
        $this->availableCards = $availableCards;
        $this->filteredCards = $availableCards;
    }

    /**
     * Update filtered cards when search filter component emits results
     * Aggiorna le carte filtrate quando il componente filtro di ricerca emette risultati
     *
     * @param array $cards Filtered cards from SearchFilter component
     * @return void
     */
    public function updateFilteredCards($cards)
    {
        $this->filteredCards = $cards;
        $this->selectedCardId = '';
        $this->copiesAmount = 1;
        $this->maxCopies = 1;
    }

    /**
     * Update available cards and current deck state
     * Aggiorna le carte disponibili e lo stato attuale del mazzo
     *
     * @param array $currentDeckCards Current deck composition
     * @return void
     */
    public function updateAvailableCards($currentDeckCards)
    {
        $this->currentDeckCards = $currentDeckCards;
    }

    /**
     * Handle card selection and calculate maximum allowed copies
     * Gestisce la selezione della carta e calcola il numero massimo di copie consentite
     *
     * @return void
     */
    public function updatedSelectedCardId()
    {
        if (empty($this->selectedCardId)) {
            $this->maxCopies = 1;
            $this->copiesAmount = 1;
            return;
        }

        // Trova la carta selezionata
        $selectedCard = collect($this->filteredCards)->firstWhere('id', $this->selectedCardId);
        
        if (!$selectedCard) {
            $this->maxCopies = 1;
            $this->copiesAmount = 1;
            return;
        }

        // Calcola il numero massimo di copie che possiamo aggiungere
        $maxCopiesAllowed = $selectedCard['maxCopie'] ?? 3;
        $currentCopies = $this->currentDeckCards[$this->selectedCardId] ?? 0;
        $this->maxCopies = max(1, $maxCopiesAllowed - $currentCopies);
        
        // Assicuriamoci che copiesAmount non superi il massimo
        if ($this->copiesAmount > $this->maxCopies) {
            $this->copiesAmount = $this->maxCopies;
        }
    }

    /**
     * Add selected cards to deck
     * Aggiunge le carte selezionate al mazzo
     *
     * Dispatches an event to the parent DeckManager component with
     * the selected card ID and number of copies to add.
     *
     * @return void
     */
    public function addCardsToDeck()
    {
        if(empty($this->selectedCardId)) {
            return;
        }

        // Emettiamo un evento con l'ID della carta e il numero di copie da aggiungere
        $this->dispatch('cardAdded', [
            'cardId' => $this->selectedCardId,
            'copies' => $this->copiesAmount
        ]);

        // Reset della selezione
        $this->selectedCardId = '';
        $this->copiesAmount = 1;
        $this->maxCopies = 1;
    }

    public function render()
    {
        return view('livewire.add-card-section');
    }
}
