@extends('layouts.app')

@section('title', 'Dettagli Errore - Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-bug text-danger me-2"></i>Dettagli Errore #{{ $error->id }}</h1>
                <a href="{{ route('admin.errors') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Torna alla Lista
                </a>
            </div>

            <div class="row">
                <!-- Error Information -->
                <div class="col-lg-8">
                    <!-- Basic Info -->
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Informazioni Errore</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Tipo Eccezione:</strong>
                                    <p><code>{{ $error->exception_class }}</code></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Data/Ora:</strong>
                                    <p>{{ $error->created_at->format('d/m/Y H:i:s') }}</p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <strong>Messaggio:</strong>
                                    <div class="alert alert-danger">
                                        {{ $error->message }}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <strong>File:</strong>
                                    <p><code>{{ $error->short_file }}</code></p>
                                </div>
                                <div class="col-md-4">
                                    <strong>Linea:</strong>
                                    <p><code>{{ $error->line }}</code></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Request Information -->
                    @if($error->request_url || $error->request_method || $error->user_agent)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="fas fa-globe me-1"></i>Informazioni Richiesta</h6>
                        </div>
                        <div class="card-body">
                            @if($error->request_url)
                                <div class="mb-3">
                                    <strong>URL:</strong>
                                    <p><code>{{ $error->request_url }}</code></p>
                                </div>
                            @endif
                            @if($error->request_method)
                                <div class="mb-3">
                                    <strong>Metodo HTTP:</strong>
                                    <span class="badge bg-primary">{{ $error->request_method }}</span>
                                </div>
                            @endif
                            @if($error->user_agent)
                                <div class="mb-3">
                                    <strong>User Agent:</strong>
                                    <p class="small text-muted">{{ $error->user_agent }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Stack Trace -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="fas fa-code me-1"></i>Stack Trace</h6>
                        </div>
                        <div class="card-body">
                            <pre class="bg-custom-light p-3 small" style="max-height: 400px; overflow-y: auto;"><code>{{ $error->trace }}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Status Management -->
                <div class="col-lg-4">
                    <!-- Current Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="fas fa-info-circle me-1"></i>Stato Attuale</h6>
                        </div>
                        <div class="card-body text-center">
                            <span class="badge bg-{{ $error->status_badge_color }} fs-6 p-2">
                                {{ $error->status_display }}
                            </span>
                            @if($error->resolved_at)
                                <p class="mt-2 small text-muted">
                                    Gestito il {{ $error->resolved_at->format('d/m/Y H:i') }}
                                    @if($error->resolvedBy)
                                        da {{ $error->resolvedBy->name }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- User Information -->
                    @if($error->user)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="fas fa-user me-1"></i>Utente</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Nome:</strong> {{ $error->user->name }}</p>
                            <p><strong>Email:</strong> {{ $error->user->email }}</p>
                            <p><strong>Admin:</strong> 
                                @if($error->user->admin)
                                    <span class="badge bg-warning">Sì</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @endif

                    <!-- Status Update Form -->
                    <div class="card">
                        <div class="card-header">
                            <h6><i class="fas fa-edit me-1"></i>Aggiorna Stato</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.errors.update', $error) }}">
                                @csrf
                                @method('PATCH')
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Nuovo Stato</label>
                                    <select name="status" id="status" class="form-select" required>
                                        <option value="new" {{ $error->status === 'new' ? 'selected' : '' }}>Nuovo</option>
                                        <option value="in_progress" {{ $error->status === 'in_progress' ? 'selected' : '' }}>In Lavorazione</option>
                                        <option value="resolved" {{ $error->status === 'resolved' ? 'selected' : '' }}>Risolto</option>
                                        <option value="ignored" {{ $error->status === 'ignored' ? 'selected' : '' }}>Ignorato</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="admin_notes" class="form-label">Note Admin</label>
                                    <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4" 
                                              placeholder="Aggiungi note sulla risoluzione o gestione dell'errore...">{{ $error->admin_notes }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-1"></i>Aggiorna Stato
                                </button>
                            </form>
                        </div>
                    </div>

                    @if($error->admin_notes)
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6><i class="fas fa-sticky-note me-1"></i>Note Admin Attuali</h6>
                        </div>
                        <div class="card-body">
                            <p class="small">{{ $error->admin_notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div class="toast show" role="alert">
            <div class="toast-header">
                <i class="fas fa-check-circle text-success me-2"></i>
                <strong class="me-auto">Successo</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                {{ session('success') }}
            </div>
        </div>
    </div>
@endif
@endsection
