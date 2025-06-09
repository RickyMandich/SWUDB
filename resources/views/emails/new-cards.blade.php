@extends('emails.layout')
@section('content')
    <h2 style="color: #2c3e50;">Nuove carte disponibili</h2>
    <p style="font-size: 16px;">{!! nl2br(e($messaggio)) !!}</p>
@endsection
