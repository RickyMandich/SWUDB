@extends('layouts.app')
@section('title', 'La mia Collezione')
@section('content')
    <div class="container-fluid">
        <!-- Header della collezione -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h1 class="card-title mb-2">
                            <i class="fas fa-folder-open me-2"></i>
                            La mia Collezione
                        </h1>
                        <p class="card-text mb-0">
                            <strong>{{ $totalCards }}</strong> carte totali nella collezione
                        </p>
                        @if(isset($debugInfo) && Auth::check() && Auth::user()->admin)
                        <div class="mt-2">
                            <button type="button"
                                    class="btn btn-outline-info btn-sm"
                                    id="toggle-debug"
                                    onclick="toggleDebugInfo()">
                                <i class="fas fa-bug me-1"></i>
                                <span id="debug-toggle-text">Mostra Debug Info</span>
                            </button>
                        </div>
                        @endif
                        @if(isset($debugInfo) && Auth::check() && Auth::user()->admin)
                        <div class="alert alert-info mt-2" id="debug-info" style="display: none;">
                            <h6>🔍 DEBUG INFO (Solo Admin)</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Database:</strong> {{ $debugInfo['total_cards_db'] }} carte totali</p>
                                    <p class="mb-1"><strong>Visualizzate:</strong> {{ $debugInfo['cards_displayed'] }} carte</p>
                                    <p class="mb-1"><strong>In collezione:</strong> {{ $debugInfo['cards_in_collezione'] }} carte</p>
                                    <p class="mb-1"><strong>Ordinamento:</strong> {{ $debugInfo['sort_applied'] }}</p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Valori massimi DB:</strong></p>
                                    <ul class="mb-1">
                                        <li>Costo: {{ $debugInfo['max_values']['costo'] }}</li>
                                        <li>Potenza: {{ $debugInfo['max_values']['potenza'] ?? 'N/A' }}</li>
                                        <li>Vita: {{ $debugInfo['max_values']['vita'] ?? 'N/A' }}</li>
                                    </ul>
                                    <p class="mb-1"><strong>Carte con valori alti (>10):</strong> {{ $debugInfo['high_value_cards_count'] }}</p>
                                </div>
                            </div>
                            @if(count($debugInfo['sample_high_value_cards']) > 0)
                            <hr>
                            <p class="mb-1"><strong>Esempi carte con valori alti:</strong></p>
                            <ul class="mb-0">
                                @foreach($debugInfo['sample_high_value_cards'] as $card)
                                <li><small>{{ $card }}</small></li>
                                @endforeach
                            </ul>
                            @endif
                            <hr>
                            <p class="mb-0"><small><strong>Filtri:</strong> {{ $debugInfo['livewire_status'] }}</small></p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Componente Livewire per i filtri di ricerca -->
        <div class="mb-4">
            @livewire('search-filter', ['mode' => 'collezione'])
        </div>

        <!-- Risultati di ricerca -->
        <div class="mb-3">
            <div class="alert alert-info d-flex align-items-center" id="results-counter">
                <i class="fas fa-info-circle me-2"></i>
                <span id="counter-text">Caricamento carte...</span>
            </div>
        </div>

        <!-- Contenitore per i risultati -->
        <div id="cards-container">
            <div class="row" id="cards-grid">
                <!-- Le carte verranno caricate dinamicamente da Livewire -->
                <div class="col-12 text-center py-5" id="initial-message">
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        Caricamento carte in corso...
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Token CSRF per le richieste AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Dati della collezione per il controllo delle copie
        const collezioneData = {
            @foreach($collezione as $carta)
                '{{ $carta->espansione }}-{{ $carta->numero }}': {{ $carta->copie }},
            @endforeach
        };
        
        // Gestione dei pulsanti + e -
        document.addEventListener('click', function(e) {
            if (e.target.closest('.increase-btn')) {
                const btn = e.target.closest('.increase-btn');
                const espansione = btn.dataset.espansione;
                const numero = btn.dataset.numero;
                const input = document.querySelector(`input[data-espansione="${espansione}"][data-numero="${numero}"]`);
                const currentValue = parseInt(input.value) || 0;
                input.value = currentValue + 1;
                updateCollezione(espansione, numero, input.value);
            }
            
            if (e.target.closest('.decrease-btn')) {
                const btn = e.target.closest('.decrease-btn');
                const espansione = btn.dataset.espansione;
                const numero = btn.dataset.numero;
                const input = document.querySelector(`input[data-espansione="${espansione}"][data-numero="${numero}"]`);
                const currentValue = parseInt(input.value) || 0;
                if (currentValue > 0) {
                    input.value = currentValue - 1;
                    updateCollezione(espansione, numero, input.value);
                }
            }
        });
        
        // Gestione input diretti
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('copie-input')) {
                const input = e.target;
                const espansione = input.dataset.espansione;
                const numero = input.dataset.numero;
                let value = parseInt(input.value) || 0;
                
                // Assicurati che il valore sia valido
                if (value < 0) value = 0;
                if (value > 999) value = 999;
                input.value = value;
                
                updateCollezione(espansione, numero, value);
            }
        });
        
        // Funzione per aggiornare la collezione
        function updateCollezione(espansione, numero, copie) {
            fetch('{{ route("collezione.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    espansione: espansione,
                    numero: numero,
                    copie: copie
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Aggiorna i dati locali della collezione
                    const cardId = espansione + '-' + numero;
                    collezioneData[cardId] = copie;

                    // Aggiorna lo stato dei pulsanti
                    updateButtonStates(espansione, numero, copie);
                    // Aggiorna il contatore totale
                    updateTotalCounter();
                } else {
                    console.error('Errore nell\'aggiornamento della collezione');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
            });
        }
        
        // Aggiorna lo stato dei pulsanti
        function updateButtonStates(espansione, numero, copie) {
            const decreaseBtn = document.querySelector(`.decrease-btn[data-espansione="${espansione}"][data-numero="${numero}"]`);
            if (decreaseBtn) {
                decreaseBtn.disabled = copie <= 0;
            }
        }
        
        // Aggiorna il contatore totale
        function updateTotalCounter() {
            let total = 0;
            document.querySelectorAll('.copie-input').forEach(input => {
                total += parseInt(input.value) || 0;
            });

            // Aggiorna il testo nell'header
            const headerText = document.querySelector('.card-text');
            if (headerText) {
                headerText.innerHTML = `<strong>${total}</strong> carte totali nella collezione`;
            }
        }

        // Toggle per le info di debug
        function toggleDebugInfo() {
            const debugInfo = document.getElementById('debug-info');
            const toggleText = document.getElementById('debug-toggle-text');
            const toggleBtn = document.getElementById('toggle-debug');

            if (debugInfo.style.display === 'none') {
                debugInfo.style.display = 'block';
                toggleText.textContent = 'Nascondi Debug Info';
                toggleBtn.classList.remove('btn-outline-info');
                toggleBtn.classList.add('btn-info');
            } else {
                debugInfo.style.display = 'none';
                toggleText.textContent = 'Mostra Debug Info';
                toggleBtn.classList.remove('btn-info');
                toggleBtn.classList.add('btn-outline-info');
            }
        }
        
        // Ascolta l'evento di filtro delle carte
        document.addEventListener('livewire:init', () => {
            Livewire.on('cardsFiltered', (cards) => {
                updateCardsDisplay(cards[0]);
            });
        });

        function updateCardsDisplay(cards) {
            const cardsGrid = document.getElementById('cards-grid');
            const counterText = document.getElementById('counter-text');
            const initialMessage = document.getElementById('initial-message');

            // Rimuovi il messaggio iniziale se presente
            if (initialMessage) {
                initialMessage.remove();
            }

            if (counterText) {
                counterText.innerHTML = `Trovate <strong>${cards ? cards.length : 0}</strong> carte`;
            }

            if (!cards || cards.length === 0) {
                cardsGrid.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <div class="alert alert-warning">
                            <i class="fas fa-search me-2"></i>
                            Nessuna carta trovata con i filtri selezionati.
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            cards.forEach(carta => {
                // Cerca se la carta è nella collezione
                const copieAttuali = getCardCopiesInCollection(carta.espansione, carta.numero);

                html += `
                    <div class="col-12 col-sm-4 ps-4 pe-4 pt-4 pb-4 card-item">
                        <div class="innerCarta row pr-10">
                            <div class="col-12 col-sm-12 rounded-4 border-primary-subtle bg-secondary-subtle p-3">
                                <div class="row">
                                    <div class="col">
                                        <a href="${"{{ route('carta', ['espansione' => ':espansione:', 'numero' => ':numero:']) }}".replace(':espansione:', carta.espansione).replace(':numero:', carta.numero)}" target="_blank">
                                            <img class="col-12" src="${carta.frontArt}" alt="immagine di ${carta.snippet}">
                                        </a>
                                    </div>
                                    <div class="col">
                                        <a href="${"{{ route('carta', ['espansione' => ':espansione:', 'numero' => ':numero:']) }}".replace(':espansione:', carta.espansione).replace(':numero:', carta.numero)}" target="_blank" class="text-decoration-none">
                                            <h5>${carta.snippet}</h5>
                                        </a>
                                        ${carta.tratti} <br>
                                        <div class="row">
                                            <span class="col-9">costo:</span>
                                            <span class="m-auto text-warning align-self-end col-3">${carta.costo}</span> <br>
                                        </div>
                                        <div class="row">
                                            <span class="col-9">potenza:</span>
                                            <span class="m-auto text-danger align-self-end col-3">${carta.potenza || '-'}</span> <br>
                                        </div>
                                        <div class="row">
                                            <span class="col-9">vita:</span>
                                            <span class="m-auto text-primary align-self-end col-3">${carta.vita || '-'}</span> <br>
                                        </div>
                                        <div class="row text-center">
                                            <span class="col-12 ${carta.rarita.toLowerCase().split(' ').join('')}">${carta.rarita}</span>
                                        </div>

                                        <!-- Controlli per la collezione -->
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <button type="button"
                                                            class="btn btn-danger btn-sm me-2 decrease-btn"
                                                            data-espansione="${carta.espansione}"
                                                            data-numero="${carta.numero}"
                                                            ${copieAttuali <= 0 ? 'disabled' : ''}>
                                                        <i class="fas fa-minus"></i>
                                                    </button>

                                                    <input type="number"
                                                           class="form-control text-center mx-2 copie-input"
                                                           style="width: 80px;"
                                                           value="${copieAttuali}"
                                                           min="0"
                                                           max="999"
                                                           data-espansione="${carta.espansione}"
                                                           data-numero="${carta.numero}">

                                                    <button type="button"
                                                            class="btn btn-success btn-sm ms-2 increase-btn"
                                                            data-espansione="${carta.espansione}"
                                                            data-numero="${carta.numero}">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            cardsGrid.innerHTML = html;
        }

        // Funzione per ottenere le copie di una carta nella collezione
        function getCardCopiesInCollection(espansione, numero) {
            const cardId = espansione + '-' + numero;
            return collezioneData[cardId] || 0;
        }
    </script>
    @endpush
@endsection

@section("php")
<?php
function toCssClass($class){
    return str_replace(" ", "-", $class);
}
?>
@endsection
