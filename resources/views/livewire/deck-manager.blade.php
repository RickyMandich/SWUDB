<div>
    <div class="header text-center mb-4">
        @if ($modalitaRinomina && $proprietario)
            <!-- Modalità rinominazione -->
            <div class="rename-section">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-8 col-lg-6">
                        <div class="input-group mb-3">
                            <input type="text"
                                   class="form-control"
                                   wire:model="nuovoNome"
                                   placeholder="Nuovo nome del mazzo"
                                   maxlength="500"
                                   wire:keydown.enter="salvaNuovoNome"
                                   wire:keydown.escape="annullaRinomina">
                            <button class="btn btn-success"
                                    type="button"
                                    wire:click="salvaNuovoNome">
                                <i class="fas fa-check me-1"></i>Salva
                            </button>
                            <button class="btn btn-secondary"
                                    type="button"
                                    wire:click="annullaRinomina">
                                <i class="fas fa-times me-1"></i>Annulla
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Visualizzazione normale -->
            <div class="d-flex justify-content-center align-items-center mb-2">
                <h1 class="me-3">{{ $nome }}</h1>
                @if ($proprietario && $nome !== 'Collezione')
                    <button class="btn btn-outline-primary btn-sm"
                            wire:click="attivaRinomina"
                            title="Rinomina mazzo">
                        <i class="fas fa-edit"></i>
                    </button>
                @endif
            </div>
        @endif

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
        <div class="version-info mb-2">
            <span class="badge bg-secondary">{{ $deckObject->getVersionString() }}</span>
            @if($deckObject->updated_at)
                <small class="text-muted ms-2">
                    Ultima modifica: {{ $deckObject->updated_at->format('d/m/Y H:i') }}
                </small>
            @endif
        </div>

        <!-- Pulsanti di esportazione -->
        <div class="export-buttons mt-3">
            <div class="btn-group" role="group" aria-label="Esporta mazzo">
                <a href="{{ route('mazzo.export.txt', ['user' => $user, 'mazzo' => str_replace(' ', '+', $nome)]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-file-alt me-1"></i>Esporta TXT
                </a>
                <a href="{{ route('mazzo.export.json', ['user' => $user, 'mazzo' => str_replace(' ', '+', $nome)]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-file-code me-1"></i>Esporta JSON
                </a>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <!-- Colonna sinistra -->
            <div class="col-12 col-lg-6">
                <div class="mazzo">
                    <h3 class="mb-3">Mazzo</h3>
                    <div class="contenuto">
                        @foreach($mazzo as $id => $carta)
                            <span class="d-flex mt-4">
                                {{ $carta['copie'] ?? 1 }}x
                                @if ($proprietario)
                                    <button type="button" wire:click="aumentaCopia('{{ $id }}')" class="btn btn-success rounded-0 rounded-start-1 border-end-0 py-1 px-2 lh-1">+</button>
                                    <button type="button" wire:click="diminuisciCopia('{{ $id }}')" class="btn btn-danger rounded-0 rounded-end-1 border-start-0 py-1 px-2 lh-1">-</button>
                                @endif
                                @if(isset($carta['espansione']) && isset($carta['numero']) && !empty($carta['espansione']) && $carta['numero'] > 0)
                                    <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}" target="_blank">
                                        {{ $carta['snippet'] ?? '' }}
                                    </a>
                                @else
                                    <span>{{ $carta['snippet'] ?? 'Carta non disponibile' }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <!-- Colonna destra -->
            @if ($proprietario)
                <div class="col-12 col-lg-6">
                    <!-- Sezione aggiunta carte sempre visibile -->
                    @livewire('add-card-section', [
                        'userId' => $user,
                        'deckId' => $deck,
                        'currentDeckCards' => collect($mazzo)->mapWithKeys(function($card, $key) {
                            return [$key => $card['copie']];
                        })->toArray(),
                        'availableCards' => $cards
                    ])

                    <div class="aggiunte mb-4">
                        <h3>Carte aggiunte</h3>
                        <div class="mb-4 contenuto">
                            @foreach($aggiunte as $id => $carta)
                                <span class="d-flex mt-4">
                                    {{ $carta['copie'] ?? 1 }}x
                                    @if ($proprietario)
                                        <button type="button" wire:click="diminuisciCopia('{{ $id }}')" class="btn btn-danger rounded-1 border-0 py-1 px-2 lh-1">-</button>
                                    @endif
                                    @if(isset($carta['espansione']) && isset($carta['numero']) && !empty($carta['espansione']) && $carta['numero'] > 0)
                                        <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}" target="_blank">
                                            {{ $carta['snippet'] ?? '' }}
                                        </a>
                                    @else
                                        <span>{{ $carta['snippet'] ?? 'Carta non disponibile' }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="rimosse">
                        <h3>Carte rimosse</h3>
                        <div class="mb-4 contenuto">
                            @foreach($rimosse as $id => $carta)
                                <span class="d-flex mt-4">
                                    {{ $carta['copie'] ?? 1 }}x
                                    <button type="button" wire:click="aumentaCopia('{{ $id }}')" class="btn btn-success rounded-1 border-0 py-1 px-2 lh-1">+</button>
                                    @if(isset($carta['espansione']) && isset($carta['numero']) && !empty($carta['espansione']) && $carta['numero'] > 0)
                                        <a href="{{ route('carta', ["espansione" => $carta["espansione"], "numero" => $carta["numero"]]) }}" target="_blank">
                                            {{ $carta['snippet'] ?? '' }}
                                        </a>
                                    @else
                                        <span>{{ $carta['snippet'] ?? 'Carta non disponibile' }}</span>
                                    @endif
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
                    <!-- Prima riga: Tratti -->
                    <div class="row">
                        <!-- Tratti -->
                        <div class="col-12 col-lg-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Tratti</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Tratti Divisi</th>
                                                    <th class="text-end">Carte</th>
                                                    <th>
                                                        @for($i=0;$i<10;$i++)
                                                            &nbsp;
                                                        @endfor
                                                    </th>
                                                    <th>Tratti Completi</th>
                                                    <th class="text-end">Carte</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    // Convertiamo gli array in collezioni per facilitare l'iterazione
                                                    $trattiDivisi = collect($trattiPrincipali)->keys()->toArray();
                                                    $trattiCompleti = collect($trattiCompleti)->keys()->toArray();

                                                    // Determiniamo il numero massimo di righe
                                                    $maxRighe = max(count($trattiDivisi), count($trattiCompleti));
                                                @endphp

                                                @if($maxRighe > 0)
                                                    @for($i = 0; $i < $maxRighe; $i++)
                                                        <tr>
                                                            <!-- Tratti Divisi -->
                                                            <td>
                                                                @if(isset($trattiDivisi[$i]))
                                                                    {{ $trattiDivisi[$i] }}
                                                                @endif
                                                            </td>
                                                            <td class="text-end">
                                                                @if(isset($trattiDivisi[$i]))
                                                                    {{ $trattiPrincipali[$trattiDivisi[$i]] }}
                                                                @endif
                                                            </td>
                                                            <td></td>
                                                            <!-- Tratti Completi -->
                                                            <td>
                                                                @if(isset($trattiCompleti[$i]))
                                                                    {{ $trattiCompleti[$i] }}
                                                                @endif
                                                            </td>
                                                            <td class="text-end">
                                                                @if(isset($trattiCompleti[$i]))
                                                                    {{ $this->trattiCompleti[$trattiCompleti[$i]] }}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endfor
                                                @else
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">Nessun tratto trovato</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Statistiche per Costo -->
                        <div class="col-12 col-lg-6 mb-3">
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

                    <!-- Terza riga: Distribuzioni -->
                    <div class="row mt-3">
                        <div class="col-12 col-md-4">
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
                        <div class="col-12 col-md-4">
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
                        <div class="col-12 col-md-4">
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
         class="fixed bottom-4 right-4 p-4 border rounded-4 border-secondary border-5 shadow-lg text-center fs-3 position-absolute top-50 start-50 translate-middle bg-secondary-subtle"
         :class="{ 'bg-green-100 text-green-800': type === 'success', 'bg-red-100 text-red-800': type === 'error', 'bg-yellow-100 text-yellow-800': type === 'warning', 'bg-blue-100 text-blue-800': type === 'info' }"
         style="display: none;"
    >
        <p x-text="message"></p>
    </div>
    

    
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

            // Gestione del form di rinominazione
            Livewire.on('submitRenameForm', (data) => {
                // Crea un form temporaneo per la rinominazione
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("mazzo.rename", ["user" => $user, "mazzo" => $deck]) }}';

                // Aggiungi il token CSRF
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);

                // Aggiungi il metodo PATCH
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'PATCH';
                form.appendChild(methodInput);

                // Aggiungi il nuovo nome
                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = 'nuovo_nome';
                nameInput.value = data[0].nuovo_nome;
                form.appendChild(nameInput);

                // Aggiungi il form al body e invialo
                document.body.appendChild(form);
                form.submit();
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

            // Listener per aggiornare i grafici quando cambiano le statistiche
            Livewire.on('refreshCharts', () => {
                // Distruggi i grafici esistenti se esistono
                if (window.statisticsChart) window.statisticsChart.destroy();
                if (window.aspectChart) window.aspectChart.destroy();
                if (window.typeChart) window.typeChart.destroy();
                if (window.costChart) window.costChart.destroy();

                // Ricrea i grafici con i nuovi dati
                initializeCharts();
            });
        });

        function hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? {
                r: parseInt(result[1], 16),
                g: parseInt(result[2], 16),
                b: parseInt(result[3], 16)
            } : null;
        }

        function rgbToHex(r, g, b) {
            return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        }

        function blendColors(color1, color2, ratio = 0.5) {
            const rgb1 = hexToRgb(color1);
            const rgb2 = hexToRgb(color2);

            if (!rgb1 || !rgb2) return color1;

            const r = Math.round(rgb1.r * (1 - ratio) + rgb2.r * ratio);
            const g = Math.round(rgb1.g * (1 - ratio) + rgb2.g * ratio);
            const b = Math.round(rgb1.b * (1 - ratio) + rgb2.b * ratio);

            return rgbToHex(r, g, b);
        }

        function getAspectColor(aspect) {
            // Mappa degli aspetti ai loro colori caratteristici
            const aspectColors = {
                // Aspetti aggiuntivi che potrebbero essere nel database
                'Nero': '#000000',           // Nero
                'Bianco': '#f8f9fa',         // Bianco
                'Rosso': '#dc3545',          // Rosso
                'Blu': '#007bff',            // Blu
                'Verde': '#28a745',          // Verde
                'Giallo': '#ffc107',         // Giallo

                // Fallback
                'nessun aspetto': '#6c757d', // Grigio per carte senza aspetto
                'Nessuno': '#6c757d',        // Grigio (compatibilità)
                '': '#6c757d',               // Grigio per valori vuoti
                'null': '#6c757d'            // Grigio per null
            };

            // Controlla se è un aspetto combinato
            if (aspect.includes(' / ')) {
                const [primaryAspect, secondaryAspect] = aspect.split(' / ');
                const primaryColor = aspectColors[primaryAspect];
                const secondaryColor = aspectColors[secondaryAspect];

                // Se entrambi gli aspetti sono mappati e sono diversi
                if (primaryColor && secondaryColor && primaryAspect !== secondaryAspect) {
                    // Crea una miscela tra i due colori
                    // Se il secondario è Bianco, schiarisci il primario
                    // Se il secondario è Nero, scurisci il primario
                    let blendRatio = 0.5; // 30% del colore secondario

                    if (secondaryAspect === 'Bianco') {
                        // Schiarisci il colore primario mescolandolo con il bianco
                        return blendColors(primaryColor, '#ffffff', blendRatio);
                    } else if (secondaryAspect === 'Nero') {
                        // Scurisci il colore primario mescolandolo con il nero
                        return blendColors(primaryColor, '#000000', blendRatio);
                    } else {
                        // Per altri aspetti secondari, miscela normalmente
                        return blendColors(primaryColor, secondaryColor, blendRatio);
                    }
                }

                // Se non riesce a miscelare, usa il colore primario
                return aspectColors[primaryAspect] || '#6c757d';
            }

            // Per aspetti singoli, usa il colore diretto
            const color = aspectColors[aspect];

            // Debug migliorato
            if (!color) {
                console.warn(`Aspetto non mappato: "${aspect}"`);
                return '#6c757d'; // Default grigio
            }

            return color;
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

                window.statisticsChart = new Chart(statisticsCtx, {
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

                // Debug: mostra gli aspetti trovati
                console.log('Aspetti trovati:', aspectLabels);

                const aspectColors = aspectLabels.map(aspect => {
                    const color = getAspectColor(aspect);
                    console.log(`Aspetto: ${aspect} -> Colore: ${color}`);
                    return color;
                });

                window.aspectChart = new Chart(aspectCtx, {
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

                window.typeChart = new Chart(typeCtx, {
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

                window.costChart = new Chart(costCtx, {
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
