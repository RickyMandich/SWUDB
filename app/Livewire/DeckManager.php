<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * Livewire component for comprehensive deck management and statistics
 * Componente Livewire per gestione completa mazzi e statistiche
 *
 * This component provides full deck management functionality including:
 * - Real-time card addition/removal with validation
 * - Comprehensive deck statistics calculation
 * - Integration with popup card selection
 * - Automatic statistics updates and chart refresh
 * - Support for different card types (Leaders, Bases, Units, etc.)
 */
class DeckManager extends Component
{
    public $nome;
    public $user;
    public $deck;
    public $deckObject;
    public $size;
    public $proprietario;
    public $cards = [];
    public $deckCards = [];

    // Carta => numero di copie
    public $mazzo = [];
    public $aggiunte = [];
    public $rimosse = [];

    // Gestione rinominazione
    public $modalitaRinomina = false;
    public $nuovoNome = '';

    // Statistiche del mazzo
    public $trattiPrincipali = [];
    public $trattiCompleti = [];
    public $statistichePerCosto = [];
    public $distribuzionePerTipo = [];
    public $distribuzionePerCosto = [];
    public $distribuzionePerAspetto = [];
    public $totaleCarteStatistiche = 0;
    
    protected $listeners = [
        'cardAdded' => 'addCard',
        'refreshDeck' => '$refresh'
    ];
    
    /**
     * Initialize the deck manager component with deck data and available cards
     * Inizializza il componente gestore mazzo con dati del mazzo e carte disponibili
     *
     * @param string $nome Deck name
     * @param string $user Deck owner username
     * @param string $deck Deck URL identifier
     * @param \App\Models\Deck $deckObject Full deck object with version info
     * @param int $size Current deck size (card count)
     * @param bool $proprietario Whether current user owns this deck
     * @param array $carte Available cards from database
     * @param array $mazzo Current deck composition
     * @return void
     */
    public function mount($nome, $user, $deck, $deckObject, $size, $proprietario, $carte, $mazzo)
    {
        $this->nome = $nome;
        $this->user = $user;
        $this->deck = $deck;
        $this->deckObject = $deckObject;
        $this->size = $size;
        $this->proprietario = $proprietario;
        
        // Converte le carte disponibili in un formato più facilmente utilizzabile
        $this->cards = collect($carte)->mapWithKeys(function($card) {
            // Converto l'elemento in array se è un modello Eloquent
            if (is_object($card) && method_exists($card, 'toArray')) {
                $card = $card->toArray();
            } else {
                $card = (array)$card;
            }

            // Accesso sicuro agli array con valori di default
            $espansione = isset($card['espansione']) ? $card['espansione'] : '';
            $numero = isset($card['numero']) ? $card['numero'] : 0;
            $nome = isset($card['nome']) ? $card['nome'] : '';
            $titolo = isset($card['titolo']) ? $card['titolo'] : '';

            $key = $espansione . '-' . $numero;

            // Crea uno snippet per ogni carta
            $card['snippet'] = "$espansione-$numero - $nome".(strlen($titolo) > 0 ? ", ". strtoupper($titolo) : "");

            return [$key => $card];
        })->toArray();
        
        // Inizializza il mazzo con le carte già presenti
        $this->mazzo = collect($mazzo)->mapWithKeys(function($card) {
            // Salva il valore di copie se è un oggetto prima della conversione
            $copie = null;
            if (is_object($card) && isset($card->copie)) {
                $copie = $card->copie;
            }

            // Converto l'elemento in array se è un modello Eloquent
            if (is_object($card) && method_exists($card, 'toArray')) {
                $card = $card->toArray();
            } else {
                $card = (array)$card;
            }

            // Ripristina il valore di copie se era presente nell'oggetto originale
            if ($copie !== null) {
                $card['copie'] = $copie;
            } else if (!isset($card['copie'])) {
                // Se non c'è il campo copie, impostiamo un valore di default
                $card['copie'] = 1;
            }

            // Accesso sicuro agli array con valori di default
            $espansione = isset($card['espansione']) ? $card['espansione'] : '';
            $numero = isset($card['numero']) ? $card['numero'] : 0;
            $key = $espansione . '-' . $numero;
            return [$key => $card];
        })->toArray();

        // Calcola le statistiche iniziali
        $this->calcolaStatistiche();

        // Inizializza la sezione aggiunta carte
        $this->updateAddCardSection();
    }
    
