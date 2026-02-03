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

    // Parametri iniziali da GET
    public $initialParams = [];

    // Computed property per verificare se ci sono filtri attivi
    public $hasActiveFilters = false;

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
     * @param array $initialParams Initial parameters from GET request
     * @return void
     */
    public function mount($mode = 'page', $initialNome = null, $initialParams = [])
    {
        $this->mode = $mode;
        $this->initialParams = $initialParams;

        $this->loadFilterOptions();

        // Initialize filters from GET parameters if available
        // Inizializza i filtri dai parametri GET se disponibili
        $this->initializeFromParams();

        // Override with specific parameters if provided
        // Sovrascrivi con parametri specifici se forniti
        if (!empty($initialNome)) {
            $this->nome = $initialNome;
        }

        // Initialize hasActiveFilters property
        // Inizializza la proprietà hasActiveFilters
        $this->hasActiveFilters = $this->hasActiveFilters();

        // Apply filters if any initial values are set, otherwise load all cards (except in popup mode)
        // Applica i filtri se sono impostati valori iniziali, altrimenti carica tutte le carte (eccetto in modalità popup)
        if ($this->hasActiveFilters) {
            $this->applyFilters();
        } else if ($this->mode !== 'popup') {
            $this->loadAllCards();
        }
        // In popup mode without filters, start with empty results for better performance
        // In modalità popup senza filtri, inizia con risultati vuoti per migliori prestazioni
    }

    /**
     * Initialize filter values from GET parameters
     * Inizializza i valori dei filtri dai parametri GET
     *
     * @return void
     */
    private function initializeFromParams()
    {
        if (empty($this->initialParams)) {
            return;
        }

        // Map GET parameters to component properties
        // Mappa i parametri GET alle proprietà del componente
        $paramMap = [
            'nome' => 'nome',
            'titolo' => 'titolo',
            'espansione' => 'espansione',
            'tipo' => 'tipo',
            'aspettoPrimario' => 'aspettoPrimario',
            'aspettoSecondario' => 'aspettoSecondario',
            'rarita' => 'rarita',
            'costoMin' => 'costoMin',
            'costoMax' => 'costoMax',
            'potenzaMin' => 'potenzaMin',
            'potenzaMax' => 'potenzaMax',
            'vitaMin' => 'vitaMin',
            'vitaMax' => 'vitaMax',
            'tratti' => 'tratti',
            'arena' => 'arena',
            'unica' => 'unica',
            'artista' => 'artista'
        ];

        foreach ($paramMap as $param => $property) {
            if (isset($this->initialParams[$param]) && $this->initialParams[$param] !== '') {
                $value = $this->initialParams[$param];

                // Convert numeric parameters
                // Converti parametri numerici
                if (in_array($param, ['costoMin', 'costoMax', 'potenzaMin', 'potenzaMax', 'vitaMin', 'vitaMax'])) {
                    $value = is_numeric($value) ? (int) $value : null;
                }

                // Convert boolean parameters
                // Converti parametri booleani
                if ($param === 'unica') {
                    $value = $value === '1' ? true : ($value === '0' ? false : null);
                }

                $this->$property = $value;
            }
        }
    }

    /**
     * Check if any filters are currently active
     * Verifica se ci sono filtri attualmente attivi
     *
     * @return bool
     */
    private function hasActiveFilters()
    {
        return !empty($this->nome) ||
            !empty($this->titolo) ||
            !empty($this->espansione) ||
            !empty($this->tipo) ||
            !empty($this->aspettoPrimario) ||
            !empty($this->aspettoSecondario) ||
            !empty($this->rarita) ||
            $this->costoMin !== null ||
            $this->costoMax !== null ||
            $this->potenzaMin !== null ||
            $this->potenzaMax !== null ||
            $this->vitaMin !== null ||
            $this->vitaMax !== null ||
            !empty($this->tratti) ||
            !empty($this->arena) ||
            $this->unica !== null ||
            !empty($this->artista);
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
            return Card::select('cards.espansione')
                ->join('expansions', 'cards.espansione', '=', 'expansions.espansione')
                ->groupBy('cards.espansione', 'expansions.uscita')
                ->orderBy('expansions.uscita', 'asc')
                ->pluck('cards.espansione');
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
        // Assicura che i filtri siano scalari (non array) e gestisce i tipi corretti
        $this->nome = is_array($this->nome) ? (string) ($this->nome[0] ?? '') : (string) $this->nome;
        $this->titolo = is_array($this->titolo) ? (string) ($this->titolo[0] ?? '') : (string) $this->titolo;
        $this->espansione = is_array($this->espansione) ? (string) ($this->espansione[0] ?? '') : (string) $this->espansione;
        $this->tipo = is_array($this->tipo) ? (string) ($this->tipo[0] ?? '') : (string) $this->tipo;
        $this->aspettoPrimario = is_array($this->aspettoPrimario) ? (string) ($this->aspettoPrimario[0] ?? '') : (string) $this->aspettoPrimario;
        $this->aspettoSecondario = is_array($this->aspettoSecondario) ? (string) ($this->aspettoSecondario[0] ?? '') : (string) $this->aspettoSecondario;
        $this->rarita = is_array($this->rarita) ? (string) ($this->rarita[0] ?? '') : (string) $this->rarita;
        $this->tratti = is_array($this->tratti) ? (string) ($this->tratti[0] ?? '') : (string) $this->tratti;
        $this->arena = is_array($this->arena) ? (string) ($this->arena[0] ?? '') : (string) $this->arena;
        $this->artista = is_array($this->artista) ? (string) ($this->artista[0] ?? '') : (string) $this->artista;

        // Normalizza i valori vuoti o array dei filtri numerici in null
        foreach (['costoMin', 'costoMax', 'potenzaMin', 'potenzaMax', 'vitaMin', 'vitaMax', 'unica'] as $field) {
            $val = $this->$field;

            // Se è un array, prendi il primo elemento
            if (is_array($val)) {
                $val = !empty($val) ? reset($val) : null;
            }

            // Normalizza valori vuoti o "falsy"
            if ($val === '' || $val === false || $val === 'null' || $val === 'undefined') {
                $this->$field = null;
            } else if ($val !== null) {
                // Per 'unica' gestiamo come booleano, per gli altri come intero
                if ($field === 'unica') {
                    $this->$field = in_array($val, [1, '1', true, 'true'], true);
                } else {
                    $this->$field = (int) $val;
                }
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
        if ($this->costoMin !== null || ($this->costoMax !== null && (int) $this->costoMax < (int) $this->maxCostoDb)) {
            $minCosto = $this->costoMin ?? 0;
            $maxCosto = $this->costoMax ?? $this->maxCostoDb;
            $query->whereBetween('costo', [(int) $minCosto, (int) $maxCosto]);
        }

        // Filtro per potenza (solo se specificato)
        if ($this->potenzaMin !== null || ($this->potenzaMax !== null && (int) $this->potenzaMax < (int) $this->maxPotenzaDb)) {
            $minPotenza = $this->potenzaMin ?? 0;
            $maxPotenza = $this->potenzaMax ?? $this->maxPotenzaDb;
            $query->where(function ($q) use ($minPotenza, $maxPotenza) {
                $q->whereNull('potenza')
                    ->orWhereBetween('potenza', [(int) $minPotenza, (int) $maxPotenza]);
            });
        }

        // Filtro per vita (solo se specificato)
        if ($this->vitaMin !== null || ($this->vitaMax !== null && (int) $this->vitaMax < (int) $this->maxVitaDb)) {
            $minVita = $this->vitaMin ?? 0;
            $maxVita = $this->vitaMax ?? $this->maxVitaDb;
            $query->where(function ($q) use ($minVita, $maxVita) {
                $q->whereNull('vita')
                    ->orWhereBetween('vita', [(int) $minVita, (int) $maxVita]);
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

        // Update hasActiveFilters property
        // Aggiorna la proprietà hasActiveFilters
        $this->hasActiveFilters = $this->hasActiveFilters();

        // Emetti evento per aggiornare la vista principale
        $this->dispatch('cardsFiltered', $this->filteredCards->toArray());

        // Update URL with current filter parameters for page and collezione modes
        // Aggiorna l'URL con i parametri di filtro correnti per le modalità page e collezione
        if (in_array($this->mode, ['page', 'collezione'])) {
            $this->updateUrlWithFilters();
        }
    }

    /**
     * Update the browser URL with current filter parameters
     * Aggiorna l'URL del browser con i parametri di filtro correnti
     *
     * @return void
     */
    private function updateUrlWithFilters()
    {
        $params = [];

        // Add non-empty filter values to URL parameters
        // Aggiungi valori di filtro non vuoti ai parametri URL
        if (!empty($this->nome))
            $params['nome'] = $this->nome;
        if (!empty($this->titolo))
            $params['titolo'] = $this->titolo;
        if (!empty($this->espansione))
            $params['espansione'] = $this->espansione;
        if (!empty($this->tipo))
            $params['tipo'] = $this->tipo;
        if (!empty($this->aspettoPrimario))
            $params['aspettoPrimario'] = $this->aspettoPrimario;
        if (!empty($this->aspettoSecondario))
            $params['aspettoSecondario'] = $this->aspettoSecondario;
        if (!empty($this->rarita))
            $params['rarita'] = $this->rarita;
        if ($this->costoMin !== null)
            $params['costoMin'] = $this->costoMin;
        if ($this->costoMax !== null)
            $params['costoMax'] = $this->costoMax;
        if ($this->potenzaMin !== null)
            $params['potenzaMin'] = $this->potenzaMin;
        if ($this->potenzaMax !== null)
            $params['potenzaMax'] = $this->potenzaMax;
        if ($this->vitaMin !== null)
            $params['vitaMin'] = $this->vitaMin;
        if ($this->vitaMax !== null)
            $params['vitaMax'] = $this->vitaMax;
        if (!empty($this->tratti))
            $params['tratti'] = $this->tratti;
        if (!empty($this->arena))
            $params['arena'] = $this->arena;
        if ($this->unica !== null)
            $params['unica'] = $this->unica ? '1' : '0';
        if (!empty($this->artista))
            $params['artista'] = $this->artista;

        // Build URL based on current mode
        // Costruisci URL basato sulla modalità corrente
        $routeName = $this->mode === 'collezione' ? 'collezione' : 'carte';
        $url = route($routeName, $params);

        // Use JavaScript to update URL without page reload
        // Usa JavaScript per aggiornare l'URL senza ricaricare la pagina
        $this->dispatch('updateUrl', $url);
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

        // Update URL to remove all filter parameters
        // Aggiorna l'URL per rimuovere tutti i parametri di filtro
        if (in_array($this->mode, ['page', 'collezione'])) {
            $routeName = $this->mode === 'collezione' ? 'collezione' : 'carte';
            $url = route($routeName);
            $this->dispatch('updateUrl', $url);
        }
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

        // Update hasActiveFilters property
        // Aggiorna la proprietà hasActiveFilters
        $this->hasActiveFilters = $this->hasActiveFilters();

        // Emetti evento per aggiornare la vista principale
        $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
    }

    // Real-time filter update methods - automatically trigger when properties change
    // Metodi di aggiornamento filtri in tempo reale - si attivano automaticamente quando cambiano le proprietà
    public function updatedNome()
    {
        $this->applyFilters();
    }
    public function updatedTitolo()
    {
        $this->applyFilters();
    }
    public function updatedEspansione()
    {
        $this->applyFilters();
    }
    public function updatedTipo()
    {
        $this->applyFilters();
    }
    public function updatedAspettoPrimario()
    {
        $this->applyFilters();
    }
    public function updatedAspettoSecondario()
    {
        $this->applyFilters();
    }
    public function updatedRarita()
    {
        $this->applyFilters();
    }
    public function updatedCostoMin()
    {
        $this->applyFilters();
    }
    public function updatedCostoMax()
    {
        $this->applyFilters();
    }
    public function updatedPotenzaMin()
    {
        $this->applyFilters();
    }
    public function updatedPotenzaMax()
    {
        $this->applyFilters();
    }
    public function updatedVitaMin()
    {
        $this->applyFilters();
    }
    public function updatedVitaMax()
    {
        $this->applyFilters();
    }
    public function updatedTratti()
    {
        $this->applyFilters();
    }
    public function updatedArena()
    {
        $this->applyFilters();
    }
    public function updatedUnica()
    {
        $this->applyFilters();
    }
    public function updatedArtista()
    {
        $this->applyFilters();
    }

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
