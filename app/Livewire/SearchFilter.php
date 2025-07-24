<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Card;
use Illuminate\Support\Facades\Cache;


/**
 * Livewire component for advanced card search and filtering functionality
 * Componente Livewire per funzionalità avanzate di ricerca e filtro carte
 *
 * This component provides comprehensive card filtering capabilities with:
 * - Real-time search across multiple card attributes
 * - Dynamic filter options loaded from database with caching
 * - Support for multiple modes (page, popup, collection)
 * - Automatic sorting using CardsController merge sort algorithm
 * - Event-driven communication with parent components
 */
class SearchFilter extends Component
{
    // Proprietà per i filtri
    public $nome = '';
    public $titolo = '';
    public $espansione = '';
    public $tipo = '';
    public $aspettoPrimario = '';
    public $aspettoSecondario = '';
    public $rarita = '';
    public $costoMin = null;
    public $costoMax = null;
    public $potenzaMin = null;
    public $potenzaMax = null;
    public $vitaMin = null;
    public $vitaMax = null;

    // Valori massimi dinamici dal database
    public $maxCostoDb = 999;
    public $maxPotenzaDb = 999;
    public $maxVitaDb = 999;
    public $tratti = '';
    public $arena = '';
    public $unica = null;
    public $artista = '';

    // Proprietà per le opzioni dei select
    public $espansioni = [];
    public $tipi = [];
    public $aspettiPrimari = [];
    public $aspettiSecondari = [];
    public $rarita_options = [];
    public $arene = [];
    public $artisti = [];

    // Risultati filtrati
    public $filteredCards = [];
    public $totalResults = 0;

    // Modalità di utilizzo (per la pagina principale, popup o collezione)
    public $mode = 'page'; // 'page', 'popup' o 'collezione'

    // Stato dei filtri avanzati (aperto/chiuso)
    public $advancedFiltersOpen = false;

    // Stato del filtro principale (aperto/chiuso)
    public $mainFiltersOpen = false;

    protected $listeners = [
        'resetFilters' => 'resetAllFilters',
        'applyFiltersForPopup' => 'getFilteredCardsForPopup',
        'loadAllCards' => 'loadAllCards'
    ];

    /**
     * Initialize the component with mode and optional initial filters
     * Inizializza il componente con modalità e filtri iniziali opzionali
     *
     * @param string $mode Component mode: 'page', 'popup' or 'collezione'
     * @param string|null $initialNome Initial name filter value from URL parameter
     * @return void
     */
    public function mount($mode = 'page', $initialNome = null)
    {
        $this->mode = $mode;
        $this->loadFilterOptions();

        // Set initial filter values if provided
        // Imposta i valori iniziali dei filtri se forniti
        if (!empty($initialNome)) {
            $this->nome = $initialNome;
        }

        // Apply filters if any initial values are set, otherwise load all cards
        // Applica i filtri se sono impostati valori iniziali, altrimenti carica tutte le carte
        if (!empty($this->nome)) {
            $this->applyFilters();
        } else {
            $this->loadAllCards();
        }
    }

    /**
     * Load all filter options from database with caching for performance
     * Carica tutte le opzioni di filtro dal database con cache per le prestazioni
     *
     * This method populates all filter dropdown options and maximum values
     * using cached queries to improve performance. Cache expires after 1 hour.
     *
     * @return void
     */
    public function loadFilterOptions()
    {
        // Carica tutte le opzioni uniche per i filtri dalla cache
        $this->espansioni = Cache::remember('cards_filter_espansioni', 3600, function () {
            return Card::select('espansione')
                ->selectRaw('MIN(uscita) as prima_uscita')
                ->groupBy('espansione')
                ->orderBy('prima_uscita')
                ->pluck('espansione');
        });

        $this->tipi = Cache::remember('cards_filter_tipi', 3600, function () {
            return Card::select('tipo')
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo');
        });

        $this->aspettiPrimari = Cache::remember('cards_filter_aspetti_primari', 3600, function () {
            return Card::select('aspettoPrimario')
                ->distinct()
                ->whereNotNull('aspettoPrimario')
                ->orderBy('aspettoPrimario')
                ->pluck('aspettoPrimario');
        });

        $this->aspettiSecondari = Cache::remember('cards_filter_aspetti_secondari', 3600, function () {
            return Card::select('aspettoSecondario')
                ->distinct()
                ->whereNotNull('aspettoSecondario')
                ->orderBy('aspettoSecondario')
                ->pluck('aspettoSecondario');
        });

        $this->rarita_options = Cache::remember('cards_filter_rarita', 3600, function () {
            return Card::select('rarita')
                ->distinct()
                ->orderBy('rarita')
                ->pluck('rarita');
        });

        $this->arene = Cache::remember('cards_filter_arene', 3600, function () {
            return Card::select('arena')
                ->distinct()
                ->whereNotNull('arena')
                ->orderBy('arena')
                ->pluck('arena');
        });

        $this->artisti = Cache::remember('cards_filter_artisti', 3600, function () {
            return Card::select('artista')
                ->distinct()
                ->orderBy('artista')
                ->pluck('artista');
        });

        // Carica i valori massimi dal database dalla cache
        $maxValues = Cache::remember('cards_filter_max_values', 3600, function () {
            return [
                'costo' => Card::max('costo') ?? 999,
                'potenza' => Card::max('potenza') ?? 999,
                'vita' => Card::max('vita') ?? 999,
            ];
        });

        $this->maxCostoDb = $maxValues['costo'];
        $this->maxPotenzaDb = $maxValues['potenza'];
        $this->maxVitaDb = $maxValues['vita'];
    }

