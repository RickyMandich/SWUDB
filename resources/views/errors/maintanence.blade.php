@extends('layouts.error')
@section('code', '500')
@section('message')
    @if(Auth::admin())    
        @yield('specificMessage')
    @endif
    La pagina in manutenzione, se hai lamentele puoi contattarci a <a href='mailto:{{ 'info@' . env('APP_DOMAIN') }}'>{{ 'info@' . env('APP_DOMAIN') }}</a>
@endsection