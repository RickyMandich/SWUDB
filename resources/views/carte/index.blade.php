@extends('layouts.app')
@section('title', $title)
@section('content')
    <div class="container-fluid">
        <!-- Componente Livewire per i filtri di ricerca -->
        <div class="mb-4" id="search-filter-container">
            @livewire('search-filter', ['mode' => 'page', 'initialNome' => $initialNome ?? ''])
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
                html += `
                    <div class="col-12 col-sm-4 ps-4 pe-4 pt-4 pb-4 card-item">
                        <div class="innerCarta row pr-10">
                            <div class="col-12 col-sm-12 rounded-4 border-primary-subtle bg-secondary-subtle p-3">
                                <a href="${"{{ route('carta', ['espansione' => ':espansione:', 'numero' => ':numero:']) }}".replace(':espansione:', carta.espansione).replace(':numero:', carta.numero)}">
                                    <div class="row">
                                        <div class="col">
                                            <img class="col-12" src="${carta.frontArt}" alt="immagine di ${carta.snippet}">
                                        </div>
                                        <div class="col">
                                            <h5>${carta.snippet}</h5>
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
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            });

            cardsGrid.innerHTML = html;
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