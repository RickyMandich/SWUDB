@extends('layouts.app')
<style>
    .content {
        flex-grow: 1; /* Occupa tutto lo spazio disponibile */
        overflow: auto; /* Abilita lo scorrimento */
        max-width: 100%; /* Usa tutta la larghezza disponibile */
        height: 82.5vh; /* Altezza massima pari all'altezza dello schermo */
        display: flex;
        flex-direction: column;
    }

    
    
    .table thead th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa; /* Sfondo per l'intestazione */
        z-index: 1;
    }

    #row *{
        display: inline;
    }
</style>
@section('content')
    <div class="container content mt-4">
        <div id="row">
            <form action="/carte" class="mb-3">
                <label for="nome" class="form-label">Inserisci il nome della carta</label>
                <input type="text" name="nome" id="nome" class="form-control mb-2" value="{{ $nome }}">
                <input type="submit" value="Cerca" class="btn btn-primary">
            </form>
            <a href="/carte" class="btn btn-secondary">Cancella parametri di ricerca</a>
        </div>
        <p>Trovati {{ count($content) }} risultati</p>
        <div class="table-container">
            <table class="table table-hover table-striped table-bordered">
                <thead class="table-light sticky-top">
                    <tr">
                        @foreach($header as $attributo)
                            <th>{{ $attributo }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class=".table-group-divider">
                    @if ($empty)
                    @else
                    @foreach($content as $carta)
                    <tr>
                        @foreach ($header as $attributo)
                            <td>{{ $carta[$attributo] }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection