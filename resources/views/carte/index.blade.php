@extends('layouts.app')
@section('title', $title)
@section('content')
    <div id="row">
        <form action="{{ route("carte", ["espansione" => $espansione]) }}" class="mb-3 row">
            <label for="nome" class="form-label col-12">Inserisci il nome della carta</label>
            <span class="col-11 my-4">
                <input type="text" placeholder="Carta" name="nome" id="nome" class="form-control" value="{{ $nome }}">
            </span>
            <input type="submit" value="Cerca" class="btn btn-primary col-1 my-4">
            @foreach ($espansioni as $set)
            <a href="{{ route("carte", ["espansione" => $set->espansione]) }}" class="btn btn-{{ $set->espansione == $espansione ? "primary" : "secondary" }} col-3 col-sm-1 m-2 text-center">
                {{ $set->espansione }}
            </a>
            @endforeach
            <a href="{{ route("carte") }}" class="btn btn-{{ $espansione == "" ? "primary" : "secondary" }} col-10 col-sm-12 m-2 my-4">Cancella parametri di ricerca</a>
        </form>
    </div>
    <p>Trovati {{ count($content) }} risultati</p>
    <div class="row">
        @foreach ($content as $carta)
            <div class="col-12 col-sm-4 ps-4 pe-4 pt-4 pb-4">
                <div class="innerCarta row pr-10">
                    <div class="col-12 col-sm-12 rounded-4 border-primary-subtle bg-secondary-subtle p-3">
                        <a href="{{ route("carta", ["espansione" => $carta->espansione, "numero" => $carta->numero]) }}">
                            <div class="row">
                                <div class="col">
                                    <img class="col-12" src="{{ $carta->frontArt }}" alt="immagine di {{$carta->snippet}}">
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
                                        <span class="col-12 {{ toCssClass($carta->rarita) }}">{{ $carta->rarita }}</span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
@section("php")
<?php
function toCssClass($class){
    return str_replace(" ", "-", $class);
}
?>
@endsection