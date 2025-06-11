<div class="search-filter-container">
    <div class="card">
        <div class="card-header bg-secondary d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filtri di Ricerca
            </h5>
            <div class="d-flex gap-2">
                <button wire:click="resetAllFilters" class="btn btn-outline-secondary btn-sm  text-primary-emphasis">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm text-primary-emphasis" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
        </div>

        <div class="collapse show" id="filterCollapse">
            <div class="card-body">
                <!-- Risultati -->
                @if($mode === 'page')
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Trovati <strong>{{ $totalResults }}</strong> risultati
                </div>
                @endif

                <!-- Filtri principali -->
                <div class="row g-3">
                    <!-- Nome -->
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome della carta</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="nome"
                               class="form-control"
                               id="nome"
                               placeholder="Inserisci il nome...">
                    </div>

                    <!-- Espansione -->
                    <div class="col-md-6">
                        <label for="espansione" class="form-label">Espansione</label>
                        <select wire:model.live="espansione" class="form-select" id="espansione">
                            <option value="">Tutte le espansioni</option>
                            @foreach($espansioni as $esp)
                                <option value="{{ $esp }}">{{ $esp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tipo -->
                    <div class="col-md-4">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select wire:model.live="tipo" class="form-select" id="tipo">
                            <option value="">Tutti i tipi</option>
                            @foreach($tipi as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Aspetto Primario -->
                    <div class="col-md-4">
                        <label for="aspettoPrimario" class="form-label">Aspetto Primario</label>
                        <select wire:model.live="aspettoPrimario" class="form-select" id="aspettoPrimario">
                            <option value="">Tutti gli aspetti</option>
                            @foreach($aspettiPrimari as $asp)
                                <option value="{{ $asp }}">{{ $asp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Aspetto Secondario -->
                    <div class="col-md-4">
                        <label for="aspettoSecondario" class="form-label">Aspetto Secondario</label>
                        <select wire:model.live="aspettoSecondario" class="form-select" id="aspettoSecondario">
                            <option value="">Tutti gli aspetti</option>
                            @foreach($aspettiSecondari as $asp)
                                <option value="{{ $asp }}">{{ $asp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Rarità -->
                    <div class="col-md-6">
                        <label for="rarita" class="form-label">Rarità</label>
                        <select wire:model.live="rarita" class="form-select" id="rarita">
                            <option value="">Tutte le rarità</option>
                            @foreach($rarita_options as $rar)
                                <option value="{{ $rar }}">{{ $rar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Unica -->
                    <div class="col-md-6">
                        <label for="unica" class="form-label">Carta Unica</label>
                        <select wire:model.live="unica" class="form-select" id="unica">
                            <option value="">Tutte</option>
                            <option value="1">Solo uniche</option>
                            <option value="0">Solo non uniche</option>
                        </select>
                    </div>
                </div>

                <!-- Filtri avanzati (collassabili) -->
                <div class="mt-4">
                    <button class="btn btn-outline-secondary btn-sm mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                        <i class="fas fa-cogs me-1"></i>Filtri Avanzati
                    </button>

                    <div class="collapse" id="advancedFilters">
                        <div class="row g-3">
                            <!-- Costo -->
                            <div class="col-md-6">
                                <label class="form-label">Costo</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="costoMin"
                                               class="form-control"
                                               placeholder="Min"
                                               min="0" max="20">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="costoMax"
                                               class="form-control"
                                               placeholder="Max"
                                               min="0" max="20">
                                    </div>
                                </div>
                            </div>

                            <!-- Potenza -->
                            <div class="col-md-6">
                                <label class="form-label">Potenza</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="potenzaMin"
                                               class="form-control"
                                               placeholder="Min"
                                               min="0" max="20">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="potenzaMax"
                                               class="form-control"
                                               placeholder="Max"
                                               min="0" max="20">
                                    </div>
                                </div>
                            </div>

                            <!-- Vita -->
                            <div class="col-md-6">
                                <label class="form-label">Vita</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="vitaMin"
                                               class="form-control"
                                               placeholder="Min"
                                               min="0" max="20">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number"
                                               wire:model.live="vitaMax"
                                               class="form-control"
                                               placeholder="Max"
                                               min="0" max="20">
                                    </div>
                                </div>
                            </div>

                            <!-- Tratti -->
                            <div class="col-md-6">
                                <label for="tratti" class="form-label">Tratti</label>
                                <input type="text"
                                       wire:model.live.debounce.300ms="tratti"
                                       class="form-control"
                                       id="tratti"
                                       placeholder="Cerca nei tratti...">
                            </div>

                            <!-- Arena -->
                            <div class="col-md-6">
                                <label for="arena" class="form-label">Arena</label>
                                <select wire:model.live="arena" class="form-select" id="arena">
                                    <option value="">Tutte le arene</option>
                                    @foreach($arene as $ar)
                                        <option value="{{ $ar }}">{{ $ar }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Artista -->
                            <div class="col-md-6">
                                <label for="artista" class="form-label">Artista</label>
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
        </div>
    </div>
</div>
