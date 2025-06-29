@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-users me-2"></i>Gestione Utenti</h4>
                        <span class="badge bg-primary">{{ $totalUsers }} utenti totali</span>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Statistiche rapide -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-user-shield fa-2x mb-2"></i>
                                    <h5>{{ $adminUsers }}</h5>
                                    <small>Amministratori</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-user fa-2x mb-2"></i>
                                    <h5>{{ $regularUsers }}</h5>
                                    <small>Utenti Normali</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-envelope-circle-check fa-2x mb-2"></i>
                                    <h5>{{ $verifiedUsers }}</h5>
                                    <small>Email Verificate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                    <h5>{{ $unverifiedUsers }}</h5>
                                    <small>Email Non Verificate</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabella utenti -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Stato</th>
                                    <th>Email Verificata</th>
                                    <th>Registrato</th>
                                    <th>Ultimo Accesso</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            @if($user->admin)
                                                <i class="fas fa-crown text-warning me-2" title="Amministratore"></i>
                                            @else
                                                <i class="fas fa-user text-muted me-2" title="Utente normale"></i>
                                            @endif
                                            <strong>{{ $user->name }}</strong>
                                            @if($user->id === Auth::id())
                                                <span class="badge bg-secondary ms-2">Tu</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @if($user->admin)
                                            <span class="badge bg-success">
                                                <i class="fas fa-shield-alt me-1"></i>Admin
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-user me-1"></i>Utente
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->isEmailVerified())
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Verificata
                                            </span>
                                        @else
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Non Verificata
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $user->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $user->updated_at->format('d/m/Y H:i') }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <!-- Visualizza dettagli -->
                                            <a href="{{ route('users.show', $user->id) }}" 
                                               class="btn btn-outline-primary btn-sm" 
                                               title="Visualizza dettagli">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <!-- Toggle admin status -->
                                            @if($user->id !== Auth::id())
                                                <form method="POST" action="{{ route('users.toggle-admin', $user->id) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="btn btn-outline-{{ $user->admin ? 'warning' : 'success' }} btn-sm"
                                                            title="{{ $user->admin ? 'Rimuovi privilegi admin' : 'Promuovi ad admin' }}"
                                                            onclick="return confirm('Sei sicuro di voler {{ $user->admin ? 'rimuovere i privilegi di amministratore da' : 'promuovere ad amministratore' }} {{ $user->name }}?')">
                                                        <i class="fas fa-{{ $user->admin ? 'user-minus' : 'user-plus' }}"></i>
                                                    </button>
                                                </form>

                                                <!-- Toggle email verification -->
                                                <form method="POST" action="{{ route('users.toggle-email-verification', $user->id) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="btn btn-outline-{{ $user->isEmailVerified() ? 'warning' : 'info' }} btn-sm"
                                                            title="{{ $user->isEmailVerified() ? 'Rimuovi verifica email' : 'Marca email come verificata' }}"
                                                            onclick="return confirm('Sei sicuro di voler {{ $user->isEmailVerified() ? 'rimuovere la verifica email per' : 'marcare come verificata l\'email di' }} {{ $user->name }}?')">
                                                        <i class="fas fa-{{ $user->isEmailVerified() ? 'envelope-open-text' : 'envelope-circle-check' }}"></i>
                                                    </button>
                                                </form>

                                                <!-- Resend verification email (only if not verified) -->
                                                @if(!$user->isEmailVerified())
                                                    <form method="POST" action="{{ route('users.resend-verification', $user->id) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit"
                                                                class="btn btn-outline-secondary btn-sm"
                                                                title="Reinvia email di verifica"
                                                                onclick="return confirm('Reinviare l\'email di verifica a {{ $user->name }}?')">
                                                            <i class="fas fa-paper-plane"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                
                                                <!-- Elimina utente -->
                                                <form method="POST" action="{{ route('users.destroy', $user->id) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" 
                                                            class="btn btn-outline-danger btn-sm"
                                                            title="Elimina utente"
                                                            onclick="return confirm('Sei sicuro di voler eliminare l\'utente {{ $user->name }}? Questa azione non può essere annullata.')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="btn btn-outline-secondary btn-sm disabled" title="Non puoi modificare il tuo stesso account">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($users->isEmpty())
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nessun utente trovato</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
