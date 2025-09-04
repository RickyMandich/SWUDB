@extends('layouts.app')

@section('title', 'Gestione Mazzi - Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i>Gestione Mazzi - Amministrazione
                    </h4>
                    <div>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Torna alla Dashboard
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Statistiche rapide -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-layer-group fa-2x mb-2"></i>
                                    <h5>{{ $decks->total() }}</h5>
                                    <small>Mazzi Totali</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-globe fa-2x mb-2"></i>
                                    <h5>{{ $decks->where('public', true)->where('nome', '!=', 'collezione')->count() }}</h5>
                                    <small>Mazzi Pubblici</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-archive fa-2x mb-2"></i>
                                    <h5>{{ $decks->where('nome', 'collezione')->count() }}</h5>
                                    <small>Collezioni</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-lock fa-2x mb-2"></i>
                                    <h5>{{ $decks->where('public', false)->where('nome', '!=', 'collezione')->count() }}</h5>
                                    <small>Mazzi Privati</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabella mazzi -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Utente</th>
                                    <th>Nome Mazzo</th>
                                    <th>Tipo</th>
                                    <th>Visibilità</th>
                                    <th>Dimensione</th>
                                    <th>Versione</th>
                                    <th>Ultimo Aggiornamento</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($decks as $deck)
                                <tr>
                                    <td>{{ $deck->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($deck->user->admin)
                                                <i class="fas fa-crown text-warning me-2" title="Amministratore"></i>
                                            @else
                                                <i class="fas fa-user text-muted me-2" title="Utente normale"></i>
                                            @endif
                                            <strong>{{ $deck->user->name }}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ $deck->nome }}</strong>
                                    </td>
                                    <td>
                                        @if($deck->nome === 'collezione')
                                            <span class="badge bg-info">
                                                <i class="fas fa-archive me-1"></i>Collezione
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-layer-group me-1"></i>Mazzo
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($deck->nome === 'collezione')
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-user me-1"></i>Personale
                                            </span>
                                        @else
                                            <span class="badge {{ $deck->public ? 'bg-success' : 'bg-warning' }}">
                                                <i class="fas {{ $deck->public ? 'fa-globe' : 'fa-lock' }} me-1"></i>
                                                {{ $deck->public ? 'Pubblico' : 'Privato' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $deck->total_cards }} carte</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $deck->getVersionString() }}</span>
                                    </td>
                                    <td>
                                        @if($deck->updated_at)
                                            <small>{{ $deck->updated_at->format('d/m/Y H:i') }}</small>
                                        @else
                                            <small class="text-muted">N/A</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            @if($deck->nome === 'collezione')
                                                <a href="{{ route('collezione', ['user' => $deck->user->name]) }}"
                                                   class="btn btn-outline-primary btn-sm"
                                                   title="Visualizza collezione">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('mazzo', ['user' => $deck->user->name, 'mazzo' => str_replace(' ', '+', $deck->nome)]) }}"
                                                   class="btn btn-outline-primary btn-sm"
                                                   title="Visualizza mazzo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginazione -->
                    @if($decks->hasPages())
                        <div class="d-flex justify-content-center mt-4">
                            {{ $decks->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
