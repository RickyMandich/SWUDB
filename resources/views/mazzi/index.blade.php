@extends('layouts.app')
@section('content')
        @if(count($decks) != 0)
            <ul>
                @foreach ($decks as $deck)
                    <li>
                        <a class="link-underline link-underline-opacity-0" href="/<?php echo Auth::user()->name ?>/{{ str_replace(" ", "+", $deck->nome) }}">{{ $deck->nome }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            non hai nessun mazzo (i mazzi vuoti non vengono considerati)
        @endif
@endsection