@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="fas fa-user me-2"></i>Dettagli Utente: {{ $user->name }}
                            @if($user->admin)
                                <i class="fas fa-crown text-warning ms-2" title="Amministratore"></i>
                            @endif
                        </h4>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Torna all'elenco
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Informazioni utente -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle me-1"></i>Informazioni Generali
                                    </h6>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>ID:</strong></td>
                                            <td>{{ $user->id }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Nome:</strong></td>
                                            <td>{{ $user->name }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td>{{ $user->email }}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Stato:</strong></td>
                                            <td>
                                                @if($user->admin)
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-shield-alt me-1"></i>Amministratore
                                                    </span>
                                                @else
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-user me-1"></i>Utente Normale
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email Verificata:</strong></td>
                                            <td>
                                                @if($user->email_verified_at)
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Verificata
                                                    </span>
                                                    <br><small class="text-muted">{{ $user->email_verified_at->format('d/m/Y H:i') }}</small>
                                                @else
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Non Verificata
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-clock me-1"></i>Timestamp
                                    </h6>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Registrato:</strong></td>
                                            <td>
                                                {{ $user->created_at->format('d/m/Y H:i:s') }}
                                                <br><small class="text-muted">{{ $user->created_at->diffForHumans() }}</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ultimo Aggiornamento:</strong></td>
                                            <td>
                                                {{ $user->updated_at->format('d/m/Y H:i:s') }}
                                                <br><small class="text-muted">{{ $user->updated_at->diffForHumans() }}</small>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form di modifica (solo se non è l'utente corrente) -->
                    @if($user->id !== Auth::id())
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="fas fa-edit me-1"></i>Modifica Informazioni</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('users.update', $user->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Nome</label>
                                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                       id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                                @error('name')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                                       id="email" name="email" value="{{ old('email', $user->email) }}" required>
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
                                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                                       id="password" name="password">
                                                @error('password')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="password_confirmation" class="form-label">Conferma Password</label>
                                                <input type="password" class="form-control" 
                                                       id="password_confirmation" name="password_confirmation">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Salva Modifiche
                                        </button>
                                        
                                        <div>
                                            <!-- Toggle admin status -->
                                            <form method="POST" action="{{ route('users.toggle-admin', $user->id) }}" class="d-inline me-2">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="btn btn-{{ $user->admin ? 'warning' : 'success' }}"
                                                        onclick="return confirm('Sei sicuro di voler {{ $user->admin ? 'rimuovere i privilegi di amministratore da' : 'promuovere ad amministratore' }} {{ $user->name }}?')">
                                                    <i class="fas fa-{{ $user->admin ? 'user-minus' : 'user-plus' }} me-1"></i>
                                                    {{ $user->admin ? 'Rimuovi Admin' : 'Promuovi Admin' }}
                                                </button>
                                            </form>
                                            
                                            <!-- Elimina utente -->
                                            <form method="POST" action="{{ route('users.destroy', $user->id) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-danger"
                                                        onclick="return confirm('Sei sicuro di voler eliminare l\'utente {{ $user->name }}? Questa azione non può essere annullata.')">
                                                    <i class="fas fa-trash me-1"></i>Elimina Utente
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Nota:</strong> Non puoi modificare il tuo stesso account da questa pagina.
                            <a href="{{ route('dashboard') }}" class="alert-link">Vai alla dashboard</a> per gestire il tuo account.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