    /**
     * Update the add card section with current deck state
     * Aggiorna la sezione aggiunta carte con lo stato attuale del mazzo
     *
     * @return void
     */
    public function updateAddCardSection()
    {
        // Prepariamo un array con le carte attualmente nel mazzo e il loro conteggio
        $currentDeckCards = collect($this->mazzo)->mapWithKeys(function($card, $key) {
            $copie = isset($card['copie']) ? $card['copie'] : 1;
            return [$key => $copie];
        })->toArray();

        // Aggiorniamo il componente sezione aggiunta carte con le carte disponibili
        $this->dispatch('updateAvailableCards', $currentDeckCards);
    }
    
    /**
     * Add multiple copies of a card to the deck from popup selection
     * Aggiunge più copie di una carta al mazzo dalla selezione popup
     *
     * @param array $data Array containing 'cardId' and 'copies' keys
     * @return void
     */
    public function addCard($data)
    {
        $cardId = $data['cardId'];
        $copies = $data['copies'];
        
        $continua = true;
        for ($i = 0; $i < $copies && $continua; $i++) {
            $continua = $this->aumentaCopia($cardId);
        }
        
        // Aggiorniamo il conteggio totale delle carte e la sezione aggiunta carte
        $this->refreshCardCount();
        $this->updateAddCardSection();
    }
    
    /**
     * Increase the copy count of a specific card in the deck
     * Aumenta il numero di copie di una carta specifica nel mazzo
     *
     * @param string $id Card identifier in format "expansion-number"
     * @return bool True if successful, false if max copies reached
     */
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

        // Aggiorna le statistiche immediatamente quando chiamato dai pulsanti
        $this->refreshCardCount();
        $this->updateAddCardSection();

