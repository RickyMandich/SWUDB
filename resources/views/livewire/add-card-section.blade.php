<div class="aggiungi-carte mb-4">
    <h3>
        <i class="fas fa-plus me-2"></i>Aggiungi carte
    </h3>
    
    <div class="card">
        <!-- Filtri -->
        <div class="card-header">
            @livewire('search-filter', ['mode' => 'popup'])
        </div>

        <!-- Body -->
        <div class="card-body">
            <!-- Contatore carte disponibili -->
            <div class="mb-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ count($filteredCards) }} carte disponibili
                </small>
            </div>

            <!-- Selettore carta -->
            <div class="mb-3">
                <select wire:model.live="selectedCardId" class="form-select w-100">
                    <option value="" selected disabled>---Seleziona una carta---</option>
                    @foreach($filteredCards as $card)
                        <option value="{{ $card['id'] }}">{{ $card['snippet'] }}</option>
                    @endforeach
                </select>
            </div>
            
            <!-- Selettore copie -->
            @if($selectedCardId)
                <div class="mb-3">
                    <label class="form-label">Numero di copie:</label>
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
                    <button wire:click="addCardsToDeck" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Aggiungi al mazzo
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
