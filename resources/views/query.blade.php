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
                                <strong>Nota:</strong> Le query sulla tabella <code>cards</code> senza <code>ORDER BY</code>
                                verranno automaticamente ordinate usando l'algoritmo mergeSort personalizzato,
                                purché i risultati contengano tutti gli attributi necessari
                                (nome, tipo, aspettoPrimario, aspettoSecondario, costo, uscita, numero, espansione).
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
                            @if(isset($sorted) && $sorted)
                                <span class="badge bg-info">
                                    <i class="fas fa-sort me-1"></i>Ordinamento mergeSort applicato
                                </span>
                            @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped-columns table-hover table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        @foreach(array_keys((array)$result[0]) as $column)
                                            <th>{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($result as $row)
                                        <tr>
                                            @foreach((array)$row as $key=>$value)
                                                @if($key !== "" or $value !== "")
                                                    <td>
                                                        @if(isset($sorted) and $sorted)
                                                            <a href="{{ route('carta', ['espansione' => $row->espansione, 'numero' => $row->numero]) }}" target="_blank">
                                                        @endif
                                                        <!--{!! $key !!} => -->{!! $value !!}
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
