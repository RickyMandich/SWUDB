<div>
    <!-- Modal Livewire per l'aggiunta di carte -->
    <div x-data="{ show: @entangle('isOpen').live }"
         x-show="show"
         @keydown.escape.window="$wire.close()"
         class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
         style="display: none; z-index: 1050;">

        <!-- Overlay di sfondo -->
        <div class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50"></div>

        <!-- Modal contenuto -->
        <div class="position-relative">
            <div class="bg-white rounded shadow-lg overflow-hidden" style="width: 500px; max-width: 90vw;" @click.outside="$wire.close()">
                <!-- Header -->
                <div class="p-4 border-b bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="text-lg font-semibold mb-0">Aggiungi carte al mazzo</h3>
                    <button wire:click="toggleFilters" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-filter me-1"></i>
                        {{ $showFilters ? 'Nascondi' : 'Mostra' }} Filtri
                    </button>
                </div>

                <!-- Filtri (se abilitati) -->
                @if($showFilters)
                <div class="p-3 bg-light border-bottom">
                    @livewire('search-filter', ['mode' => 'popup'])
                </div>
                @endif

                <!-- Body -->
                <div class="p-4">
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
                            <button wire:click="addCardsToDeck" class="btn btn-success">Aggiungi</button>
                        </div>
                    @endif
                </div>
                
                <!-- Footer -->
                <div class="p-4 border-top bg-light d-flex justify-content-end">
                    <button class="btn btn-secondary" wire:click="close">Chiudi</button>
                </div>
            </div>
        </div>
    </div>
</div>