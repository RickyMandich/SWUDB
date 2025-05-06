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
                                    @if ($attributo == "snippet")
                                    <a href="/carta/{{ $carta["espansione"] }}/{{ $carta["numero"] }}">
                                        {!! nl2br($carta[$attributo]) !!}
                                    </a>
                                    @else
                                        {!! nl2br($carta[$attributo]) !!}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
@endsection