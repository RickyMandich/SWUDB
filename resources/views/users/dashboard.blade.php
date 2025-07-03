@extends('layouts.app')

@push('styles')
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
    border-bottom: 1px solid #495057;
    padding-bottom: 0.25rem;
}

.markdown-content ul {
    margin-bottom: 1rem;
    padding-left: 1.5rem;
}

.markdown-content li {
    margin-bottom: 0.25rem;
    position: relative;
}

.markdown-content li::marker {
    color: #6c757d;
}

.markdown-content del {
    color: #6c757d;
    text-decoration: line-through;
    opacity: 0.7;
}

.markdown-content del::before {
    content: "✅ ";
    color: #28a745;
    font-weight: bold;
    text-decoration: none;
}

.markdown-content code {
    background-color: #495057;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: #e83e8c;
    border: 1px solid #6c757d;
}

.markdown-content ul ul {
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}

.markdown-content li:not(:has(del)) {
    color: #495057;
}

.markdown-content li:not(:has(del))::before {
    content: "🔲 ";
    margin-right: 0.25rem;
}

.markdown-content strong {
    color: #212529;
    font-weight: 600;
}

/* Scrollbar personalizzata per la todo list */
.markdown-content::-webkit-scrollbar {
    width: 8px;
}

.markdown-content::-webkit-scrollbar-track {
    background: #495057;
    border-radius: 4px;
}

.markdown-content::-webkit-scrollbar-thumb {
    background: #6c757d;
    border-radius: 4px;
}