    /**
     * Apply all active filters to build the filtered card query and results
     * Applica tutti i filtri attivi per costruire la query filtrata e i risultati
     *
     * This method builds a comprehensive database query based on all active filters,
     * applies the custom sorting algorithm, and dispatches events to update the UI.
     * Handles null values and empty filters appropriately.
     *
     * @return void
     */
    public function applyFilters()
    {
        // Normalizza i valori vuoti dei filtri numerici in null
        foreach (['costoMin', 'costoMax', 'potenzaMin', 'potenzaMax', 'vitaMin', 'vitaMax', 'unica'] as $field) {
            if ($this->$field === '' || $this->$field === false) {
                $this->$field = null;
            }
        }
        
        $query = Card::query();

        // Filtro per nome
        if (!empty($this->nome)) {
            $query->where('nome', 'like', '%' . $this->nome . '%');
        }

        // Filtro per titolo
        if (!empty($this->titolo)) {
            $query->where('titolo', 'like', '%' . $this->titolo . '%');
        }

        // Filtro per espansione
        if (!empty($this->espansione)) {
            $query->where('espansione', $this->espansione);
        }

        // Filtro per tipo
        if (!empty($this->tipo)) {
            $query->where('tipo', $this->tipo);
        }

        // Filtro per aspetto primario
        if (!empty($this->aspettoPrimario)) {
            $query->where('aspettoPrimario', $this->aspettoPrimario);
        }

        // Filtro per aspetto secondario
        if (!empty($this->aspettoSecondario)) {
            $query->where('aspettoSecondario', $this->aspettoSecondario);
        }

        // Filtro per rarità
        if (!empty($this->rarita)) {
            $query->where('rarita', $this->rarita);
        }

        // Filtro per costo (solo se specificato)
        if ($this->costoMin !== null || ($this->costoMax !== null && $this->costoMax < $this->maxCostoDb)) {
            $minCosto = $this->costoMin ?? 0;
            $maxCosto = $this->costoMax ?? $this->maxCostoDb;
            $query->whereBetween('costo', [$minCosto, $maxCosto]);
        }

        // Filtro per potenza (solo se specificato)
        if ($this->potenzaMin !== null || ($this->potenzaMax !== null && $this->potenzaMax < $this->maxPotenzaDb)) {
            $minPotenza = $this->potenzaMin ?? 0;
            $maxPotenza = $this->potenzaMax ?? $this->maxPotenzaDb;
            $query->where(function($q) use ($minPotenza, $maxPotenza) {
                $q->whereNull('potenza')
                  ->orWhereBetween('potenza', [$minPotenza, $maxPotenza]);
            });
        }

        // Filtro per vita (solo se specificato)
        if ($this->vitaMin !== null || ($this->vitaMax !== null && $this->vitaMax < $this->maxVitaDb)) {
            $minVita = $this->vitaMin ?? 0;
            $maxVita = $this->vitaMax ?? $this->maxVitaDb;
            $query->where(function($q) use ($minVita, $maxVita) {
                $q->whereNull('vita')
                  ->orWhereBetween('vita', [$minVita, $maxVita]);
            });
        }

        // Filtro per tratti
        if (!empty($this->tratti)) {
            $query->where('tratti', 'like', '%' . $this->tratti . '%');
        }

        // Filtro per arena
        if (!empty($this->arena)) {
            $query->where('arena', $this->arena);
        }

        // Filtro per unica
        if ($this->unica !== null) {
            $query->where('unica', $this->unica);
        }

        // Filtro per artista
        if (!empty($this->artista)) {
            $query->where('artista', $this->artista);
        }

        $results = $query->get();

        // Applica l'ordinamento usando il metodo del controller
        if (!$results->isEmpty()) {
            $results = \App\Http\Controllers\CardsController::mergeSort($results);
        }

        $this->filteredCards = $results;
        $this->totalResults = $this->filteredCards->count();

        // Emetti evento per aggiornare la vista principale
        $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
    }

