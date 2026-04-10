@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-database me-2"></i>Database Query Tool</h4>
                    <small class="text-muted">Solo per amministratori</small>
                </div>

                <div class="card-body">
                    <form method="GET" action="{{ route('query') }}" id="queryForm">
                        <div class="mb-3">
                            <label for="query" class="form-label">SQL Query:</label>
                            <textarea class="form-control" id="query" name="query" rows="5" placeholder="SELECT * FROM cards LIMIT 10">{{ $query ?? '' }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-1"></i>Esegui Query
                        </button>

                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Nota:</strong> Le query senza <code>ORDER BY</code>
                                verranno automaticamente ordinate usando l'algoritmo mergeSort personalizzato,
                                purché i risultati contengano tutti gli attributi necessari
                                (cid, nome, tipo, costo, numero, espansione).
                                L'attributo <code>uscita</code> viene recuperato automaticamente dalla tabella <code>expansions</code> se mancante.
                                <br>
                                <i class="fas fa-keyboard me-1"></i>
                                <strong>Scorciatoia:</strong> Premi <kbd>Ctrl</kbd> + <kbd>Invio</kbd> per eseguire la query.
                            </small>
                        </div>
                    </form>

                    @if(isset($error))
                        <hr>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>Errore MySQL
                            </h5>
                            <p class="mb-0">{{ $error }}</p>
                        </div>
                    @elseif(isset($result) && count($result) > 0)
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Risultati ({{ count($result) }} righe):</h5>
                            <div>
                                @if(isset($sorted) && $sorted)
                                    <span class="badge bg-success">
                                        <i class="fas fa-sort me-1"></i>Ordine mergeSort
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark"
                                        @if(isset($missingAttributes) && !empty($missingAttributes))
                                            title="Attributi mancanti: {{ implode(', ', $missingAttributes) }}"
                                        @endif>
                                        <i class="fas fa-sort-slash me-1"></i>Ordine non mergeSort
                                    </span>
                                @endif
                            </div>
                        </div>
                        @if(isset($missingAttributes) && !empty($missingAttributes))
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong>MergeSort non applicato.</strong> Attributi mancanti: <code>{{ implode(', ', $missingAttributes) }}</code>
                                </small>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-striped-columns table-hover table-sm">
                                @php
                                    $columns = array_keys((array)$result[0]);
                                @endphp
                                <thead class="table-dark">
                                    <tr>
                                        @foreach($columns as $column)
                                            @if($column !== 'cid')
                                                <th>{{ $column }}</th>
                                            @endif
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($result as $row)
                                        <tr>
                                            @foreach($columns as $column)
                                                @if($column !== 'cid')
                                                    <td>
                                                        @php
                                                            $rowArray = (array)$row;
                                                            $value = $rowArray[$column] ?? '';
                                                        @endphp
                                                        @if(isset($sorted) and $sorted)
                                                            <a href="{{ route('carta', ['espansione' => $row->espansione, 'numero' => $row->numero]) }}" target="_blank">
                                                        @endif
                                                        {!! $value !!}
                                                        @if(isset($sorted) and $sorted)
                                                            </a>
                                                        @endif
                                                    </td>
                                                @endif
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif(isset($result))
                        <hr>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>Query eseguita con successo. Nessun risultato trovato.
                        </div>
                    @elseif(isset($affectedRows))
                        <hr>
                        <div class="alert alert-success">
                            <h5 class="alert-heading">
                                <i class="fas fa-check-circle me-2"></i>Query eseguita con successo
                            </h5>
                            <p class="mb-0">
                                <strong>{{ $affectedRows }}</strong>
                                {{ $affectedRows === 1 ? 'riga modificata' : 'righe modificate' }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const queryTextarea = document.getElementById('query');
    const queryForm = document.getElementById('queryForm');

    // Aggiungi listener per Ctrl+Invio
    queryTextarea.addEventListener('keydown', function(event) {
        // Verifica se è stato premuto Ctrl+Invio
        if (event.ctrlKey && event.key === 'Enter') {
            event.preventDefault(); // Previene il comportamento di default (nuova riga)

            // Aggiungi feedback visivo
            const submitButton = queryForm.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Eseguendo...';
            submitButton.disabled = true;

            // Esegui il form
            queryForm.submit();
        }
    });

    // Focus automatico sulla textarea quando la pagina si carica
    queryTextarea.focus();

    // Posiziona il cursore alla fine del testo esistente
    if (queryTextarea.value.length > 0) {
        queryTextarea.setSelectionRange(queryTextarea.value.length, queryTextarea.value.length);
    }
});
</script>
@endsection
