<div>
    <!-- Modal Livewire per l'aggiunta di carte -->
    @if($isOpen)
    <div @keydown.escape.window="$wire.close()"
         class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3"
         style="display: none; z-index: 1050; overflow-y: auto;">

        <!-- Overlay di sfondo -->
        <div class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50"
             wire:click="close"></div>

        <!-- Modal contenuto -->
        <div class="position-relative w-100 d-flex align-items-center justify-content-center" style="min-height: 100%;">
            <div class="card shadow-lg bg-white text-dark" style="width: 600px; max-width: 100%; max-height: 95vh; overflow-y: auto;" data-bs-theme="light">
                <!-- Header -->
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-plus me-2"></i>Aggiungi carte al mazzo
                    </h5>
                    <button wire:click="close"
                            class="btn btn-outline-light btn-sm" title="Chiudi">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Filtri -->
                <div class="bg-light border-bottom p-3">
                    <!-- Loading per apertura popup -->
                    <div wire:loading wire:target="open" class="text-center py-2">
                        <small class="text-muted">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                            Caricamento filtri...
                        </small>
                    </div>

                    <!-- Filtri normali -->
                    <div wire:loading.remove wire:target="open">
                        @livewire('search-filter', ['mode' => 'popup'])
                    </div>
                </div>

                <!-- Body -->
                <div class="card-body">
                    <!-- Loading Spinner per apertura popup -->
                    <div wire:loading wire:target="open" class="d-flex justify-content-center align-items-center py-5">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Caricamento...</span>
                            </div>
                            <p class="text-muted mb-0">
                                <i class="fas fa-search me-2"></i>Caricamento carte disponibili...
                            </p>
                        </div>
                    </div>

                    <!-- Contenuto normale -->
                    <div wire:loading.remove wire:target="open">
                    <!-- Contatore carte disponibili -->
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            {{ count($filteredCards) }} carte disponibili
                        </small>
                    </div>

                    <!-- Selettore carta -->
                    <div class="mb-4">
                        <select wire:model.live="selectedCardId" class="form-select form-select-lg w-100">
                            <option value="" selected disabled>---Seleziona una carta---</option>
                            @foreach($filteredCards as $card)
                                <option value="{{ $card['id'] }}">{{ $card['snippet'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Selettore copie -->
                    @if($selectedCardId)
                        <div class="mb-4">
                            <div class="d-flex justify-content-center gap-2" id="copie">
                                @for($i = 1; $i <= $maxCopies; $i++)
                                    <div>
                                        <input type="radio" wire:model="copiesAmount" value="{{ $i }}" id="copie-{{ $i }}" class="btn-check" autocomplete="off" @if($i==1) checked @endif>
                                        <label class="btn btn-outline-success" for="copie-{{ $i }}">{{ $i }}</label>
                                    </div>
                                @endfor
                            </div>
                        </div>
                        
                        <!-- Bottone aggiungi -->
                        <div class="text-center">
                            <button wire:click="addCardsToDeck"
                                    class="btn btn-success"
                                    wire:loading.attr="disabled"
                                    wire:target="addCardsToDeck">
                                <span wire:loading.remove wire:target="addCardsToDeck">
                                    <i class="fas fa-plus me-2"></i>Aggiungi
                                </span>
                                <span wire:loading wire:target="addCardsToDeck">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Aggiungendo...
                                </span>
                            </button>
                        </div>
                    @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>