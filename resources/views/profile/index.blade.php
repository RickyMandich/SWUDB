@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="fas fa-user-cog me-2"></i>Il Mio Profilo
                            @if($user->admin)
                                <i class="fas fa-crown text-warning ms-2" title="Amministratore"></i>
                            @endif
                        </h4>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Torna alla Dashboard
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Informazioni account -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-secondary">
                                <div class="card-header">
                                    <h6><i class="fas fa-info-circle me-1"></i>Informazioni Account</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>Nome Utente:</strong>
                                        <span class="text-info">{{ $user->name }}</span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email:</strong>
                                        <span class="text-info">{{ $user->email }}</span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Stato Account:</strong>
                                        @if($user->admin)
                                            <span class="badge bg-success">
                                                <i class="fas fa-shield-alt me-1"></i>Amministratore
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-user me-1"></i>Utente
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <strong>Email Verificata:</strong>
                                        @if($user->isEmailVerified())
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Verificata
                                            </span>
                                        @else
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Non Verificata
                                            </span>
                                        @endif
                                    </div>
                                    <div class="mb-3">
                                        <strong>Registrato il:</strong>
                                        <span class="text-muted">{{ $user->created_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    <div>
                                        <strong>Ultimo aggiornamento:</strong>
                                        <span class="text-muted">{{ $user->updated_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card bg-secondary">
                                <div class="card-header">
                                    <h6><i class="fas fa-tools me-1"></i>Azioni Rapide</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="{{ route('collezione') }}" class="btn btn-outline-warning">
                                            <i class="fas fa-star me-2"></i>La Mia Collezione
                                        </a>
                                        <a href="{{ route('mazzi') }}" class="btn btn-outline-success">
                                            <i class="fas fa-layer-group me-2"></i>I Miei Mazzi
                                        </a>
                                        @if($user->admin)
                                            <div class="dropdown-divider"></div>
                                            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary">
                                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard Admin
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form di modifica profilo -->
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-edit me-1"></i>Modifica Profilo</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('profile.update') }}">
                                @csrf
                                @method('PATCH')
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Nome Utente</label>
                                            <input type="text" 
                                                   class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" 
                                                   name="name" 
                                                   value="{{ old('name', $user->name) }}" 
                                                   required>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" 
                                                   class="form-control @error('email') is-invalid @enderror" 
                                                   id="email" 
                                                   name="email" 
                                                   value="{{ old('email', $user->email) }}" 
                                                   required>
                                            @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password" class="form-label">Nuova Password (opzionale)</label>
                                            <input type="password" 
                                                   class="form-control @error('password') is-invalid @enderror" 
                                                   id="password" 
                                                   name="password">
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <div class="form-text">Lascia vuoto per mantenere la password attuale</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password_confirmation" class="form-label">Conferma Nuova Password</label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="password_confirmation" 
                                                   name="password_confirmation">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Salva Modifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Azioni account -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6><i class="fas fa-cogs me-1"></i>Gestione Account</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Verifica email -->
                                @if(!$user->isEmailVerified())
                                    <div class="col-md-6 mb-3">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Email non verificata</strong><br>
                                            <small>Verifica la tua email per accedere a tutte le funzionalità</small>
                                        </div>
                                        <form method="POST" action="{{ route('profile.resend-verification') }}" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-warning"
                                                    onclick="return confirm('Reinviare l\'email di verifica?')">
                                                <i class="fas fa-paper-plane me-1"></i>Reinvia Email di Verifica
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="col-md-6 mb-3">
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-1"></i>
                                            <strong>Email verificata</strong><br>
                                            <small>Il tuo account è completamente attivo</small>
                                        </div>
                                    </div>
                                @endif

                                <!-- Elimina account -->
                                <div class="col-md-6 mb-3">
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        <strong>Zona Pericolosa</strong><br>
                                        <small>Elimina definitivamente il tuo account</small>
                                    </div>
                                    <button type="button" 
                                            class="btn btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteAccountModal">
                                        <i class="fas fa-trash me-1"></i>Elimina Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal per eliminazione account -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-danger">
                <h5 class="modal-title text-danger" id="deleteAccountModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Elimina Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('profile.delete') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <strong>ATTENZIONE:</strong> Questa azione è irreversibile!
                    </div>
                    <p>Stai per eliminare definitivamente il tuo account <strong>{{ $user->name }}</strong>.</p>
                    <p>Tutti i tuoi dati, mazzi e collezione verranno eliminati permanentemente.</p>
                    
                    <div class="mb-3">
                        <label for="delete_password" class="form-label">Conferma con la tua password:</label>
                        <input type="password" 
                               class="form-control" 
                               id="delete_password" 
                               name="password" 
                               required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Annulla
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Elimina Definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
