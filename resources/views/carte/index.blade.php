@extends('layouts.app')
@section('content')
        <div id="row">
            <form action="/carte" class="mb-3">
                <label for="nome" class="form-label">Inserisci il nome della carta</label>
                <input type="text" name="nome" id="nome" class="form-control mb-2" value="{{ $nome }}">
                <input type="submit" value="Cerca" class="btn btn-primary">
            </form>
            <a href="/carte" class="btn btn-secondary">Cancella parametri di ricerca</a>
        </div>
        <p>Trovati {{ count($content) }} risultati</p>
        <div class="table-container table-responsive">
            <table class="table table-hover table-striped table-bordered">
                <thead class="table-light sticky-top">
                    <tr">
                        @foreach($header as $attributo)
                            <th>{{ $attributo }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class=".table-group-divider">
                    @if (!$empty)
                        @foreach($content as $carta)
                        <tr>
                            @foreach ($header as $attributo)
                                <td>
                                    <a href="/carta/{{ $carta["espansione"] }}/{{ $carta["numero"] }}">
                                        {{ $carta[$attributo] }}
                                    </a>
                                </td>
                            @endforeach
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
@endsection