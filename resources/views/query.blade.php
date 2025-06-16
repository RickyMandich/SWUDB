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
                    <form method="GET" action="{{ route('query') }}">
                        <div class="mb-3">
                            <label for="query" class="form-label">SQL Query:</label>
                            <textarea class="form-control" id="query" name="query" rows="5" placeholder="SELECT * FROM cards LIMIT 10">{{ $query ?? '' }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-1"></i>Esegui Query
                        </button>
                    </form>

                    @if(isset($result) && count($result) > 0)
                        <hr>
                        <h5>Risultati ({{ count($result) }} righe):</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
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
                                            @foreach((array)$row as $value)
                                                <td>{{ $value }}</td>
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
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
