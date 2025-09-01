@extends('layouts.app')

@section('content')
<style>
.markdown-content {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.6;
}

.markdown-content h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #212529;
    border-bottom: 2px solid #dee2e6;
    padding-bottom: 0.5rem;
}

.markdown-content h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: #495057;
}

.markdown-content ul {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.markdown-content li {
    margin-bottom: 0.25rem;
}

.markdown-content del {
    color: #6c757d;
    text-decoration: line-through;
}

.markdown-content code {
    background-color: #495057;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: #e83e8c;
}

.markdown-content ul ul {
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}
</style>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-tachometer-alt me-2"></i>Dashboard Amministratore</h4>
                </div>

                <div class="card-body">
                    <!-- Statistiche principali -->
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-cards-blank fa-2x mb-2"></i>
                                    <h5>{{ number_format($stats['total_cards']) }}</h5>
                                    <small>Carte Totali</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h5>{{ $stats['total_users'] }}</h5>
                                    <small>Utenti Totali</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-shield fa-2x mb-2"></i>
                                    <h5>{{ $stats['admin_users'] }}</h5>
                                    <small>Amministratori</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-layer-group fa-2x mb-2"></i>
                                    <h5>{{ $stats['total_decks'] }}</h5>
                                    <small>Mazzi Reali</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-eye fa-2x mb-2"></i>
                                    <h5>{{ $stats['public_decks'] }}</h5>
                                    <small>Mazzi Pubblici</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-dark text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-archive fa-2x mb-2"></i>
                                    <h5>{{ $stats['total_collections'] }}</h5>
                                    <small>Collezioni</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riga aggiuntiva per nuovi utenti -->
                    <div class="row mb-4">
                        <div class="col-md-2 offset-md-5">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                                    <h5>{{ $stats['recent_users'] }}</h5>
                                    <small>Nuovi Utenti (7gg)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Azioni rapide -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="fas fa-tools me-1"></i>Strumenti Amministrativi</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="{{ route('users.index') }}" class="btn btn-outline-primary">
                                            <i class="fas fa-users me-2"></i>Gestione Utenti
                                        </a>
                                        <a href="{{ route('admin.query') }}" class="btn btn-outline-secondary">
                                            <i class="fas fa-database me-2"></i>Query Database
                                        </a>
                                        <a href="{{ route('admin.logs') }}" class="btn btn-outline-warning">
                                            <i class="fas fa-file-alt me-2"></i>Gestione Logs
                                        </a>
                                        <a href="{{ route('carte.update') }}" class="btn btn-outline-success">
                                            <i class="fas fa-sync-alt me-2"></i>Aggiorna Database Carte
                                        </a>
                                        <a href="{{ route('mazzi') }}" class="btn btn-outline-info">
                                            <i class="fas fa-layer-group me-2"></i>Mazzi Pubblici
                                        </a>
                                        <a href="{{ route('admin.errors') }}" class="btn btn-outline-danger">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Gestione Errori
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="fas fa-chart-bar me-1"></i>Statistiche Dettagliate</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Rapporto Admin/Utenti:</strong></td>
                                            <td>{{ round(($stats['admin_users'] / max($stats['total_users'], 1)) * 100, 1) }}%</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Rapporto Mazzi Pubblici:</strong></td>
                                            <td>{{ round(($stats['public_decks'] / max($stats['total_decks'], 1)) * 100, 1) }}%</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Utenti con Collezione:</strong></td>
                                            <td>{{ round(($stats['total_collections'] / max($stats['total_users'], 1)) * 100, 1) }}%</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Media Mazzi per Utente:</strong></td>
                                            <td>{{ round($stats['total_decks'] / max($stats['total_users'], 1), 1) }} (escluse collezioni)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Crescita Utenti (7gg):</strong></td>
                                            <td>{{ $stats['recent_users'] }} nuovi utenti</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Link rapidi -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6><i class="fas fa-external-link-alt me-1"></i>Link Rapidi</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <a href="{{ route('carte') }}" class="btn btn-outline-primary w-100 mb-2">
                                                <i class="fas fa-cards-blank me-1"></i>Visualizza Carte
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="{{ route('compare', ['espansione1' => 'SOR', 'numero1' => '001', 'espansione2' => 'SOR', 'numero2' => '002']) }}" class="btn btn-outline-secondary w-100 mb-2">
                                                <i class="fas fa-balance-scale me-1"></i>Test Compare
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="{{ route('documentazione') }}" class="btn btn-outline-info w-100 mb-2">
                                                <i class="fas fa-book me-1"></i>Documentazione
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="{{ route('mazzi.import') }}" class="btn btn-outline-success w-100 mb-2">
                                                <i class="fas fa-upload me-1"></i>Import Mazzi
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
