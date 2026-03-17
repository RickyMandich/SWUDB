@extends('layouts.app')
@section('title', 'Dettaglio Esecuzione Test')
@section('content')
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Dettaglio Esecuzione Test</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    @foreach($breadcrumbs as $breadcrumb)
                        <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                            @if($breadcrumb['url'])
                                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['text'] }}</a>
                            @else
                                {{ $breadcrumb['text'] }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Informazioni Generali</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <strong>Stato:</strong>
                                @if($result->status)
                                    <span class="badge bg-success">PASSATO</span>
                                @else
                                    <span class="badge bg-danger">FALLITO</span>
                                @endif
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <strong>Data:</strong>
                                <span>{{ $result->created_at->format('d/m/Y H:i:s') }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <strong>Durata:</strong>
                                <span>{{ $result->duration ?? 'N/D' }}s</span>
                            </li>
                        </ul>
                    </div>
                </div>

                @if(!$result->status)
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Questo test ha bloccato l'esecuzione della scansione pianificata.
                    </div>
                @endif
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Console Output</h5>
                    </div>
                    <div class="card-body p-0">
                        <pre class="bg-dark text-light p-4 mb-0" style="max-height: 600px; overflow-y: auto;"><code>{{ $result->output }}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
