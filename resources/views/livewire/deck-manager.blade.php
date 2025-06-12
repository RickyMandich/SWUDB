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

                        <!-- Statistiche per Costo -->
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Statistiche</h5>
                                    @if(!empty($statistichePerCosto))
                                        <canvas id="statisticsChart" width="400" height="200"></canvas>
                                    @else
                                        <p class="text-center text-muted">Nessuna unità trovata</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Distribuzione per Aspetto -->
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Distribuzione per Aspetto</h5>
                                    @if(!empty($distribuzionePerAspetto))
                                        <canvas id="aspectChart" width="300" height="300"></canvas>
                                    @else
                                        <p class="text-center text-muted">Nessun aspetto trovato</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Distribuzione per Tipo -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Distribuzione per Tipo</h5>
                                    @if(!empty($distribuzionePerTipo))
                                        <canvas id="typeChart" width="300" height="300"></canvas>
                                    @else
                                        <p class="text-center text-muted">Nessun tipo trovato</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Distribuzione per Costo -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Distribuzione per Costo</h5>
                                    @if(!empty($distribuzionePerCosto))
                                        <canvas id="costChart" width="300" height="300"></canvas>
                                    @else
                                        <p class="text-center text-muted">Nessun costo trovato</p>
                                    @endif
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

            // Inizializzazione dei grafici
            initializeCharts();
        });

        function getAspectColor(aspect) {
            // Mappa degli aspetti ai loro colori caratteristici
            const aspectColors = {
                'Aggression': '#dc3545',     // Rosso
                'Command': '#ffc107',        // Giallo
                'Cunning': '#6f42c1',        // Viola
                'Heroism': '#007bff',        // Blu
                'Vigilance': '#28a745',      // Verde
                'Villainy': '#343a40',       // Nero/Grigio scuro
                'Nessuno': '#6c757d'         // Grigio
            };

            // Per aspetti combinati (es. "Command / Villainy"), usa il colore del primo aspetto
            const primaryAspect = aspect.split(' / ')[0];
            return aspectColors[primaryAspect] || '#6c757d'; // Default grigio
        }

        function initializeCharts() {
            // Grafico Statistiche (Linee)
            const statisticsCanvas = document.getElementById('statisticsChart');
            if (statisticsCanvas) {
                const statisticsCtx = statisticsCanvas.getContext('2d');
                const statisticsData = @json($statistichePerCosto);

                const costs = Object.keys(statisticsData).sort((a, b) => parseInt(a) - parseInt(b));
                const vitaData = costs.map(cost => statisticsData[cost].vita_media);
                const potenzaData = costs.map(cost => statisticsData[cost].potenza_media);

                new Chart(statisticsCtx, {
                    type: 'line',
                    data: {
                        labels: costs.map(cost => `Costo ${cost}`),
                        datasets: [{
                            label: 'Vita Media',
                            data: vitaData,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.1
                        }, {
                            label: 'Potenza Media',
                            data: potenzaData,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const cost = costs[context.dataIndex];
                                        const unita = statisticsData[cost].unita;
                                        return `${context.dataset.label}: ${context.parsed.y} (${unita} unità)`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Valore Medio'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Costo'
                                }
                            }
                        }
                    }
                });
            }

            // Grafico Aspetti (Torta)
            const aspectCanvas = document.getElementById('aspectChart');
            if (aspectCanvas) {
                const aspectCtx = aspectCanvas.getContext('2d');
                const aspectData = @json($distribuzionePerAspetto);
                const totalAspects = @json($totaleCarteStatistiche);

                const aspectLabels = Object.keys(aspectData);
                const aspectValues = Object.values(aspectData);
                const aspectColors = aspectLabels.map(aspect => getAspectColor(aspect));

                new Chart(aspectCtx, {
                    type: 'pie',
                    data: {
                        labels: aspectLabels,
                        datasets: [{
                            data: aspectValues,
                            backgroundColor: aspectColors
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const percentage = ((context.parsed / totalAspects) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Grafico Tipi (Barre Verticali)
            const typeCanvas = document.getElementById('typeChart');
            if (typeCanvas) {
                const typeCtx = typeCanvas.getContext('2d');
                const typeData = @json($distribuzionePerTipo);
                const totalTypes = @json($totaleCarteStatistiche);

                const typeLabels = Object.keys(typeData);
                const typeValues = Object.values(typeData);

                new Chart(typeCtx, {
                    type: 'bar',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            label: 'Carte',
                            data: typeValues,
                            backgroundColor: '#28a745',
                            borderColor: '#1e7e34',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const percentage = ((context.parsed.y / totalTypes) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed.y} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Numero Carte'
                                }
                            }
                        }
                    }
                });
            }

            // Grafico Costi (Barre Verticali)
            const costCanvas = document.getElementById('costChart');
            if (costCanvas) {
                const costCtx = costCanvas.getContext('2d');
                const costData = @json($distribuzionePerCosto);
                const totalCosts = @json($totaleCarteStatistiche);

                const costLabels = Object.keys(costData).sort((a, b) => parseInt(a) - parseInt(b));
                const costValues = costLabels.map(cost => costData[cost]);

                new Chart(costCtx, {
                    type: 'bar',
                    data: {
                        labels: costLabels.map(cost => `Costo ${cost}`),
                        datasets: [{
                            label: 'Carte',
                            data: costValues,
                            backgroundColor: '#ffc107',
                            borderColor: '#e0a800',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const percentage = ((context.parsed.y / totalCosts) * 100).toFixed(1);
                                        return `Costo ${costLabels[context.dataIndex]}: ${context.parsed.y} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Numero Carte'
                                }
                            }
                        }
                    }
                });
            }
        }
    </script>
</div>