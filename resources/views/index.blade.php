@extends('layouts.app')
@section('content')
    <h1 class="text-center ">
        {{ __("Welcome on") . " " . config('app.domain', 'SWUDB.net') }}
    </h1>
@endsection