        return true;
    }
    
    /**
     * Decrease the copy count of a specific card in the deck
     * Diminuisce il numero di copie di una carta specifica nel mazzo
     *
     * @param string $id Card identifier in format "expansion-number"
     * @return bool True if successful, false if card not in deck
     */
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
            $this->updateAddCardSection();
            return true;
        } else {
            $this->dispatch('showMessage', [
                'type' => 'warning',
                'message' => 'Non puoi rimuovere una carta che non è presente nel mazzo'
            ]);
            return false;
        }
    }
    
    /**
     * Refresh the total card count and recalculate all deck statistics
     * Aggiorna il conteggio totale delle carte e ricalcola tutte le statistiche del mazzo
     *
     * This method excludes Leaders and Bases from the count and triggers
     * chart refresh events for the frontend.
     *
     * @return void
     */
    public function refreshCardCount()
    {
        // Aggiorniamo il conteggio totale delle carte nel mazzo (escludendo leader e basi)
        $this->size = collect($this->mazzo)->filter(function($carta) {
            if (isset($carta['tipo'])) {
                $tipo = strtolower($carta['tipo']);
                return $tipo !== 'leader' && $tipo !== 'base';
            }
            return true;
        })->sum('copie');

        // Ricalcoliamo le statistiche
        $this->calcolaStatistiche();

        // Invia evento per aggiornare i grafici JavaScript
        $this->dispatch('refreshCharts');
    }

    /**
     * Calculate comprehensive deck statistics including traits, costs, types and aspects
     * Calcola statistiche complete del mazzo inclusi tratti, costi, tipi e aspetti
     *
     * This complex method generates detailed statistics for the deck:
     * - Trait analysis (both split and complete traits)
     * - Cost-based statistics with averages for units
     * - Type and cost distribution charts
     * - Aspect correlation analysis
     * - Excludes Leaders and Bases from calculations
     *
     * @return void
     */
    public function calcolaStatistiche()
    {
        if (empty($this->mazzo)) {
            $this->trattiPrincipali = [];
            $this->trattiCompleti = [];
            $this->statistichePerCosto = [];
            $this->distribuzionePerTipo = [];
            $this->distribuzionePerCosto = [];
            $this->distribuzionePerAspetto = [];
            $this->totaleCarteStatistiche = 0;
            return;
        }

        $carteDettagliate = [];
        $tuttiTratti = [];
        $tuttiTrattiCompleti = [];
        $statisticheCosto = [];

        // Raccogliamo i dettagli delle carte dal mazzo
        foreach ($this->mazzo as $id => $cartaMazzo) {
            $copie = isset($cartaMazzo['copie']) ? $cartaMazzo['copie'] : 1;

            // Ignoriamo i leader e le basi nelle statistiche
            if (isset($cartaMazzo['tipo'])) {
                $tipo = strtolower($cartaMazzo['tipo']);
                if ($tipo === 'leader' || $tipo === 'base') {
                    continue;
                }
            }

            // Aggiungiamo le carte ripetute per il numero di copie
            for ($i = 0; $i < $copie; $i++) {
                $carteDettagliate[] = $cartaMazzo;
            }

            // Calcola tratti (divisi per " * ")
            if (!empty($cartaMazzo['tratti'])) {
                // Tratti suddivisi
                $tratti = explode(' * ', $cartaMazzo['tratti']);
                foreach ($tratti as $tratto) {
                    $tratto = trim($tratto);
                    if (!empty($tratto)) {
                        for ($i = 0; $i < $copie; $i++) {
                            $tuttiTratti[] = $tratto;
                        }
                    }
                }

                // Tratti completi (non suddivisi)
                $trattoCompleto = trim($cartaMazzo['tratti']);
                if (!empty($trattoCompleto)) {
                    for ($i = 0; $i < $copie; $i++) {
                        $tuttiTrattiCompleti[] = $trattoCompleto;
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

        // Calcola tratti principali (suddivisi)
        $this->trattiPrincipali = collect($tuttiTratti)
            ->countBy()
            ->sortDesc()
            ->toArray();

        // Calcola tratti completi (non suddivisi)
        $this->trattiCompleti = collect($tuttiTrattiCompleti)
            ->countBy()
            ->sortDesc()
            ->toArray();

        // Calcola statistiche per costo (con tutti i punti tra min e max)
        if (!empty($statisticheCosto)) {
            $minCosto = min(array_keys($statisticheCosto));
            $maxCosto = max(array_keys($statisticheCosto));

            $this->statistichePerCosto = [];
            for ($i = $minCosto; $i <= $maxCosto; $i++) {
                if (isset($statisticheCosto[$i])) {
                    $stats = $statisticheCosto[$i];
                    $this->statistichePerCosto[$i] = [
                        'vita_media' => round($stats['vita_totale'] / $stats['unita'], 1),
                        'potenza_media' => round($stats['potenza_totale'] / $stats['unita'], 1),
                        'unita' => $stats['unita']
                    ];
                } else {
                    $this->statistichePerCosto[$i] = [
                        'vita_media' => 0,
                        'potenza_media' => 0,
                        'unita' => 0
                    ];
                }
            }
        } else {
            $this->statistichePerCosto = [];
        }

        // Calcola distribuzione per tipo
        $this->distribuzionePerTipo = collect($carteDettagliate)
            ->groupBy('tipo')
            ->map(function($gruppo) {
                return $gruppo->count();
            })
            ->sortDesc()
            ->toArray();

        // Calcola distribuzione per costo (con tutti i punti tra min e max)
        $costiPresenti = collect($carteDettagliate)
            ->groupBy('costo')
            ->map(function($gruppo) {
                return $gruppo->count();
            })
            ->toArray();

        if (!empty($costiPresenti)) {
            $minCosto = min(array_keys($costiPresenti));
            $maxCosto = max(array_keys($costiPresenti));

            $this->distribuzionePerCosto = [];
            for ($i = $minCosto; $i <= $maxCosto; $i++) {
                $this->distribuzionePerCosto[$i] = $costiPresenti[$i] ?? 0;
            }
        } else {
            $this->distribuzionePerCosto = [];
        }

        // Calcola distribuzione per aspetto (correlazione primario-secondario)
        $this->distribuzionePerAspetto = collect($carteDettagliate)
            ->map(function($carta) {
                $primario = $carta['aspettoPrimario'] ?? null;
                $secondario = $carta['aspettoSecondario'] ?? null;

                // Gestisce aspetti primari vuoti o null
                if (empty($primario)) {
                    $primario = 'nessun aspetto';
                }

                if ($secondario && !empty($secondario)) {
                    return $primario . ' / ' . $secondario;
                } else {
                    return $primario;
                }
            })
            ->countBy()
            ->sortDesc()
            ->toArray();

        // Calcola il totale delle carte considerate nelle statistiche
        $this->totaleCarteStatistiche = count($carteDettagliate);
    }

    
    /**
     * Save the current deck changes by preparing form data and dispatching save event
     * Salva le modifiche attuali del mazzo preparando i dati del form e inviando l'evento di salvataggio
     *
     * This method formats the additions and removals into the expected format
     * for the backend controller and triggers the save form submission.
     *
     * @return void
     */
    public function saveDeck()
    {
        // Prepariamo i dati per il form
        $formData = [];

        foreach ($this->aggiunte as $id => $carta) {
            $copie = isset($carta['copie']) ? $carta['copie'] : 1;
            $formData[$id] = "A-" . $copie;
        }

        foreach ($this->rimosse as $id => $carta) {
            $copie = isset($carta['copie']) ? $carta['copie'] : 1;
            $formData[$id] = "R-" . $copie;
        }

        // Inviamo i dati al controller salvando il form
        $this->dispatch('submitSaveForm', ['carte' => $formData]);
    }

    /**
     * Activate rename mode and initialize the new name field
     * Attiva la modalità rinomina e inizializza il campo nuovo nome
     *
     * @return void
     */
    public function attivaRinomina()
    {
        $this->modalitaRinomina = true;
        $this->nuovoNome = $this->nome;
    }

    /**
     * Cancel rename mode and reset the new name field
     * Annulla la modalità rinomina e resetta il campo nuovo nome
     *
     * @return void
     */
    public function annullaRinomina()
    {
        $this->modalitaRinomina = false;
        $this->nuovoNome = '';
    }

    /**
     * Save the new deck name by dispatching rename form submission
     * Salva il nuovo nome del mazzo inviando il form di rinominazione
     *
     * This method validates the new name and triggers the rename form submission.
     *
     * @return void
     */
    public function salvaNuovoNome()
    {
        // Validazione base del nuovo nome
        $nuovoNome = trim($this->nuovoNome);

        if (empty($nuovoNome)) {
            $this->dispatch('showMessage', [
                'type' => 'error',
                'message' => 'Il nome del mazzo non può essere vuoto'
            ]);
            return;
        }

        if (strlen($nuovoNome) > 500) {
            $this->dispatch('showMessage', [
                'type' => 'error',
                'message' => 'Il nome del mazzo non può superare i 500 caratteri'
            ]);
            return;
        }

        if ($nuovoNome === "Collezione") {
            $this->dispatch('showMessage', [
                'type' => 'warning',
                'message' => 'Il nome "Collezione" è riservato'
            ]);
            return;
        }

        // Se il nome non è cambiato, annulla semplicemente la modalità rinomina
        if ($nuovoNome === $this->nome) {
            $this->annullaRinomina();
            return;
        }

        // Invia il form di rinominazione
        $this->dispatch('submitRenameForm', ['nuovo_nome' => $nuovoNome]);
    }
    
    public function render()
    {
        return view('livewire.deck-manager');
    }
}