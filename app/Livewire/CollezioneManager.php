<?php

namespace App\Livewire;

use App\Models\Card;
use App\Models\Deck;
use App\Models\Composition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Livewire component for collection management with integrated search and filtering
 * Componente Livewire per la gestione della collezione con ricerca e filtri integrati
 *
 * This component combines the functionality of SearchFilter with collection-specific
 * features like managing card copies in the user's collection.
 */
class CollezioneManager extends Component
{
    // Proprietà per i filtri (ereditate da SearchFilter)
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
    public $tratti = '';
    public $arena = '';
    public $unica = null;
    public $artista = '';

    // Valori massimi dal database per i filtri
    public $maxCostoDb = 999;
    public $maxPotenzaDb = 999;
    public $maxVitaDb = 999;

    // Opzioni per i filtri dropdown
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

    // Stato dei filtri avanzati
    public $advancedFiltersOpen = false;

    // Collezione specifica
    public $collezioneId;
    public $collezioneData = [];

    protected $listeners = [
        'resetFilters' => 'resetAllFilters',
        'loadAllCards' => 'loadAllCards'
    ];

    /**
     * Initialize the component
     * Inizializza il componente
     */
    public function mount($collezioneId)
    {
        $this->collezioneId = $collezioneId;
        $this->loadFilterOptions();
        $this->loadCollezioneData();
        
        // Inizializza con array vuoto
        $this->filteredCards = collect([]);
        $this->totalResults = 0;
    }

    /**
     * Load collection data for copy management
     * Carica i dati della collezione per la gestione delle copie
     */
    public function loadCollezioneData()
    {
        $compositions = Composition::where('idMazzo', $this->collezioneId)->get();
        $this->collezioneData = $compositions->keyBy(function($item) {
            return $item->espansione . '-' . $item->numero;
        })->toArray();
    }

    /**
     * Load all filter options from database with caching
     * Carica tutte le opzioni di filtro dal database con cache
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
     */
    public function applyFilters()
    {
        $query = Card::query();

        // Applica tutti i filtri come nel componente SearchFilter
        if (!empty($this->nome)) {
            $query->where('nome', 'like', '%' . $this->nome . '%');
        }

        if (!empty($this->titolo)) {
            $query->where('titolo', 'like', '%' . $this->titolo . '%');
        }

        if (!empty($this->espansione)) {
            $query->where('espansione', $this->espansione);
        }

        if (!empty($this->tipo)) {
            $query->where('tipo', $this->tipo);
        }

        if (!empty($this->aspettoPrimario)) {
            $query->where('aspettoPrimario', $this->aspettoPrimario);
        }

        if (!empty($this->aspettoSecondario)) {
            $query->where('aspettoSecondario', $this->aspettoSecondario);
        }

        if (!empty($this->rarita)) {
            $query->where('rarita', $this->rarita);
        }

        if ($this->costoMin !== null || ($this->costoMax !== null && $this->costoMax < $this->maxCostoDb)) {
            $minCosto = $this->costoMin ?? 0;
            $maxCosto = $this->costoMax ?? $this->maxCostoDb;
            $query->whereBetween('costo', [$minCosto, $maxCosto]);
        }

        if ($this->potenzaMin !== null || ($this->potenzaMax !== null && $this->potenzaMax < $this->maxPotenzaDb)) {
            $minPotenza = $this->potenzaMin ?? 0;
            $maxPotenza = $this->potenzaMax ?? $this->maxPotenzaDb;
            $query->where(function($q) use ($minPotenza, $maxPotenza) {
                $q->whereNull('potenza')
                  ->orWhereBetween('potenza', [$minPotenza, $maxPotenza]);
            });
        }

        if ($this->vitaMin !== null || ($this->vitaMax !== null && $this->vitaMax < $this->maxVitaDb)) {
            $minVita = $this->vitaMin ?? 0;
            $maxVita = $this->vitaMax ?? $this->maxVitaDb;
            $query->where(function($q) use ($minVita, $maxVita) {
                $q->whereNull('vita')
                  ->orWhereBetween('vita', [$minVita, $maxVita]);
            });
        }

        if (!empty($this->tratti)) {
            $query->where('tratti', 'like', '%' . $this->tratti . '%');
        }

        if (!empty($this->arena)) {
            $query->where('arena', $this->arena);
        }

        if ($this->unica !== null) {
            $query->where('unica', $this->unica);
        }

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

        // Emetti evento per aggiornare la vista
        $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
    }

    /**
     * Load all cards without filters
     * Carica tutte le carte senza filtri
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

        // Emetti evento per aggiornare la vista
        $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
    }

    /**
     * Reset all filter values to their default state
     * Reimposta tutti i valori dei filtri al loro stato predefinito
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

        $this->applyFilters();
    }

    /**
     * Toggle the advanced filters section
     * Attiva/disattiva la sezione filtri avanzati
     */
    public function toggleAdvancedFilters()
    {
        $this->advancedFiltersOpen = !$this->advancedFiltersOpen;
    }

    // Real-time filter update methods
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

    public function render()
    {
        return view('livewire.collezione-manager');
    }
}
