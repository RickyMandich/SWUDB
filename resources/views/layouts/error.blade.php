@extends('layouts.app')
@section('content-class', 'align-middle d-flex flex-column justify-content-center align-items-center')
@section('content')
    @if(Auth::admin())
        <div class="fs-2 text-danger">
            Errore @yield('code'):
        </div>
    @endif
    <div class="fs-2">
        @yield('message')
    </div>
    
    @php
        use Illuminate\Foundation\Inspiring;
        $quote = Inspiring::quote();
        // Estrai il testo e l'autore dalla citazione formattata
        preg_match('/" (.+) ".*— (.+)/s', $quote, $matches);
        $quoteText = $matches[1] ?? '';
        $quoteAuthor = $matches[2] ?? '';
    @endphp
    
    <div class="mt-5 text-center" style="max-width: 600px;">
        <div class="card bg-dark border-warning">
            <div class="card-body">
                <blockquote class="blockquote mb-0">
                    <p class="fs-5 fst-italic text-warning">"{{ $quoteText }}"</p>
                    <footer class="blockquote-footer text-light mt-2">
                        <cite title="Source Title">{{ $quoteAuthor }}</cite>
                    </footer>
                </blockquote>
            </div>
        </div>
    </div>
@endsection

