<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Card;
use Illuminate\Support\Collection;

class DeckManager extends Component
{
    public $nome;
    public $user;
    public $deck;
    public $size;
    public $proprietario;
    public $cards = [];
    public $deckCards = [];
    
    // Carta => numero di copie
    public $mazzo = [];
    public $aggiunte = [];
    public $rimosse = [];

    // Statistiche del mazzo
    public $trattiPrincipali = [];
    public $statistichePerCosto = [];
    public $distribuzionePerTipo = [];
    public $distribuzionePerCosto = [];
    
    protected $listeners = [
        'cardAdded' => 'addCard',
        'refreshDeck' => '$refresh'
    ];
    
    public function mount($nome, $user, $deck, $size, $proprietario, $carte, $mazzo)
    {
        $this->nome = $nome;
        $this->user = $user;
        $this->deck = $deck;
        $this->size = $size;
        $this->proprietario = $proprietario;
        
        // Converte le carte disponibili in un formato più facilmente utilizzabile
        $this->cards = collect($carte)->mapWithKeys(function($card) {
            $key = $card['espansione'] . '-' . $card['numero'];
            
            // Crea uno snippet per ogni carta
            $card['snippet'] = "$card[espansione]-$card[numero] - ".$card['nome'].(strlen($card['titolo']) > 0 ? ", ". strtoupper($card['titolo']) : "");
            
            return [$key => $card];
        })->toArray();
        
        // Inizializza il mazzo con le carte già presenti
        $this->mazzo = collect($mazzo)->mapWithKeys(function($card) {
            $card = (array)$card;
            $key = $card['espansione'] . '-' . $card['numero'];
            return [$key => $card];
        })->toArray();

        // Calcola le statistiche iniziali
        $this->calcolaStatistiche();
    }
    
    public function openAddCardPopup()
    {
        // Prepariamo un array con le carte attualmente nel mazzo e il loro conteggio
        $currentDeckCards = collect($this->mazzo)->mapWithKeys(function($card, $key) {
            return [$key => $card['copie']];
        })->toArray();
        
        // Aggiorniamo il componente popup con le carte disponibili
        $this->dispatch('updateAvailableCards', $currentDeckCards);
        $this->dispatch('openAddCardPopup');
    }
    
    public function addCard($data)
    {
        $cardId = $data['cardId'];
        $copies = $data['copies'];
        
        $continua = true;
        for ($i = 0; $i < $copies && $continua; $i++) {
            $continua = $this->aumentaCopia($cardId);
        }
        
        // Aggiorniamo il conteggio totale delle carte
        $this->refreshCardCount();
    }
    
    public function aumentaCopia($id)
    {
        $aggiungi = true;
        
        // Verifichiamo se la carta è già nel mazzo
        if (isset($this->mazzo[$id])) {
            // Controlliamo se abbiamo raggiunto il numero massimo di copie
            if ($this->mazzo[$id]['copie'] < $this->mazzo[$id]['maxCopie']) {
                $this->mazzo[$id]['copie']++;
            } else {
                // Mostriamo un messaggio tramite un evento o tramite una notifica di sistema
                $this->dispatch('showMessage', [
                    'type' => 'warning',
                    'message' => 'Hai raggiunto il numero massimo di copie di questa carta'
                ]);
                return false;
            }
        } else {
            // La carta non è nel mazzo, la aggiungiamo
            $this->mazzo[$id] = $this->cards[$id];
            $this->mazzo[$id]['copie'] = 1;
        }
        
        if ($aggiungi) {
            if (isset($this->rimosse[$id])) {
                if ($this->rimosse[$id]['copie'] > 1) {
                    $this->rimosse[$id]['copie']--;
                } else {
                    unset($this->rimosse[$id]);
                }
            } else if (isset($this->aggiunte[$id])) {
                $this->aggiunte[$id]['copie']++;
            } else {
                $this->aggiunte[$id] = $this->cards[$id];
                $this->aggiunte[$id]['copie'] = 1;
            }
        }
        
        return true;
    }
    
