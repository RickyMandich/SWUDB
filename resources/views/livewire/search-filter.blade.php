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
                    @if($hasActiveFilters)
                        <button onclick="saveCurrentSearch()" class="btn btn-outline-success btn-sm"
                            title="Salva ricerca corrente">
                            <i class="fas fa-bookmark me-1"></i>Salva
                        </button>
                        <button onclick="shareCurrentSearch()" class="btn btn-outline-info btn-sm" title="Condividi ricerca">
                            <i class="fas fa-share me-1"></i>Condividi
                        </button>
                    @endif
                    <button onclick="showSavedSearches()" class="btn btn-outline-warning btn-sm"
                        title="Visualizza ricerche salvate">
                        <i class="fas fa-history me-1"></i>Salvate
                    </button>
                @endif
                <button wire:click="resetAllFilters" class="btn btn-outline-light btn-sm"
                    title="Resetta tutti i filtri">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
                <button wire:click="toggleMainFilters" type="button" class="btn btn-outline-light btn-sm"
                    title="Mostra/Nascondi filtri">
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
                        <input type="text" wire:model.live.debounce.300ms="nome" class="form-control" id="nome"
                            placeholder="Inserisci il nome...">
                    </div>

                    <!-- Titolo -->
                    <div class="col-md-4">
                        <label for="titolo" class="form-label fw-bold">Titolo</label>
                        <input type="text" wire:model.live.debounce.300ms="titolo" class="form-control" id="titolo"
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

                    <!-- Aspetti (Toggle Buttons) -->
                    <div class="col-md-8">
                        <label class="form-label fw-bold d-block">Aspetti</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($aspetti_options as $asp)
                                @php
                                    $bgColor = $asp->colore ?? '#6c757d';
                                    $textColor = ($asp->nome === 'Eroismo' || $bgColor === '#ffffff') ? '#000' : '#fff';
                                @endphp
                                <div class="form-check-inline m-0">
                                    <input type="checkbox" class="btn-check" id="aspect-{{ $asp->id }}" 
                                        wire:model.live="aspetti" value="{{ $asp->nome }}" autocomplete="off">
                                    <label class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm border-2" 
                                        for="aspect-{{ $asp->id }}"
                                        style="--bs-btn-active-bg: {{ $bgColor }}; --bs-btn-active-border-color: {{ $bgColor }}; --bs-btn-active-color: {{ $textColor }};">
                                        {{ $asp->nome }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Rarità -->
                    <div class="col-md-4">
                        <label for="rarita" class="form-label fw-bold">Rarità</label>
                        <select wire:model.live="rarita" class="form-select" id="rarita">
                            <option value="">Tutte le rarità</option>
                            @foreach($rarita_options as $rar)
                                <option value="{{ $rar }}">{{ $rar }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Unica -->
                    <div class="col-md-4">
                        <label for="unica" class="form-label fw-bold">Carta Unica</label>
                        <select wire:model.live="unica" class="form-select" id="unica">
                            <option value="">Tutte</option>
                            <option value="1">Solo uniche</option>
                            <option value="0">Solo non uniche</option>
                        </select>
                    </div>

                    <!-- Rotazione -->
                    <div class="col-md-4">
                        <label for="rotazione" class="form-label fw-bold">Rotazione</label>
                        <select wire:model.live="rotazione" class="form-select" id="rotazione">
                            <option value="">Tutte le rotazioni</option>
                            @foreach($rotazioni as $rot)
                                <option value="{{ $rot }}">{{ $rot }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Filtri avanzati (collassabili) -->
                <div class="mt-4">
                    <button wire:click="toggleAdvancedFilters" class="btn btn-outline-secondary btn-sm mb-3" type="button">
                        <i class="fas fa-cogs me-1"></i>Filtri Avanzati
                        <i class="fas {{ $advancedFiltersOpen ? 'fa-chevron-up' : 'fa-chevron-down' }} ms-1"></i>
                    </button>

                    <div class="{{ $advancedFiltersOpen ? 'd-block' : 'd-none' }}" id="advancedFilters"
                        style="transition: all 0.3s ease;">
                        <div class="row g-3">
                            <!-- Costo -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Costo</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number" wire:model.live="costoMin" class="form-control"
                                            placeholder="Min" min="0" max="{{ $maxCostoDb }}">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number" wire:model.live="costoMax" class="form-control"
                                            oninput="checkMax(this)" placeholder="Max ({{ $maxCostoDb }})" min="0"
                                            max="{{ $maxCostoDb }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Potenza -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Potenza</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number" wire:model.live="potenzaMin" class="form-control"
                                            placeholder="Min" min="0" max="{{ $maxPotenzaDb }}">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number" wire:model.live="potenzaMax" class="form-control"
                                            oninput="checkMax(this)" placeholder="Max ({{ $maxPotenzaDb }})" min="0"
                                            max="{{ $maxPotenzaDb }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Vita -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Vita</label>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number" wire:model.live="vitaMin" class="form-control"
                                            placeholder="Min" min="0" max="{{ $maxVitaDb }}">
                                    </div>
                                    <div class="col-auto align-self-center">-</div>
                                    <div class="col">
                                        <input type="number" wire:model.live="vitaMax" class="form-control"
                                            oninput="checkMax(this)" placeholder="Max ({{ $maxVitaDb }})" min="0"
                                            max="{{ $maxVitaDb }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Tratti -->
                            <div class="col-md-6">
                                <label for="tratti" class="form-label fw-bold">Tratti</label>
                                <input type="text" wire:model.live.debounce.300ms="tratti" class="form-control" id="tratti"
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
    document.addEventListener('DOMContentLoaded', function () {
        // Gestione ESC per chiudere i filtri avanzati
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                const advancedFilters = document.getElementById('advancedFilters');
                if (advancedFilters && !advancedFilters.classList.contains('d-none')) {
                    @this.call('toggleAdvancedFilters');
                }
            }
        });
    });
    function checkMax(input) {
        if (!input.value || input.value === '') {
            input.value = '';
        }
    }

    // Listen for URL update events from Livewire
    // Ascolta gli eventi di aggiornamento URL da Livewire
    document.addEventListener('livewire:init', () => {
        Livewire.on('updateUrl', (url) => {
            // Update browser URL without page reload
            // Aggiorna l'URL del browser senza ricaricare la pagina
            window.history.pushState({}, '', url[0]);
        });
    });

    // Function to save current search to localStorage
    // Funzione per salvare la ricerca corrente nel localStorage
    function saveCurrentSearch() {
        const currentUrl = window.location.href;
        const searchName = prompt('Inserisci un nome per questa ricerca:');

        if (searchName && searchName.trim()) {
            let savedSearches = JSON.parse(localStorage.getItem('savedSearches') || '[]');

            // Remove existing search with same name
            // Rimuovi ricerca esistente con lo stesso nome
            savedSearches = savedSearches.filter(search => search.name !== searchName.trim());

            // Add new search
            // Aggiungi nuova ricerca
            savedSearches.push({
                name: searchName.trim(),
                url: currentUrl,
                date: new Date().toISOString()
            });

            localStorage.setItem('savedSearches', JSON.stringify(savedSearches));
            alert('Ricerca salvata con successo!');
        }
    }

    // Function to share current search
    // Funzione per condividere la ricerca corrente
    function shareCurrentSearch() {
        const currentUrl = window.location.href;

        if (navigator.share) {
            // Use native sharing if available
            // Usa condivisione nativa se disponibile
            navigator.share({
                title: 'Ricerca Carte - UnlimitedDB',
                text: 'Guarda questa ricerca di carte su UnlimitedDB',
                url: currentUrl
            });
        } else {
            // Fallback: copy to clipboard
            // Fallback: copia negli appunti
            navigator.clipboard.writeText(currentUrl).then(() => {
                alert('URL copiato negli appunti!');
            }).catch(() => {
                // Fallback for older browsers
                // Fallback per browser più vecchi
                const textArea = document.createElement('textarea');
                textArea.value = currentUrl;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('URL copiato negli appunti!');
            });
        }
    }

    // Function to show saved searches
    // Funzione per mostrare le ricerche salvate
    function showSavedSearches() {
        const savedSearches = JSON.parse(localStorage.getItem('savedSearches') || '[]');

        if (savedSearches.length === 0) {
            alert('Nessuna ricerca salvata trovata.');
            return;
        }

        // Create modal content
        // Crea contenuto del modal
        let modalContent = '<div class="list-group">';
        savedSearches.forEach((search, index) => {
            const date = new Date(search.date).toLocaleDateString('it-IT');
            modalContent += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${search.name}</h6>
                        <small class="text-muted">Salvata il ${date}</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="loadSavedSearch('${search.url}')">
                            <i class="fas fa-external-link-alt"></i> Carica
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSavedSearch(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        modalContent += '</div>';

        // Show modal
        // Mostra modal
        showModal('Ricerche Salvate', modalContent);
    }

    // Function to load a saved search
    // Funzione per caricare una ricerca salvata
    function loadSavedSearch(url) {
        window.location.href = url;
    }

    // Function to delete a saved search
    // Funzione per eliminare una ricerca salvata
    function deleteSavedSearch(index) {
        if (confirm('Sei sicuro di voler eliminare questa ricerca salvata?')) {
            let savedSearches = JSON.parse(localStorage.getItem('savedSearches') || '[]');
            savedSearches.splice(index, 1);
            localStorage.setItem('savedSearches', JSON.stringify(savedSearches));
            showSavedSearches(); // Refresh the modal
        }
    }

    // Generic modal function
    // Funzione modal generica
    function showModal(title, content) {
        // Remove existing modal if any
        // Rimuovi modal esistente se presente
        const existingModal = document.getElementById('savedSearchesModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal
        // Crea modal
        const modalHtml = `
            <div class="modal fade" id="savedSearchesModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${content}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page and show
        // Aggiungi modal alla pagina e mostra
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('savedSearchesModal'));
        modal.show();

        // Remove modal from DOM when hidden
        // Rimuovi modal dal DOM quando nascosto
        document.getElementById('savedSearchesModal').addEventListener('hidden.bs.modal', function () {
            this.remove();
        });
    }
</script>