@extends('layouts.app')
@section('content')
    <h1>
        {{ $nome }}
    </h1>
    @foreach ($mazzo as $carta)
        {{ $carta->snippet }}
    @endforeach
@endsection