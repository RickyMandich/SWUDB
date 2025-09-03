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
                                                <input type="text"
                                                       name="rotazione"
                                                       value="{{ $expansion->rotazione }}"
                                                       class="form-control form-control-sm text-center"
                                                       maxlength="1"
                                                       pattern="[0A-Z]"
                                                       title="Inserire 0 o una lettera maiuscola (A-Z)"
                                                       style="width: 60px;"
                                                       required>
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
                                    <i class="fas fa-info-circle me-1"></i>Informazioni sui gruppi di rotazione:
                                </h6>
                                <ul class="mb-0">
                                    <li><strong>0:</strong> Gruppo base/standard</li>
                                    <li><strong>A-Z:</strong> Gruppi di rotazione identificati da lettere maiuscole</li>
                                    <li>Le espansioni dello stesso gruppo entrano ed escono dal formato insieme</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
