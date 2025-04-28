@extends('layouts.app')
@section('include')
    <script src="https://cdn.jsdelivr.net/npm/@simondmc/popup-js@1.4.2/popup.min.js"></script>
@endsection
@section('content')
    <button class="btn btn-primary" onclick="openCreaMazzo()">
        Crea Mazzo
    </button>
    <br>
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
@section('script')
    <script>
        const popup = new Popup({
            title: 'Crea Mazzo',
            content: 
            `<form action={{ route('mazzo.create') }} method="POST">
                @csrf
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome Mazzo</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                <div class="mb-3">
                    <label for="public" class="form-label">pubblico</label>
                    <input type="checkbox" class="form-check-input" id="public" name="public">
                </div>
                <button type="submit" class="btn btn-primary">Crea Mazzo</button>`,
            buttons: [
                {
                    text: 'x',
                    className: 'btn btn-secondary',
                    onClick: function() {
                        popup.close();
                    }
                }
            ]
        });

        function openCreaMazzo() {
            popup.show();
        }
    </script>
@endsection