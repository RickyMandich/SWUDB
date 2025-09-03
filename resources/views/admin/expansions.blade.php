@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i>Gestione Espansioni
                    </h4>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Torna alla Dashboard
                    </a>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-1"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Codice Espansione</th>
                                    <th>Data Uscita</th>
                                    <th>Rotazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expansions as $expansion)
                                    <tr>
                                        <form method="POST" action="{{ route('admin.expansions.update') }}" class="expansion-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="espansione" value="{{ $expansion->espansione }}">
                                            
                                            <td>
                                                <strong>{{ $expansion->espansione }}</strong>
                                            </td>
                                            
                                            <td>
                                                <input type="text" 
                                                       name="uscita" 
                                                       value="{{ $expansion->uscita }}" 
                                                       class="form-control form-control-sm"
                                                       maxlength="65"
                                                       required>
                                            </td>
                                            
                                            <td>
                                                <select name="rotazione" class="form-select form-select-sm" required>
                                                    <option value="0" {{ $expansion->rotazione === '0' ? 'selected' : '' }}>Standard (0)</option>
                                                    <option value="1" {{ $expansion->rotazione === '1' ? 'selected' : '' }}>Rotazione (1)</option>
                                                    <option value="2" {{ $expansion->rotazione === '2' ? 'selected' : '' }}>Legacy (2)</option>
                                                    <option value="3" {{ $expansion->rotazione === '3' ? 'selected' : '' }}>Banned (3)</option>
                                                </select>
                                            </td>
                                            
                                            <td>
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-save me-1"></i>Salva
                                                </button>
                                            </td>
                                        </form>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            <i class="fas fa-info-circle me-1"></i>Nessuna espansione trovata
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <div class="card bg-info-subtle">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-info-circle me-1"></i>Informazioni sui valori di rotazione:
                                </h6>
                                <ul class="mb-0">
                                    <li><strong>0 - Standard:</strong> Espansione attualmente in formato Standard</li>
                                    <li><strong>1 - Rotazione:</strong> Espansione che uscirà dal formato Standard</li>
                                    <li><strong>2 - Legacy:</strong> Espansione fuori dal formato Standard</li>
                                    <li><strong>3 - Banned:</strong> Espansione bannata/non utilizzabile</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form on select change for better UX
    const rotationSelects = document.querySelectorAll('select[name="rotazione"]');
    rotationSelects.forEach(select => {
        select.addEventListener('change', function() {
            if (confirm('Vuoi salvare automaticamente questa modifica?')) {
                this.closest('form').submit();
            }
        });
    });
});
</script>
@endsection
