<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Card;


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

    protected $listeners = [
        'resetFilters' => 'resetAllFilters',
        'applyFiltersForPopup' => 'getFilteredCardsForPopup'
    ];

    public function mount($mode = 'page', $initialEspansione = '')
    {
        $this->mode = $mode;
        $this->espansione = $initialEspansione;
        $this->loadFilterOptions();
        $this->applyFilters();
    }

    public function loadFilterOptions()
    {
        // Carica tutte le opzioni uniche per i filtri
        $this->espansioni = Card::select('espansione')
            ->selectRaw('MIN(uscita) as prima_uscita')
            ->groupBy('espansione')
            ->orderBy('prima_uscita')
            ->pluck('espansione')
            ->toArray();

        $this->tipi = Card::select('tipo')
            ->distinct()
            ->orderBy('tipo')
            ->pluck('tipo')
            ->toArray();

        $this->aspettiPrimari = Card::select('aspettoPrimario')
            ->distinct()
            ->whereNotNull('aspettoPrimario')
            ->orderBy('aspettoPrimario')
            ->pluck('aspettoPrimario')
            ->toArray();

        $this->aspettiSecondari = Card::select('aspettoSecondario')
            ->distinct()
            ->whereNotNull('aspettoSecondario')
            ->orderBy('aspettoSecondario')
            ->pluck('aspettoSecondario')
            ->toArray();

        $this->rarita_options = Card::select('rarita')
            ->distinct()
            ->orderBy('rarita')
            ->pluck('rarita')
            ->toArray();

        $this->arene = Card::select('arena')
            ->distinct()
            ->whereNotNull('arena')
            ->orderBy('arena')
            ->pluck('arena')
            ->toArray();

        $this->artisti = Card::select('artista')
            ->distinct()
            ->orderBy('artista')
            ->pluck('artista')
            ->toArray();

        // Carica i valori massimi dal database
        $this->maxCostoDb = Card::max('costo') ?? 999;
        $this->maxPotenzaDb = Card::max('potenza') ?? 999;
        $this->maxVitaDb = Card::max('vita') ?? 999;
    }

    public function applyFilters()
    {
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
        if ($this->mode === 'page') {
            $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
        } elseif ($this->mode === 'popup') {
            // Emetti evento specifico per il popup
            $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
        } elseif ($this->mode === 'collezione') {
            // Emetti evento per la collezione
            $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
        }
    }

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

    public function getFilteredCardsForPopup()
    {
        $this->applyFilters();
        return $this->filteredCards;
    }

    // Metodi per aggiornare i filtri in tempo reale
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
        return view('livewire.search-filter');
    }
}
