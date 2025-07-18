<div class="mb-4">
    <div class="card shadow">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filtri di Ricerca
            </h5>
            <div class="d-flex gap-2">
                @if($mode === 'page' || $mode === 'collezione')
                    <button wire:click="loadAllCards" class="btn btn-outline-primary btn-sm" title="Mostra tutte le carte">
                        <i class="fas fa-list me-1"></i>Tutte
                    </button>
                @endif
                <button wire:click="resetAllFilters" class="btn btn-outline-light btn-sm" title="Resetta tutti i filtri">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
                <button wire:click="toggleMainFilters" type="button" class="btn btn-outline-light btn-sm" title="Mostra/Nascondi filtri">
                    <i class="fas {{ $mainFiltersOpen ? 'fa-chevron-down' : 'fa-chevron-right' }}"></i>
                </button>
            </div>
        </div>

        @if($mainFiltersOpen)
        <div class="card-body">

                <!-- Filtri principali -->
                <div class="row g-3">
                    <!-- Nome -->
                    <div class="col-md-4">
                        <label for="nome" class="form-label fw-bold">Nome della carta</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="nome"
                               class="form-control"
                               id="nome"
                               placeholder="Inserisci il nome...">
                    </div>

                    <!-- Titolo -->
                    <div class="col-md-4">
                        <label for="titolo" class="form-label fw-bold">Titolo</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="titolo"
                               class="form-control"
                               id="titolo"
                               placeholder="Inserisci il titolo...">
                    </div>

                    <!-- Espansione -->
                    <div class="col-md-4">
                        <label for="espansione" class="form-label fw-bold">Espansione</label>
                        <select wire:model.live="espansione" class="form-select" id="espansione">
                            <option value="">Tutte le espansioni</option>
                            @foreach($espansioni as $esp)
                                <option value="{{ $esp }}">{{ $esp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tipo -->
                    <div class="col-md-4">
                        <label for="tipo" class="form-label fw-bold">Tipo</label>
                        <select wire:model.live="tipo" class="form-select" id="tipo">
                            <option value="">Tutti i tipi</option>
                            @foreach($tipi as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Aspetto Primario -->
                    <div class="col-md-4">
                        <label for="aspettoPrimario" class="form-label fw-bold">Aspetto Primario</label>
                        <select wire:model.live="aspettoPrimario" class="form-select" id="aspettoPrimario">
                            <option value="">Tutti gli aspetti</option>
                            @foreach($aspettiPrimari as $asp)
                                <option value="{{ $asp }}">{{ $asp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Aspetto Secondario -->
                    <div class="col-md-4">
                        <label for="aspettoSecondario" class="form-label fw-bold">Aspetto Secondario</label>
                        <select wire:model.live="aspettoSecondario" class="form-select" id="aspettoSecondario">
                            <option value="">Tutti gli aspetti</option>
                            @foreach($aspettiSecondari as $asp)
                                <option value="{{ $asp }}">{{ $asp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Rarità -->
                    <div class="col-md-6">
                        <label for="rarita" class="form-label fw-bold">Rarità</label>
                        <select wire:model.live="rarita" class="form-select" id="rarita">
                            <option value="">Tutte le rarità</option>
                            @foreach($rarita_options as $rar)
                                <option value="{{ $rar }}">{{ $rar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Unica -->
                    <div class="col-md-6">
                        <label for="unica" class="form-label fw-bold">Carta Unica</label>
                        <select wire:model.live="unica" class="form-select" id="unica">
                            <option value="">Tutte</option>
                            <option value="1">Solo uniche</option>
                            <option value="0">Solo non uniche</option>
                        </select>
                    </div>
                </div>

                <!-- Filtri avanzati (collassabili) -->
                <div class="mt-4">
                    <button wire:click="toggleAdvancedFilters" class="btn btn-outline-secondary btn-sm mb-3" type="button">
                        <i class="fas fa-cogs me-1"></i>Filtri Avanzati
                        <i class="fas {{ $advancedFiltersOpen ? 'fa-chevron-up' : 'fa-chevron-down' }} ms-1"></i>
                    </button>

                    <div class="{{ $advancedFiltersOpen ? 'd-block' : 'd-none' }}" id="advancedFilters" style="transition: all 0.3s ease;">
                        <div class="row g-3">
                            <!-- Costo -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Costo</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="costoMin"
                                               class="form-control"
                                               placeholder="Min"
                                               min="0" max="{{ $maxCostoDb }}">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="costoMax"
                                               class="form-control"
                                               oninput="checkMax(this)"
                                               placeholder="Max ({{ $maxCostoDb }})"
                                               min="0" max="{{ $maxCostoDb }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Potenza -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Potenza</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="potenzaMin"
                                               class="form-control"
                                               placeholder="Min"
                                               min="0" max="{{ $maxPotenzaDb }}">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="potenzaMax"
                                               class="form-control"
                                               oninput="checkMax(this)"
                                               placeholder="Max ({{ $maxPotenzaDb }})"
                                               min="0" max="{{ $maxPotenzaDb }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Vita -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Vita</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="vitaMin"
                                               class="form-control"
                                               placeholder="Min"
                                               min="0" max="{{ $maxVitaDb }}">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="vitaMax"
                                               class="form-control"
                                               oninput="checkMax(this)"
                                               placeholder="Max ({{ $maxVitaDb }})"
                                               min="0" max="{{ $maxVitaDb }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Tratti -->
                            <div class="col-md-6">
                                <label for="tratti" class="form-label fw-bold">Tratti</label>
                                <input type="text"
                                       wire:model.live.debounce.300ms="tratti"
                                       class="form-control"
                                       id="tratti"
                                       placeholder="Cerca nei tratti...">
                            </div>

                            <!-- Arena -->
                            <div class="col-md-6">
                                <label for="arena" class="form-label fw-bold">Arena</label>
                                <select wire:model.live="arena" class="form-select" id="arena">
                                    <option value="">Tutte le arene</option>
                                    @foreach($arene as $ar)
                                        <option value="{{ $ar }}">{{ $ar }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Artista -->
                            <div class="col-md-6">
                                <label for="artista" class="form-label fw-bold">Artista</label>
                                <select wire:model.live="artista" class="form-select" id="artista">
                                    <option value="">Tutti gli artisti</option>
                                    @foreach($artisti as $art)
                                        <option value="{{ $art }}">{{ $art }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>


            </div>
        @endif
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestione ESC per chiudere i filtri avanzati
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const advancedFilters = document.getElementById('advancedFilters');
                if (advancedFilters && !advancedFilters.classList.contains('d-none')) {
                    @this.call('toggleAdvancedFilters');
                }
            }
        });
    });
    function checkMax(input) {
        console.log(input);
        console.log(input.value);
        if(empty(input.value)){
            input.value = undefined;
        }
    }
</script>
