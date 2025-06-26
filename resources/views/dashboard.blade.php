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
    border-bottom: 1px solid #e9ecef;
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
    background-color: #f8f9fa;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    color: #e83e8c;
    border: 1px solid #e9ecef;
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
    background: #f1f1f1;
    border-radius: 4px;
}

.markdown-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
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
            @if(Auth::admin())
                <div class="card mt-4 bg-primary-subtle">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-tasks me-2"></i>Todo List - Stato Sviluppo
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#todoListCollapse" aria-expanded="true" aria-controls="todoListCollapse">
                            <i class="fas fa-chevron-up" id="todoToggleIcon"></i>
                        </button>
                    </div>
                    <div class="collapse show" id="todoListCollapse">
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
                </script>
            @endif
        </div>
    </div>
</div>
@endsection

