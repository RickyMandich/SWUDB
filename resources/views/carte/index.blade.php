@extends('layouts.app')
@section('title', $title)
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
    <div class="row">
        @foreach ($content as $carta)
            <div class="col-12 col-sm-4 border border-primary">
                <div class="row">
                    <div class="col">
                        <img class="col-12" src="{{ $carta->backArt != "" ? $carta->backArt : $carta->frontArt }}" alt="immagine di {{$carta->snippet}}">
                    </div>
                    <div class="col">
                        <h5>{{ $carta->snippet }}</h5>
                        {{ $carta->tratti }} <br>
                        <div class="row">
                            <span class="col-9">costo:</span>
                            <span class="m-auto text-warning align-self-end col-3">{{ $carta->costo }}</span> <br>
                        </div>
                        <div class="row">
                            <span class="col-9">potenza:</span>
                            <span class="m-auto text-danger align-self-end col-3">{{ $carta->potenza }}</span> <br>
                        </div>
                        <div class="row">
                            <span class="col-9">vita:</span>
                            <span class="m-auto text-primary align-self-end col-3">{{ $carta->vita }}</span> <br>
                        </div>
                        <div class="row text-center">
                            <span class="col-12 {{ toClass($carta->rarita) }}">{{ $carta->rarita }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
@section("php")
<?php
function toClass($class){
    return str_replace(" ", "-", $class);
}
?>
@endsection