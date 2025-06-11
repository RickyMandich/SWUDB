<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Card;


class SearchFilter extends Component
{
    // Proprietà per i filtri
    public $nome = '';
    public $espansione = '';
    public $tipo = '';
    public $aspettoPrimario = '';
    public $aspettoSecondario = '';
    public $rarita = '';
    public $costoMin = 0;
    public $costoMax = 20;
    public $potenzaMin = 0;
    public $potenzaMax = 20;
    public $vitaMin = 0;
    public $vitaMax = 20;
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

    // Modalità di utilizzo (per la pagina principale o per il popup)
    public $mode = 'page'; // 'page' o 'popup'

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
    }

    public function applyFilters()
    {
        $query = Card::query();

        // Filtro per nome
        if (!empty($this->nome)) {
            $query->where('nome', 'like', '%' . $this->nome . '%');
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

        // Filtro per costo
        $query->whereBetween('costo', [$this->costoMin, $this->costoMax]);

        // Filtro per potenza (solo se non null)
        $query->where(function($q) {
            $q->whereNull('potenza')
              ->orWhereBetween('potenza', [$this->potenzaMin, $this->potenzaMax]);
        });

        // Filtro per vita (solo se non null)
        $query->where(function($q) {
            $q->whereNull('vita')
              ->orWhereBetween('vita', [$this->vitaMin, $this->vitaMax]);
        });

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
        }
    }

    public function resetAllFilters()
    {
        $this->nome = '';
        $this->espansione = '';
        $this->tipo = '';
        $this->aspettoPrimario = '';
        $this->aspettoSecondario = '';
        $this->rarita = '';
        $this->costoMin = 0;
        $this->costoMax = 20;
        $this->potenzaMin = 0;
        $this->potenzaMax = 20;
        $this->vitaMin = 0;
        $this->vitaMax = 20;
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
