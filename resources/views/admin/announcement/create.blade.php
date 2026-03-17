@extends('layouts.app')

@section('title', 'Invia Annuncio agli Utenti')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-envelope-open-text me-2"></i>Invia Annuncio agli Utenti</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                @foreach($breadcrumbs as $breadcrumb)
                    <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                        @if($breadcrumb['url'])
                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['text'] }}</a>
                        @else
                            {{ $breadcrumb['text'] }}
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Componi Messaggio</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.announcement.send') }}" method="POST">
                        @csrf
                        
                        <div class="mb-4">
                            <label for="subject" class="form-label font-weight-bold">Oggetto della Mail</label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}" placeholder="Es: Nuova espansione disponibile su SWUDB!" required>
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label font-weight-bold">Messaggio (Supporta testo semplice o Markdown)</label>
                            <textarea class="form-control @error('message') is-invalid @enderror" id="message" name="message" rows="12" placeholder="Scrivi qui il contenuto della mail..." required>{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i> Il messaggio verrà inviato a tutti gli utenti registrati.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between border-top pt-4">
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Annulla
                            </a>
                            <button type="submit" class="btn btn-primary px-5" onclick="return confirm('Sei sicuro di voler inviare questa mail a TUTTI gli utenti?')">
                                <i class="fas fa-paper-plane me-2"></i> Invia a tutti gli utenti
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-4 card bg-light border-info">
                <div class="card-body">
                    <h6 class="text-info"><i class="fas fa-lightbulb me-2"></i>Suggerimento</h6>
                    <small class="text-muted">
                        Puoi usare il Markdown per formattare il testo. Ad esempio: 
                        <code>**testo in grassetto**</code>, 
                        <code>[Link](https://swudb.it)</code>, 
                        <code>* lista</code> etc.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
