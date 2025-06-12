<div>
    <div class="header text-center mb-4">
        <h1>
            {{ $nome }} 
            @if($proprietario)
                <button wire:click="openAddCardPopup" class="btn btn-primary"> + </button>
            @endif
        </h1>
        <h4>
            <small class="text-muted">di {{ $user }}</small>
        </h4>
        <h6>
            @if ($size == 1)
                in questo mazzo è presente {{ $size }} carta
            @else
                in questo mazzo sono presenti {{ $size }} carte
            @endif
        </h6>
    </div>
    <div class="container">
        <div class="row">
            <!-- Colonna sinistra -->
            <div class="col-md-6">
                <div class="mazzo">
                    <h3 class="mb-3">Mazzo</h3>
                    <div class="contenuto">
                        @foreach($mazzo as $id => $carta)
                            <span class="d-flex mt-4">
                                {{ $carta['copie'] }}x
                                @if ($proprietario)
                                    <button type="button" wire:click="aumentaCopia('{{ $id }}')" class="btn btn-success rounded-0 rounded-start-1 border-end-0 py-1 px-2 lh-1">+</button>
                                    <button type="button" wire:click="diminuisciCopia('{{ $id }}')" class="btn btn-danger rounded-0 rounded-end-1 border-start-0 py-1 px-2 lh-1">-</button>
                                @endif
                                <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}" target="_blank">
                                    {{ $carta['snippet'] }}
                                </a>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- Colonna destra -->
            @if ($proprietario)
                <div class="col-md-6">
                    <div class="aggiunte mb-4">
                        <h3>Carte aggiunte</h3>
                        <div class="mb-4 contenuto">
                            @foreach($aggiunte as $id => $carta)
                                <span class="d-flex mt-4">
                                    {{ $carta['copie'] }}x
                                    @if ($proprietario)
                                        <button type="button" wire:click="diminuisciCopia('{{ $id }}')" class="btn btn-danger rounded-1 border-0 py-1 px-2 lh-1">-</button>
                                    @endif
                                    <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}" target="_blank">
                                        {{ $carta['snippet'] }}
                                    </a>
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="rimosse">
                        <h3>Carte rimosse</h3>
                        <div class="mb-4 contenuto">
                            @foreach($rimosse as $id => $carta)
                                <span class="d-flex mt-4">
                                    {{ $carta['copie'] }}x
                                    <button type="button" wire:click="aumentaCopia('{{ $id }}')" class="btn btn-success rounded-1 border-0 py-1 px-2 lh-1">+</button>
                                    <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}" target="_blank">
                                        {{ $carta['snippet'] }}
                                    </a>
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="form">
                        <form id="saveDeckForm" method="POST" action="{{ route('mazzo.save', ['user' => $user, 'mazzo' => $deck]) }}">
                            @csrf
                            <button type="button" wire:click="saveDeck" class="btn btn-success">Save</button>
                            
                            <div id="modifiche">
                                <!-- I campi nascosti verranno generati dinamicamente dal JavaScript -->
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sezione Analisi Statistiche -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="analisi-statistiche">
                    <h3 class="mb-3">Analisi Statistiche del Mazzo</h3>
                    <div class="row">
                        <!-- Numero Carte -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Numero Carte</h5>
                                    <h2 class="text-primary">{{ $size }}</h2>
                                    <small class="text-muted">carte totali</small>
                                </div>
                            </div>
                        </div>

                        <!-- HP/Vita Media -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title">HP/Vita Media</h5>
                                    <h2 class="text-success">{{ $vitaMedia }}</h2>
                                    <small class="text-muted">punti vita medi</small>
                                </div>
                            </div>
                        </div>

                        <!-- Tratti -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Tratti Principali</h5>
                                    <div class="contenuto">
                                        @foreach($trattiPrincipali as $tratto => $count)
                                            <div class="d-flex justify-content-between">
                                                <span class="badge bg-secondary mb-1">{{ $tratto }}</span>
                                                <small class="text-muted">{{ $count }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Keywords -->
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Keywords Frequenti</h5>
                                    <div class="contenuto">
                                        @foreach($keywordsFrequenti as $keyword => $count)
                                            <div class="d-flex justify-content-between">
                                                <span class="badge bg-info mb-1">{{ $keyword }}</span>
                                                <small class="text-muted">{{ $count }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Distribuzione per Tipo -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Distribuzione per Tipo</h5>
                                    <div class="contenuto">
                                        @foreach($distribuzionePerTipo as $tipo => $count)
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>{{ $tipo }}</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="width: 100px; height: 20px;">
                                                        <div class="progress-bar" role="progressbar"
                                                             style="width: {{ ($count / $size) * 100 }}%"
                                                             aria-valuenow="{{ $count }}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="{{ $size }}">
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-primary">{{ $count }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Distribuzione per Costo -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Distribuzione per Costo</h5>
                                    <div class="contenuto">
                                        @foreach($distribuzionePerCosto as $costo => $count)
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span>Costo {{ $costo }}</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress me-2" style="width: 100px; height: 20px;">
                                                        <div class="progress-bar bg-warning" role="progressbar"
                                                             style="width: {{ ($count / $size) * 100 }}%"
                                                             aria-valuenow="{{ $count }}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="{{ $size }}">
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-warning">{{ $count }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    
    <!-- Messaggi Toast -->
    <div x-data="{ showMessage: false, message: '', type: 'info' }" 
         x-on:show-message.window="showMessage = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => { showMessage = false }, 3000)"
         x-show="showMessage"
         x-transition
         class="fixed bottom-4 right-4 p-4 border rounded-4 border-secondary border-5 shadow-lg text-center fs-3"
         :class="{ 'bg-green-100 text-green-800': type === 'success', 'bg-red-100 text-red-800': type === 'error', 'bg-yellow-100 text-yellow-800': type === 'warning', 'bg-blue-100 text-blue-800': type === 'info' }"
         style="display: none;"
    >
        <p x-text="message"></p>
    </div>
    
    <!-- Inclusione del popup per l'aggiunta di carte -->
    @livewire('add-card-pop-up', [
        'userId' => $user,
        'deckId' => $deck,
        'currentDeckCards' => [],
        'availableCards' => []
    ])
    
    <script>
        document.addEventListener('livewire:initialized', () => {
            // Gestione del form di salvataggio
            Livewire.on('submitSaveForm', (data) => {
                // Rimuoviamo eventuali campi nascosti preesistenti
                document.querySelectorAll('#modifiche input').forEach(el => el.remove());
                
                // Aggiungiamo i campi nascosti al form
                const modificheDiv = document.getElementById('modifiche');
                data = data[0];
                console.log(data);
                Object.entries(data.carte).forEach(([id, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `carte[${id}]`;
                    input.value = value;
                    modificheDiv.appendChild(input);
                });
                
                // Inviamo il form
                document.getElementById('saveDeckForm').submit();
            });
            
            // Gestione dei messaggi toast
            Livewire.on('showMessage', (data) => {
                try{
                    window.dispatchEvent(new CustomEvent('show-message', { 
                        detail: {
                            message: data[0].message,
                            type: data[0].type
                        }
                    }));
                } catch (e) {
                    window.dispatchEvent(new CustomEvent('show-message', { 
                        detail: {
                            message: data.message,
                            type: data.type
                        }
                    }));
                }
                console.log(data);
            });
        });
    </script>
</div>