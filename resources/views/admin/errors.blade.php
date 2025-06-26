@extends('layouts.app')

@section('title', 'Gestione Errori - Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-exclamation-triangle text-danger me-2"></i>Gestione Errori di Sistema</h1>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Torna alla Dashboard
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Totale</h5>
                            <h3 class="text-primary">{{ $stats['total'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Nuovi</h5>
                            <h3 class="text-danger">{{ $stats['new'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-muted">In Lavorazione</h5>
                            <h3 class="text-warning">{{ $stats['in_progress'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Risolti</h5>
                            <h3 class="text-success">{{ $stats['resolved'] }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-muted">Ignorati</h5>
                            <h3 class="text-secondary">{{ $stats['ignored'] }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6><i class="fas fa-filter me-1"></i>Filtri</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.errors') }}">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Stato</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Tutti gli stati</option>
                                    <option value="new" {{ request('status') === 'new' || request('status') === null ? 'selected' : '' }}>Nuovo</option>
                                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Lavorazione</option>
                                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Risolto</option>
                                    <option value="ignored" {{ request('status') === 'ignored' ? 'selected' : '' }}>Ignorato</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Cerca per messaggio, classe eccezione o file..." 
                                       value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filtra
                                </button>
                                <a href="{{ route('admin.errors') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Batch Actions -->
            @if($stats['new'] > 0)
            <div class="card mb-3">
                <div class="card-header">
                    <h6><i class="fas fa-bolt me-1"></i>Azioni Rapide</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-auto">
                            <form method="POST" action="{{ route('admin.errors.batch-action') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="action" value="resolved">
                                <input type="hidden" name="filter_new" value="1">
                                <button type="submit" class="btn btn-success"
                                        onclick="return confirm('Sei sicuro di voler segnare tutti i {{ $stats['new'] }} errori nuovi come risolti?')">
                                    <i class="fas fa-check-double me-1"></i>Segna tutti i nuovi come risolti ({{ $stats['new'] }})
                                </button>
                            </form>
                        </div>
                        <div class="col-auto">
                            <form method="POST" action="{{ route('admin.errors.batch-action') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="action" value="ignored">
                                <input type="hidden" name="filter_new" value="1">
                                <button type="submit" class="btn btn-secondary"
                                        onclick="return confirm('Sei sicuro di voler segnare tutti i {{ $stats['new'] }} errori nuovi come ignorati?')">
                                    <i class="fas fa-times-circle me-1"></i>Segna tutti i nuovi come ignorati ({{ $stats['new'] }})
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Errors Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-list me-1"></i>Errori ({{ $errors->total() }} totali)</h6>
                    <div class="batch-actions" style="display: none;">
                        <form method="POST" action="{{ route('admin.errors.batch-action') }}" class="d-inline" id="batchForm">
                            @csrf
                            <div class="input-group input-group-sm">
                                <select name="action" class="form-select" required>
                                    <option value="">Seleziona azione...</option>
                                    <option value="resolved">Segna come risolti</option>
                                    <option value="ignored">Segna come ignorati</option>
                                    <option value="in_progress">Segna come in lavorazione</option>
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play me-1"></i>Applica
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSelection()">
                                    <i class="fas fa-times me-1"></i>Annulla
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    @if($errors->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAll" class="form-check-input"
                                                   onchange="toggleSelectAll(this)">
                                        </th>
                                        <th>Data</th>
                                        <th>Stato</th>
                                        <th>Tipo Eccezione</th>
                                        <th>Messaggio</th>
                                        <th>Utente</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($errors as $error)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_errors[]" value="{{ $error->id }}"
                                                       class="form-check-input error-checkbox"
                                                       onchange="updateBatchActions()">
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $error->created_at->format('d/m/Y H:i') }}
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $error->status_badge_color }}">
                                                    {{ $error->status_display }}
                                                </span>
                                            </td>
                                            <td>
                                                <code class="small">{{ class_basename($error->exception_class) }}</code>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" title="{{ $error->message }}">
                                                    {{ $error->message }}
                                                </div>
                                            </td>
                                            <td>
                                                @if($error->user)
                                                    <small>{{ $error->user->name }}</small>
                                                @else
                                                    <small class="text-muted">Guest</small>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('admin.errors.show', $error) }}"
                                                       class="btn btn-sm btn-outline-primary"
                                                       title="Visualizza dettagli">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    @if($error->status !== 'resolved')
                                                        <form method="POST" action="{{ route('admin.errors.update', $error) }}" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="resolved">
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-outline-success"
                                                                    title="Segna come risolto"
                                                                    onclick="return confirm('Sei sicuro di voler segnare questo errore come risolto?')">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    @endif

                                                    @if($error->status !== 'ignored')
                                                        <form method="POST" action="{{ route('admin.errors.update', $error) }}" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="status" value="ignored">
                                                            <button type="submit"
                                                                    class="btn btn-sm btn-outline-secondary"
                                                                    title="Segna come ignorato"
                                                                    onclick="return confirm('Sei sicuro di voler segnare questo errore come ignorato?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $errors->appends(request()->query())->links() }}
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h5>Nessun errore trovato</h5>
                            <p class="text-muted">Non ci sono errori che corrispondono ai filtri selezionati.</p>
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

<script>
function toggleSelectAll(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.error-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateBatchActions();
}

function updateBatchActions() {
    const selectedCheckboxes = document.querySelectorAll('.error-checkbox:checked');
    const batchActions = document.querySelector('.batch-actions');
    const selectAllCheckbox = document.getElementById('selectAll');

    if (selectedCheckboxes.length > 0) {
        batchActions.style.display = 'block';
        // Update form with selected IDs
        const existingInputs = document.querySelectorAll('#batchForm input[name="selected_errors[]"]');
        existingInputs.forEach(input => input.remove());

        selectedCheckboxes.forEach(checkbox => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_errors[]';
            hiddenInput.value = checkbox.value;
            document.getElementById('batchForm').appendChild(hiddenInput);
        });
    } else {
        batchActions.style.display = 'none';
    }

    // Update select all checkbox state
    const totalCheckboxes = document.querySelectorAll('.error-checkbox');
    if (selectedCheckboxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (selectedCheckboxes.length === totalCheckboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
        selectAllCheckbox.checked = false;
    }
}

function clearSelection() {
    const checkboxes = document.querySelectorAll('.error-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAll').checked = false;
    document.getElementById('selectAll').indeterminate = false;
    updateBatchActions();
}

// Add confirmation to batch form
document.getElementById('batchForm').addEventListener('submit', function(e) {
    const selectedCount = document.querySelectorAll('.error-checkbox:checked').length;
    const action = this.querySelector('select[name="action"]').value;
    const actionText = this.querySelector('select[name="action"] option:checked').textContent;

    if (!confirm(`Sei sicuro di voler ${actionText.toLowerCase()} ${selectedCount} errori selezionati?`)) {
        e.preventDefault();
    }
});
</script>
@endsection