.markdown-content::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
@endpush

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ 'Dashboard' }}</div>

                <div class="card-body fs-4">
                    @if(session('error'))
                        <div class="row mb-3 justify-content">
                            <div class="col-md-12 text-danger text-center">
                                {{ session('error') }}
                            </div>
                        </div>
                    @endif
                    @if(session('warning'))
                        <div class="row mb-3 justify-content">
                            <div class="col-md-12 text-warning text-center">
                                {{ session('warning') }}
                            </div>
                        </div>
                    @endif
                    @if (session('status'))
                    <div class="row mb-3 justify-content">
                        <div class="col-md-12 text-warning text-center">
                            {{ session('status') }}
                        </div>
                    </div>
                    @endif


                    <div class="row mb-3 justify-content">
                        <div class="col-md-12 text-center">
                            <h1>{{ __('Benvenuto, ') . Auth::user()->name . '!' }}</h1>
                        </div>
                    </div>

                    <div class="row mb-3 justify-content">
                        <span class="col-md-5 text-md-end">
                            {{ __("custom.nome") }}:
                        </span>
                        <span class="col-md-7">
                            {{ Auth::user()->name }}
                        </span>
                    </div>

                    <div class="row mb-3 justify-content">
                        <span class="col-md-5 text-md-end">
                            {{ __("custom.email") }}:
                        </span>
                        <span class="col-md-7">
                            {{ Auth::user()->email }}
                        </span>
                    </div>

                    <div class="row mb-3 justify-content-center">
                        <div class="col-md-6 text-center">
                            <a href="{{ route('collezione') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-folder-open me-2"></i>
                                Vai alla mia Collezione
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sezione Gestione Profilo -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-cog me-2"></i>Gestione Profilo
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#profileManagementCollapse" aria-expanded="false" aria-controls="profileManagementCollapse">
                        <i class="fas fa-chevron-down" id="profileToggleIcon"></i>
                    </button>
                </div>
                <div class="collapse" id="profileManagementCollapse">
                    <div class="card-body">
                        <div class="row">
                            <!-- Informazioni Account -->
                            <div class="col-md-6">
                                <div class="card bg-custom-light">
                                    <div class="card-header">
                                        <h6><i class="fas fa-info-circle me-1"></i>Informazioni Account</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong>Stato Account:</strong>
                                            @if(Auth::user()->admin)
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
                                            @if(Auth::user()->isEmailVerified())
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
                                            <span class="text-muted">{{ Auth::user()->created_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div>
                                            <strong>Ultimo aggiornamento:</strong>
                                            <span class="text-muted">{{ Auth::user()->updated_at->format('d/m/Y H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Azioni Account -->
                            <div class="col-md-6">
                                <div class="card bg-custom-light">
                                    <div class="card-header">
                                        <h6><i class="fas fa-tools me-1"></i>Azioni Account</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <!-- Modifica profilo -->
                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                                <i class="fas fa-edit me-2"></i>Modifica Profilo
                                            </button>

                                            <!-- Reinvia verifica email (solo se non verificata) -->
                                            @if(!Auth::user()->isEmailVerified())
                                                <form method="POST" action="{{ route('profile.resend-verification') }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-warning w-100" onclick="return confirm('Reinviare l\'email di verifica?')">
                                                        <i class="fas fa-paper-plane me-2"></i>Reinvia Email di Verifica
                                                    </button>
                                                </form>
                                            @endif

                                            <!-- Elimina account -->
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                                <i class="fas fa-trash me-2"></i>Elimina Account
                                            </button>

                                            @if(Auth::user()->admin)
                                                <div class="dropdown-divider"></div>
                                                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-success">
                                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard Admin
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @if(Auth::admin())
                <div class="card mt-4 bg-primary-subtle">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>Todo List - Stato Sviluppo
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#todoListCollapse" aria-expanded="false" aria-controls="todoListCollapse">
                            <i class="fas fa-chevron-down" id="todoToggleIcon"></i>
                        </button>
                    </div>
                    <div class="collapse" id="todoListCollapse">
                        <div class="card-body">
                            <div class="markdown-content" style="max-height: 600px; overflow-y: auto;">
                                @php
                                    $todoFile = base_path('todo list.md');
                                    $todoExists = file_exists($todoFile);
                                @endphp

                                @if($todoExists)
                                    @php
                                        try {
                                            $todoContent = file_get_contents($todoFile);
                                            $markdownContent = Illuminate\Support\Str::markdown($todoContent);
                                        } catch (Exception $e) {
                                            $markdownContent = null;
                                        }
                                    @endphp

                                    @if($markdownContent)
                                        {!! $markdownContent !!}
                                    @else
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            Errore durante la lettura del file 'todo list.md'.
                                        </div>
                                    @endif
                                @else
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        File 'todo list.md' non trovato nella root del progetto.
                                        <br><small class="text-muted">Percorso cercato: {{ $todoFile }}</small>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    La todo list viene caricata dal file <code>todo list.md</code> nella root del progetto.
                                    <br>
                                    <i class="fas fa-check-circle me-1 text-success"></i>
                                    Elementi con <del>testo barrato</del> sono completati.
                                    <br>
                                    <i class="fas fa-clock me-1 text-warning"></i>
                                    Ultimo aggiornamento:
                                    @if($todoExists)
                                        @php
                                            try {
                                                $lastModified = filemtime($todoFile);
                                                $formattedDate = date('d/m/Y H:i', $lastModified);
                                            } catch (Exception $e) {
                                                $formattedDate = 'Non disponibile';
                                            }
                                        @endphp
                                        {{ $formattedDate }}
                                    @else
                                        Non disponibile
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toggleButton = document.querySelector('[data-bs-target="#todoListCollapse"]');
                    const toggleIcon = document.getElementById('todoToggleIcon');
                    const collapseElement = document.getElementById('todoListCollapse');

                    if (collapseElement && toggleIcon) {
                        collapseElement.addEventListener('shown.bs.collapse', function () {
                            toggleIcon.className = 'fas fa-chevron-up';
                        });

                        collapseElement.addEventListener('hidden.bs.collapse', function () {
                            toggleIcon.className = 'fas fa-chevron-down';
                        });
                    }
                });

                // Profile management toggle
                const profileCollapseElement = document.getElementById('profileManagementCollapse');
                const profileToggleIcon = document.getElementById('profileToggleIcon');

                if (profileCollapseElement && profileToggleIcon) {
                    profileCollapseElement.addEventListener('shown.bs.collapse', function () {
                        profileToggleIcon.className = 'fas fa-chevron-up';
                    });

                    profileCollapseElement.addEventListener('hidden.bs.collapse', function () {
                        profileToggleIcon.className = 'fas fa-chevron-down';
                    });
                }
                </script>
            @endif
        </div>
    </div>
</div>

<!-- Modal per modifica profilo -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">
                    <i class="fas fa-edit me-2"></i>Modifica Profilo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Utente</label>
                                <input type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       id="name"
                                       name="name"
                                       value="{{ old('name', Auth::user()->name) }}"
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
                                       value="{{ old('email', Auth::user()->email) }}"
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Annulla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Salva Modifiche
                    </button>
                </div>
            </form>
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
                    <p>Stai per eliminare definitivamente il tuo account <strong>{{ Auth::user()->name }}</strong>.</p>
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