    /**
     * Reset all filter values to their default state and reapply filters
     * Reimposta tutti i valori dei filtri al loro stato predefinito e riapplica i filtri
     *
     * Note: This method preserves the advanced filters open/closed state
     * Nota: Questo metodo preserva lo stato aperto/chiuso dei filtri avanzati
     *
     * @return void
     */
    public function resetAllFilters()
    {
        $this->nome = '';
        $this->titolo = '';
        $this->espansione = '';
        $this->tipo = '';
        $this->aspettoPrimario = '';
        $this->aspettoSecondario = '';
        $this->rarita = '';
        $this->costoMin = null;
        $this->costoMax = null;
        $this->potenzaMin = null;
        $this->potenzaMax = null;
        $this->vitaMin = null;
        $this->vitaMax = null;
        $this->tratti = '';
        $this->arena = '';
        $this->unica = null;
        $this->artista = '';

        // Note: $advancedFiltersOpen is intentionally NOT reset to preserve UI state
        // Nota: $advancedFiltersOpen non viene intenzionalmente resettato per preservare lo stato dell'UI

        $this->applyFilters();
    }

    /**
     * Get filtered cards specifically for popup mode
     * Ottiene le carte filtrate specificamente per la modalità popup
     *
     * @return \Illuminate\Support\Collection The filtered cards collection
     */
    public function getFilteredCardsForPopup()
    {
        $this->applyFilters();
        return $this->filteredCards;
    }

    /**
     * Load all cards without filters for initial display in page mode
     * Carica tutte le carte senza filtri per la visualizzazione iniziale in modalità page
     *
     * @return void
     */
    public function loadAllCards()
    {
        $results = Card::all();

        // Applica l'ordinamento usando il metodo del controller
        if (!$results->isEmpty()) {
            $results = \App\Http\Controllers\CardsController::mergeSort($results);
        }

        $this->filteredCards = $results;
        $this->totalResults = $this->filteredCards->count();

        // Emetti evento per aggiornare la vista principale
        $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
    }

    // Real-time filter update methods - automatically trigger when properties change
    // Metodi di aggiornamento filtri in tempo reale - si attivano automaticamente quando cambiano le proprietà
    public function updatedNome() { $this->applyFilters(); }
    public function updatedTitolo() { $this->applyFilters(); }
    public function updatedEspansione() { $this->applyFilters(); }
    public function updatedTipo() { $this->applyFilters(); }
    public function updatedAspettoPrimario() { $this->applyFilters(); }
    public function updatedAspettoSecondario() { $this->applyFilters(); }
    public function updatedRarita() { $this->applyFilters(); }
    public function updatedCostoMin() { $this->applyFilters(); }
    public function updatedCostoMax() { $this->applyFilters(); }
    public function updatedPotenzaMin() { $this->applyFilters(); }
    public function updatedPotenzaMax() { $this->applyFilters(); }
    public function updatedVitaMin() { $this->applyFilters(); }
    public function updatedVitaMax() { $this->applyFilters(); }
    public function updatedTratti() { $this->applyFilters(); }
    public function updatedArena() { $this->applyFilters(); }
    public function updatedUnica() { $this->applyFilters(); }
    public function updatedArtista() { $this->applyFilters(); }

    /**
     * Toggle the advanced filters section open/closed state
     * Attiva/disattiva lo stato aperto/chiuso della sezione filtri avanzati
     *
     * @return void
     */
    public function toggleAdvancedFilters()
    {
        $this->advancedFiltersOpen = !$this->advancedFiltersOpen;
    }

    /**
     * Toggle the main filters section open/closed state
     * Attiva/disattiva lo stato aperto/chiuso della sezione filtri principale
     *
     * @return void
     */
    public function toggleMainFilters()
    {
        $this->mainFiltersOpen = !$this->mainFiltersOpen;
    }

    public function render()
    {
        return view('livewire.search-filter');
    }
}