    public function diminuisciCopia($id)
    {
        if (isset($this->mazzo[$id])) {
            if ($this->mazzo[$id]['copie'] > 1) {
                $this->mazzo[$id]['copie']--;
            } else {
                unset($this->mazzo[$id]);
            }
            
            if (isset($this->aggiunte[$id])) {
                if ($this->aggiunte[$id]['copie'] > 1) {
                    $this->aggiunte[$id]['copie']--;
                } else {
                    unset($this->aggiunte[$id]);
                }
            } else if (isset($this->rimosse[$id])) {
                $this->rimosse[$id]['copie']++;
            } else {
                $this->rimosse[$id] = $this->cards[$id];
                $this->rimosse[$id]['copie'] = 1;
            }
            
            $this->refreshCardCount();
            return true;
        } else {
            $this->dispatch('showMessage', [
                'type' => 'warning',
                'message' => 'Non puoi rimuovere una carta che non è presente nel mazzo'
            ]);
            return false;
        }
    }
    
    public function refreshCardCount()
    {
        // Aggiorniamo il conteggio totale delle carte nel mazzo
        $this->size = collect($this->mazzo)->sum('copie');

        // Ricalcoliamo le statistiche
        $this->calcolaStatistiche();
    }

    public function calcolaStatistiche()
    {
        if (empty($this->mazzo)) {
            $this->trattiPrincipali = [];
            $this->statistichePerCosto = [];
            $this->distribuzionePerTipo = [];
            $this->distribuzionePerCosto = [];
            return;
        }

        $carteDettagliate = [];
        $tuttiTratti = [];
        $statisticheCosto = [];

        // Raccogliamo i dettagli delle carte dal mazzo
        foreach ($this->mazzo as $id => $cartaMazzo) {
            $copie = $cartaMazzo['copie'];

            // Aggiungiamo le carte ripetute per il numero di copie
            for ($i = 0; $i < $copie; $i++) {
                $carteDettagliate[] = $cartaMazzo;
            }

            // Calcola tratti
            if (!empty($cartaMazzo['tratti'])) {
                $tratti = explode(',', $cartaMazzo['tratti']);
                foreach ($tratti as $tratto) {
                    $tratto = trim($tratto);
                    if (!empty($tratto)) {
                        for ($i = 0; $i < $copie; $i++) {
                            $tuttiTratti[] = $tratto;
                        }
                    }
                }
            }

            // Calcola statistiche per costo (solo per unità con vita e potenza)
            if (isset($cartaMazzo['vita']) && isset($cartaMazzo['potenza']) &&
                $cartaMazzo['vita'] > 0 && $cartaMazzo['potenza'] > 0) {

                $costo = $cartaMazzo['costo'] ?? 0;

                if (!isset($statisticheCosto[$costo])) {
                    $statisticheCosto[$costo] = [
                        'vita_totale' => 0,
                        'potenza_totale' => 0,
                        'unita' => 0
                    ];
                }

                $statisticheCosto[$costo]['vita_totale'] += $cartaMazzo['vita'] * $copie;
                $statisticheCosto[$costo]['potenza_totale'] += $cartaMazzo['potenza'] * $copie;
                $statisticheCosto[$costo]['unita'] += $copie;
            }
        }

        // Calcola tratti principali
        $this->trattiPrincipali = collect($tuttiTratti)
            ->countBy()
            ->sortDesc()
            ->toArray();

        // Calcola statistiche per costo
        $this->statistichePerCosto = [];
        foreach ($statisticheCosto as $costo => $stats) {
            $this->statistichePerCosto[$costo] = [
                'vita_media' => round($stats['vita_totale'] / $stats['unita'], 1),
                'potenza_media' => round($stats['potenza_totale'] / $stats['unita'], 1),
                'unita' => $stats['unita']
            ];
        }
        ksort($this->statistichePerCosto);

        // Calcola distribuzione per tipo
        $this->distribuzionePerTipo = collect($carteDettagliate)
            ->groupBy('tipo')
            ->map(function($gruppo) {
                return $gruppo->count();
            })
            ->sortDesc()
            ->toArray();

        // Calcola distribuzione per costo
        $this->distribuzionePerCosto = collect($carteDettagliate)
            ->groupBy('costo')
            ->map(function($gruppo) {
                return $gruppo->count();
            })
            ->sortKeys()
            ->toArray();
    }

    
    public function saveDeck()
    {
        // Prepariamo i dati per il form
        $formData = [];
        
        foreach ($this->aggiunte as $id => $carta) {
            $formData[$id] = "A-" . $carta['copie'];
        }
        
        foreach ($this->rimosse as $id => $carta) {
            $formData[$id] = "R-" . $carta['copie'];
        }
        
        // Inviamo i dati al controller salvando il form
        $this->dispatch('submitSaveForm', ['carte' => $formData]);
    }
    
    public function render()
    {
        return view('livewire.deck-manager');
    }
}