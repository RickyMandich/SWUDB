@extends('layouts.app')
@section('title', 'Storico Test di Sistema')
@section('content')
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Storico Test di Sistema</h1>
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

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Test Name</th>
                                <th>Esito</th>
                                <th>ID Esecuzione</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($results as $result)
                                <tr>
                                    <td>{{ $result->created_at->format('d/m/Y H:i') }}</td>
                                    <td>{{ $result->test_name }}</td>
                                    <td>
                                        @if($result->status)
                                            <span class="badge bg-success">PASSATO</span>
                                        @else
                                            <span class="badge bg-danger">FALLITO</span>
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ $result->run_id }}</small></td>
                                    <td>
                                        <a href="{{ route('admin.tests.show', $result->id) }}" class="btn btn-sm btn-outline-primary">
                                            Dettagli
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4">Nessun test registrato.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $results->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
