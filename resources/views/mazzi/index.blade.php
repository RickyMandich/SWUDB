@extends('layouts.app')

@section('title', 'Mazzi')

@section('content')
<div class="container-fluid">
    <!-- Header con pulsanti di azione -->
    @if(Auth::check())
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" onclick="openCreaMazzo()">
                        <i class="fas fa-plus me-1"></i>Crea Mazzo
                    </button>
                    <a href="{{ route('mazzi.import') }}" class="btn btn-outline-primary">
                        <i class="fas fa-file-import me-1"></i>Importa Mazzo
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Sezione Mazzi -->
    @if(count($decks) > 0)
        @php
            // Separiamo i mazzi dell'utente da quelli pubblici
            $userDecks = [];
            $publicDecks = [];

            foreach($decks as $deck) {
                if(Auth::check() && $deck->codUtente == Auth::user()->id) {
                    $userDecks[] = $deck;
                } else {
                    $publicDecks[] = $deck;
                }
            }
        @endphp

        <!-- Mazzi dell'utente -->
        @if(Auth::check() && count($userDecks) > 0)
            <div class="row mb-5">
                <div class="col-12">
                    <div class="d-flex align-items-center mb-3">
                        <h3 class="mb-0 me-3">
                            <i class="fas fa-user me-2 text-primary"></i>I Tuoi Mazzi
                        </h3>
                        <span class="badge bg-primary">{{ count($userDecks) }}</span>
                    </div>
                    <div class="row">
                        @foreach($userDecks as $deck)
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                <div class="card h-100 border-primary shadow-sm">
                                    <div class="card-header bg-primary text-white py-2">
                                        <small><i class="fas fa-crown me-1"></i>Tuo Mazzo</small>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0 flex-grow-1">
                                                {{ $deck->nome }}
                                            </h5>
                                            <span class="badge {{ $deck->public ? 'bg-success' : 'bg-secondary' }} ms-2">
                                                <i class="fas {{ $deck->public ? 'fa-globe' : 'fa-lock' }} me-1"></i>
                                                {{ $deck->public ? 'Pubblico' : 'Privato' }}
                                            </span>
                                        </div>

                                        <div class="mb-2">
                                            <span class="badge bg-info me-2">{{ $deck->getVersionString() }}</span>
                                            @if($deck->updated_at)
                                                <small class="text-muted">
                                                    Aggiornato: {{ $deck->updated_at->format('d/m/Y') }}
                                                </small>
                                            @endif
                                        </div>

                                        <div class="mt-auto">
                                            <div class="d-flex flex-column gap-2">
                                                <!-- Pulsante Visualizza -->
                                                <a href="{{ route('mazzo', ['user' => $deck->utente, 'mazzo' => str_replace(' ', '+', $deck->nome)]) }}"
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Visualizza
                                                </a>

                                                <!-- Pulsanti di azione -->
                                                <div class="d-flex gap-1">
                                                    <!-- Cambia visibilità -->
                                                    <form method="POST" action="{{ route('mazzo.toggle.visibility', ['user' => $deck->utente, 'mazzo' => str_replace(' ', '+', $deck->nome)]) }}"
                                                          class="flex-fill">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-outline-warning btn-sm w-100"
                                                                title="{{ $deck->public ? 'Rendi privato' : 'Rendi pubblico' }}">
                                                            <i class="fas {{ $deck->public ? 'fa-lock' : 'fa-globe' }} me-1"></i>
                                                            Rendi {{ $deck->public ? 'Privato' : 'Pubblico' }}
                                                        </button>
                                                    </form>

                                                    <!-- Elimina -->
                                                    <form method="POST" action="{{ route('mazzo.delete', ['user' => $deck->utente, 'mazzo' => str_replace(' ', '+', $deck->nome)]) }}"
                                                          class="flex-fill" onsubmit="return confirm('Sei sicuro di voler eliminare il mazzo \'{{ $deck->nome }}\'? Questa azione non può essere annullata.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100" title="Elimina mazzo">
                                                            <i class="fas fa-trash me-1"></i>Elimina
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <!-- Mazzi pubblici -->
        @if(count($publicDecks) > 0)
            <div class="row">
                <div class="col-12">
                    <div class="d-flex align-items-center mb-3">
                        <h3 class="mb-0 me-3">
                            <i class="fas fa-globe me-2 text-success"></i>Mazzi Pubblici
                        </h3>
                        <span class="badge bg-success">{{ count($publicDecks) }}</span>
                    </div>
                    <p class="text-muted mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Mazzi condivisi dalla community - Solo visualizzazione
                    </p>
                    <div class="row">
                        @foreach($publicDecks as $deck)
                            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-3">
                                <div class="card h-100 border-success shadow-sm">
                                    <div class="card-header bg-success text-white py-2">
                                        <small><i class="fas fa-globe me-1"></i>Mazzo Pubblico</small>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <div class="mb-2">
                                            <h5 class="card-title mb-1">{{ $deck->nome }}</h5>
                                            <p class="card-text text-muted mb-1">
                                                <i class="fas fa-user me-1"></i>Creato da <strong>{{ $deck->utente }}</strong>
                                            </p>
                                            <div class="mb-1">
                                                <span class="badge bg-info me-2">{{ $deck->getVersionString() }}</span>
                                                @if($deck->updated_at)
                                                    <small class="text-muted">
                                                        Aggiornato: {{ $deck->updated_at->format('d/m/Y') }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-auto">
                                            <a href="{{ route('mazzo', ['user' => $deck->utente, 'mazzo' => str_replace(' ', '+', $deck->nome)]) }}"
                                               class="btn btn-outline-success btn-sm w-100">
                                                <i class="fas fa-eye me-1"></i>Visualizza
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Messaggio quando non ci sono mazzi -->
        <div class="row">
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-cards-blank fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Nessun mazzo disponibile</h4>
                    <p class="text-muted">
                        @if(Auth::check())
                            Non hai ancora creato nessun mazzo. I mazzi vuoti non vengono visualizzati.
                        @else
                            Non ci sono mazzi pubblici disponibili al momento.
                        @endif
                    </p>
                    @if(Auth::check())
                        <button class="btn btn-primary mt-3" onclick="openCreaMazzo()">
                            <i class="fas fa-plus me-1"></i>Crea il tuo primo mazzo
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    let popup;

    document.addEventListener('DOMContentLoaded', function() {
        // Inizializza il popup per la creazione del mazzo
        popup = new Popup({
            title: 'Crea Nuovo Mazzo',
            content: `
                <form action="{{ route('mazzo.create') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Mazzo</label>
                        <input type="text" class="form-control" id="nome" name="nome" required
                               placeholder="Inserisci il nome del mazzo">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="public" name="public">
                        <label class="form-check-label" for="public">
                            Rendi il mazzo pubblico
                        </label>
                        <div class="form-text">
                            I mazzi pubblici sono visibili a tutti gli utenti
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-plus me-1"></i>Crea Mazzo
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="popup.close()">
                            Annulla
                        </button>
                    </div>
                </form>
            `,
            buttons: [
                {
                    text: '×',
                    className: 'btn btn-outline-secondary btn-sm',
                    onClick: function() {
                        popup.close();
                    }
                }
            ]
        });
    });

    function openCreaMazzo() {
        popup.show();
        // Focus sul campo nome quando si apre il popup
        setTimeout(() => {
            document.getElementById('nome').focus();
        }, 100);
    }

    // Gestione messaggi di successo/errore
    @if(session('success'))
        document.addEventListener('DOMContentLoaded', function() {
            // Mostra messaggio di successo
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);

            // Rimuovi automaticamente dopo 5 secondi
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        });
    @endif

    @if(session('error'))
        document.addEventListener('DOMContentLoaded', function() {
            // Mostra messaggio di errore
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);

            // Rimuovi automaticamente dopo 5 secondi
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        });
    @endif

    @if(session('warning'))
        document.addEventListener('DOMContentLoaded', function() {
            // Mostra messaggio di warning
            const alert = document.createElement('div');
            alert.className = 'alert alert-warning alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alert.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>{{ session('warning') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);

            // Rimuovi automaticamente dopo 5 secondi
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        });
    @endif
</script>
@endpush