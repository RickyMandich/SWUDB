<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Card;
use Illuminate\Support\Facades\Cache;

/**
 * Livewire component for card selection popup in deck management
 * Componente Livewire per popup di selezione carte nella gestione mazzi
 *
 * This component provides a modal interface for adding cards to decks with:
 * - Integration with SearchFilter component for card filtering
 * - Real-time availability checking based on current deck state
 * - Copy limit validation and enforcement
 * - Event-driven communication with parent DeckManager component
 */
class AddCardPopUp extends Component
{
    public $isOpen = false;
    public $selectedCardId = '';
    public $copiesAmount = 1;
    public $maxCopies = 1;
    public $availableCards = [];
    public $filteredCards = [];
    public $deckId;
    public $userId;

    // Eventi custom che verranno ascoltati dalla vista principale
    protected $listeners = [
        'openAddCardPopup' => 'open',
        'updateAvailableCards' => 'updateCards',
        'cardsFiltered' => 'updateFilteredCards'
    ];
    
    /**
     * Initialize the popup component with user, deck and card data
     * Inizializza il componente popup con dati utente, mazzo e carte
     *
     * @param int $userId Current user ID
     * @param int $deckId Current deck ID
     * @param array $currentDeckCards Current cards in deck with counts
     * @param array $availableCards Available cards from database
     * @return void
     */
    public function mount($userId, $deckId, $currentDeckCards = [], $availableCards = [])
    {
        $this->userId = $userId;
        $this->deckId = $deckId;
        $this->loadAvailableCards($currentDeckCards, $availableCards);
        $this->filteredCards = $this->availableCards; // Initialize filtered cards
    }
    
    /**
     * Load and filter available cards based on current deck state
     * Carica e filtra le carte disponibili in base allo stato attuale del mazzo
     *
     * @param array $currentDeckCards Current cards in deck with their counts
     * @param array $availableCards Available cards from database (optional)
     * @return void
     */
    public function loadAvailableCards($currentDeckCards = [], $availableCards = [])
    {
        if (empty($availableCards)) {
            // Se non sono state fornite le carte disponibili, caricale dalla cache o dal database
            $cards = Cache::remember('cards_popup_basic', 3600, function () {
                return Card::select("espansione", "numero", "nome", "titolo", "maxCopie")->get();
            });
        } else {
            // MessageCreated::dispatch("get card from array");
            $cards = collect($availableCards);
        }
        
        $this->availableCards = $cards->map(function($card) use ($currentDeckCards) {
            // $card = (array)$card;
            
            // Creiamo una chiave per ogni carta
            $key = $card['espansione'] . '-' . $card['numero'];
            
            // Determiniamo se la carta è già nel mazzo al massimo
            $isMaxed = isset($currentDeckCards[$key]) && $currentDeckCards[$key] >= $card['maxCopie'];
            
            // Creiamo lo snippet se non esiste già
            $snippet = isset($card['snippet']) ? $card['snippet'] : 
                "$key - ".$card['nome'].(strlen($card['titolo']) > 0 ? ", ". strtoupper($card['titolo']) : "");
            
            return [
                'id' => $key,
                'maxCopies' => $card['maxCopie'],
                'snippet' => $snippet,
                'isMaxed' => $isMaxed
            ];
        })->filter(function($card) {
            // Filtriamo le carte che hanno già raggiunto il massimo di copie
            return !$card['isMaxed'];
        })->toArray();
    }
    
    /**
     * Handle card selection change and update max copies limit
     * Gestisce il cambio di selezione carta e aggiorna il limite massimo di copie
     *
     * @return void
     */
    public function updatedSelectedCardId()
    {
        if(!empty($this->selectedCardId)) {
            // Quando viene selezionata una carta, impostiamo il numero massimo di copie
            foreach($this->availableCards as $card) {
                if($card['id'] === $this->selectedCardId) {
                    $this->maxCopies = $card['maxCopies'];
                    $this->copiesAmount = 1; // Reset del contatore
                    break;
                }
            }
        }
    }
    
    /**
     * Open the popup modal
     * Apre il popup modale
     *
     * @return void
     */
    public function open()
    {
        $this->isOpen = true;
        $this->filteredCards = []; // Inizializza con nessuna carta
    }

    /**
     * Close the popup modal and reset form state
     * Chiude il popup modale e reimposta lo stato del form
     *
     * @return void
     */
    public function close()
    {
        $this->isOpen = false;
        $this->reset(['selectedCardId', 'copiesAmount']);
    }

    /**
     * Update available cards based on current deck state
     * Aggiorna le carte disponibili in base allo stato attuale del mazzo
     *
     * @param array $currentDeckCards Current deck composition with card counts
     * @return void
     */
    public function updateCards($currentDeckCards)
    {
        $this->loadAvailableCards($currentDeckCards);
        $this->filteredCards = $this->availableCards;
    }

    /**
     * Update filtered cards from SearchFilter component
     * Aggiorna le carte filtrate dal componente SearchFilter
     *
     * @param array $cards Filtered cards from search component
     * @return void
     */
    public function updateFilteredCards($cards)
    {
        // Filtra le carte in base ai filtri applicati e alle carte disponibili
        $availableCardIds = collect($this->availableCards)->pluck('id')->toArray();

        $this->filteredCards = collect($cards)->filter(function($card) use ($availableCardIds) {
            $cardId = $card['espansione'] . '-' . $card['numero'];
            return in_array($cardId, $availableCardIds);
        })->map(function($card) {
            $cardId = $card['espansione'] . '-' . $card['numero'];
            $snippet = "$cardId - " . $card['nome'] . (strlen($card['titolo']) > 0 ? ", " . strtoupper($card['titolo']) : "");

            return [
                'id' => $cardId,
                'maxCopies' => $card['maxCopie'],
                'snippet' => $snippet,
                'isMaxed' => false
            ];
        })->toArray();
    }
    
    /**
     * Add selected cards to deck and close popup
     * Aggiunge le carte selezionate al mazzo e chiude il popup
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

        $this->close();
    }
    
    public function render()
    {
        return view('livewire.add-card-pop-up');
    }
}