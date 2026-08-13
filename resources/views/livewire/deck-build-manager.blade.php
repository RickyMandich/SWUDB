<div class="container py-4">
    <!-- Intestazione -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="{{ route('mazzo', ['user' => $user, 'mazzo' => str_replace(' ', '+', $nome)]) }}"
               class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i>Torna al mazzo
            </a>
            <h1 class="h2 mb-0"><i class="fas fa-tools me-2 text-primary"></i>Build Mazzo: {{ $nome }}</h1>
            <p class="text-muted mb-0">Proprietario mazzo: <strong>{{ $user }}</strong> | Confronto con la collezione di <strong>{{ Auth::user()->name }}</strong></p>
        </div>
        <div>
            <a href="{{ route('mazzo.build.export', ['user' => $user, 'mazzo' => str_replace(' ', '+', $nome)]) }}"
               class="btn btn-success">
                <i class="fas fa-download me-1"></i>Scarica Lista Mancanti TXT
            </a>
        </div>
    </div>

    <!-- Schede KPI / Statistiche -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <span class="text-muted small text-uppercase font-monospace fw-bold">Totale Carte Mazzo</span>
                    <h2 class="display-6 fw-bold text-primary my-1">{{ $totaleCarteMazzo }}</h2>
                    <small class="text-muted">carte necessarie</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <span class="text-muted small text-uppercase font-monospace fw-bold">In Collezione</span>
                    <h2 class="display-6 fw-bold text-success my-1">{{ $totaleCopiePossedute }}</h2>
                    <small class="text-muted">carte disponibili per il mazzo</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <span class="text-muted small text-uppercase font-monospace fw-bold">Carte Mancanti</span>
                    <h2 class="display-6 fw-bold {{ $totaleCarteMancanti > 0 ? 'text-danger' : 'text-success' }} my-1">
                        {{ $totaleCarteMancanti }}
                    </h2>
                    <small class="text-muted">({{ $countCarteMancantiDistinte }} carte distinte)</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 shadow-sm text-center">
                <div class="card-body">
                    <span class="text-muted small text-uppercase font-monospace fw-bold">Completamento</span>
                    <h2 class="display-6 fw-bold {{ $percentualeCompletamento == 100 ? 'text-success' : 'text-warning' }} my-1">
                        {{ $percentualeCompletamento }}%
                    </h2>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar {{ $percentualeCompletamento == 100 ? 'bg-success' : 'bg-warning' }}"
                             role="progressbar"
                             style="width: {{ $percentualeCompletamento }}%"
                             aria-valuenow="{{ $percentualeCompletamento }}"
                             aria-valuemin="0"
                             aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sezione Esportazione Carte Mancanti -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-file-alt text-danger me-2"></i>Lista Carte Mancanti (Post-MergeSort)
            </h5>
            <div>
                <button type="button"
                        class="btn btn-outline-primary btn-sm me-1"
                        onclick="copyMissingTxtToClipboard()">
                    <i class="fas fa-copy me-1"></i>Copia negli Appunti
                </button>
                <a href="{{ route('mazzo.build.export', ['user' => $user, 'mazzo' => str_replace(' ', '+', $nome)]) }}"
                   class="btn btn-outline-success btn-sm">
                    <i class="fas fa-file-download me-1"></i>Esporta TXT
                </a>
            </div>
        </div>
        <div class="card-body">
            @if(!empty($missingTxt))
                <textarea id="missingTxtContent"
                          class="form-control font-monospace bg-light"
                          rows="4"
                          readonly>{{ $missingTxt }}</textarea>
                <small class="text-muted mt-1 d-block">
                    <i class="fas fa-info-circle me-1"></i>Formato: <code>{qty mancante}x {espansione} {numero} {nome} ({rarità})</code>
                </small>
            @else
                <div class="alert alert-success mb-0 d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle fa-2x me-3"></i>
                    <div>
                        <strong>Complimenti!</strong> Possiedi in collezione tutte le carte necessarie per completare questo mazzo!
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Controlli Filtro e Ricerca -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text"
                               class="form-control border-start-0 ps-0"
                               placeholder="Cerca carta per nome o espansione..."
                               wire:model.live.debounce.250ms="searchQuery">
                    </div>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <div class="btn-group w-100 w-md-auto" role="group" aria-label="Filtro carte">
                        <button type="button"
                                class="btn {{ $filtro === 'tutte' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                wire:click="$set('filtro', 'tutte')">
                            Tutte le carte
                        </button>
                        <button type="button"
                                class="btn {{ $filtro === 'mancanti' ? 'btn-danger' : 'btn-outline-danger' }}"
                                wire:click="$set('filtro', 'mancanti')">
                            Solo Mancanti
                        </button>
                        <button type="button"
                                class="btn {{ $filtro === 'possedute' ? 'btn-success' : 'btn-outline-success' }}"
                                wire:click="$set('filtro', 'possedute')">
                            Solo Possedute
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista / Tabella Carte Mazzo vs Collezione -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Stato</th>
                        <th style="width: 110px;">Carta</th>
                        <th>Nome Carta</th>
                        <th style="width: 100px;" class="text-center">Nel Mazzo</th>
                        <th style="width: 180px;" class="text-center">In Collezione</th>
                        <th style="width: 110px;" class="text-center">Mancanti</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cards as $carta)
                        <tr class="{{ $carta->is_complete ? 'table-success-subtle' : 'table-danger-subtle' }}">
                            <!-- Indicatore / Colouring parlante -->
                            <td>
                                @if($carta->is_complete)
                                    <span class="badge bg-success px-2 py-1">
                                        <i class="fas fa-check me-1"></i>OK
                                    </span>
                                @else
                                    <span class="badge bg-danger px-2 py-1">
                                        <i class="fas fa-exclamation-triangle me-1"></i>-{{ $carta->copie_mancanti }}
                                    </span>
                                @endif
                            </td>

                            <!-- Espansione e Numero -->
                            <td>
                                <span class="badge bg-secondary font-monospace">
                                    {{ $carta->espansione }} {{ $carta->numero }}
                                </span>
                            </td>

                            <!-- Nome e Rarità -->
                            <td>
                                <a href="{{ route('carta', ['espansione' => $carta->espansione, 'numero' => $carta->numero]) }}"
                                   target="_blank"
                                   class="fw-bold text-decoration-none text-dark">
                                    {{ $carta->nome }}
                                </a>
                                @if(!empty($carta->titolo))
                                    <small class="text-muted d-block">{{ $carta->titolo }}</small>
                                @endif
                                <span class="badge bg-light text-dark border ms-1">{{ $carta->rarita }}</span>
                            </td>

                            <!-- Quantità nel Mazzo -->
                            <td class="text-center fw-bold fs-5">
                                {{ $carta->copie_mazzo }}x
                            </td>

                            <!-- Quantità in Collezione (Pulsanti + e -) -->
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button"
                                            class="btn btn-outline-danger px-2"
                                            wire:click="modificaCopiaCollezione('{{ $carta->espansione }}', {{ $carta->numero }}, -1)"
                                            title="Rimuovi 1 copia dalla collezione">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="btn btn-light disabled px-3 font-monospace fw-bold text-dark border">
                                        {{ $carta->copie_collezione }}
                                    </span>
                                    <button type="button"
                                            class="btn btn-outline-success px-2"
                                            wire:click="modificaCopiaCollezione('{{ $carta->espansione }}', {{ $carta->numero }}, 1)"
                                            title="Aggiungi 1 copia alla collezione">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </td>

                            <!-- Carte Mancanti -->
                            <td class="text-center">
                                @if($carta->copie_mancanti > 0)
                                    <span class="fw-bold text-danger fs-5">{{ $carta->copie_mancanti }}x</span>
                                @else
                                    <span class="text-success"><i class="fas fa-check-circle fs-5"></i></span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                Nessuna carta trovata con i filtri correnti.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function copyMissingTxtToClipboard() {
        const textarea = document.getElementById('missingTxtContent');
        if (!textarea || !textarea.value) {
            alert('Nessuna carta mancante da copiare!');
            return;
        }
        navigator.clipboard.writeText(textarea.value).then(() => {
            alert('Lista carte mancanti copiata negli appunti!');
        }).catch(err => {
            console.error('Errore nella copia: ', err);
        });
    }
</script>
