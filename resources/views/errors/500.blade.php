@extends('layouts.error')
@section('code', '500')
@section('message', "pagina in manutenzione, se hai lamentele puoi contattarci a <a href='mailto:info@ {{ env('app.domain') }}'>info@ {{ env('app.domain') }}</a>")