@extends('layouts.app')
@section('content')
    <span class="translate-middle position-absolute start-50 bottom-50 fs-2 text-danger">Errore 
        @yield('code'):</span>
    <span class="translate-middle position-absolute start-50 top-50 fs-2">
        @yield('message')
    </span>
@endsection