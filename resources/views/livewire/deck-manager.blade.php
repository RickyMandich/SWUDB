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
                        <!-- Tratti -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Tratti</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tratto</th>
                                                    <th class="text-end">Carte</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($trattiPrincipali as $tratto => $count)
                                                    <tr>
                                                        <td>{{ $tratto }}</td>
                                                        <td class="text-end">{{ $count }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="2" class="text-center text-muted">Nessun tratto trovato</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vita e Potenza per Costo -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Vita</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Costo</th>
                                                    <th class="text-end">Potenza Media</th>
                                                    <th class="text-end">Vita Media</th>
                                                    <th class="text-end">Unità</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($statistichePerCosto as $costo => $stats)
                                                    <tr>
                                                        <td>{{ $costo }}</td>
                                                        <td class="text-end">{{ $stats['potenza_media'] }}</td>
                                                        <td class="text-end">{{ $stats['vita_media'] }}</td>
                                                        <td class="text-end">{{ $stats['unita'] }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">Nessuna unità trovata</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
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
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th class="text-end">Carte</th>
                                                    <th class="text-end">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($distribuzionePerTipo as $tipo => $count)
                                                    <tr>
                                                        <td>{{ $tipo }}</td>
                                                        <td class="text-end">{{ $count }}</td>
                                                        <td class="text-end">{{ $totaleCarteStatistiche > 0 ? round(($count / $totaleCarteStatistiche) * 100, 1) : 0 }}%</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">Nessun tipo trovato</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Distribuzione per Costo -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Distribuzione per Costo</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Costo</th>
                                                    <th class="text-end">Carte</th>
                                                    <th class="text-end">%</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($distribuzionePerCosto as $costo => $count)
                                                    <tr>
                                                        <td>{{ $costo }}</td>
                                                        <td class="text-end">{{ $count }}</td>
                                                        <td class="text-end">{{ $totaleCarteStatistiche > 0 ? round(($count / $totaleCarteStatistiche) * 100, 1) : 0 }}%</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted">Nessun costo trovato</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
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