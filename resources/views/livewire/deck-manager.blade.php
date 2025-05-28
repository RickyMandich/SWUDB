<div>
    <div class="header text-center mb-4">
        <h1>
            {{ $nome }} <button wire:click="openAddCardPopup" class="btn btn-primary"> + </button>
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
                                <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}">
                                    {{ $carta['snippet'] }}
                                </a>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- Colonna destra -->
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
                                <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}">
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
                                @if ($proprietario)
                                    <button type="button" wire:click="aumentaCopia('{{ $id }}')" class="btn btn-success rounded-1 border-0 py-1 px-2 lh-1">+</button>
                                @endif
                                <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}">
                                    {{ $carta['snippet'] }}
                                </a>
                            </span>
                        @endforeach
                    </div>
                </div>
                @if ($proprietario)
                    <div class="form">
                        <form id="saveDeckForm" method="POST" action="{{ route('mazzo.save', ['user' => $user, 'mazzo' => $deck]) }}">
                            @csrf
                            <button type="button" wire:click="saveDeck" class="btn btn-success">Save</button>
                            
                            <div id="modifiche">
                                <!-- I campi nascosti verranno generati dinamicamente dal JavaScript -->
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Messaggi Toast -->
    <div x-data="{ showMessage: false, message: '', type: 'info' }" 
         x-on:show-message.window="showMessage = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => { showMessage = false }, 3000)"
         x-show="showMessage"
         x-transition
         class="fixed bottom-4 right-4 p-4 rounded shadow-lg"
         :class="{ 'bg-green-100 text-green-800': type === 'success', 'bg-red-100 text-red-800': type === 'error', 'bg-yellow-100 text-yellow-800': type === 'warning', 'bg-blue-100 text-blue-800': type === 'info' }"
         style="display: none;"
    >
        <p x-text="message"></p>
    </div>
    
    <!-- Inclusione del popup per l'aggiunta di carte -->
    <livewire:add-card-popup
        :userId="$user"
        :deckId="$deck"
    />
    
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
                window.dispatchEvent(new CustomEvent('show-message', { 
                    detail: {
                        message: data.message,
                        type: data.type
                    }
                }));
            });
        });
    </script>
</div>