<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Card;

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
    
    public function mount($userId, $deckId, $currentDeckCards = [], $availableCards = [])
    {
        $this->userId = $userId;
        $this->deckId = $deckId;
        $this->loadAvailableCards($currentDeckCards, $availableCards);
        $this->filteredCards = $this->availableCards; // Initialize filtered cards
    }
    
    public function loadAvailableCards($currentDeckCards = [], $availableCards = [])
    {
        if (empty($availableCards)) {
            // Se non sono state fornite le carte disponibili, caricale dal database
            // MessageCreated::dispatch("get card from DB");
            $cards = Card::select("espansione", "numero", "nome", "titolo", "maxCopie")->get();
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
    
    public function open()
    {
        $this->isOpen = true;
        $this->filteredCards = $this->availableCards; // Inizializza con tutte le carte
    }

    public function close()
    {
        $this->isOpen = false;
        $this->reset(['selectedCardId', 'copiesAmount']);
    }

    public function updateCards($currentDeckCards)
    {
        $this->loadAvailableCards($currentDeckCards);
        $this->filteredCards = $this->availableCards;
    }

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