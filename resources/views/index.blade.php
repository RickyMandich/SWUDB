@extends('layouts.app')
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1 class="text-center mb-5">
                    {{ __("custom.welcome")}}
                </h1>

                <!-- Pulsanti principali -->
                <div class="row g-4 mb-5">
                    <!-- Ricerca/Carte -->
                    <div class="col-md-4">
                        <div class="card bg-secondary h-100">
                            <div class="card-body text-center d-flex flex-column">
                                <i class="fas fa-search fa-3x mb-3 text-primary"></i>
                                <h5 class="card-title">{{ __('custom.carte') }}</h5>
                                <p class="card-text flex-grow-1">Cerca e sfoglia tutte le carte del database</p>
                                <a href="{{ route('carte') }}" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Esplora Carte
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Mazzi -->
                    <div class="col-md-4">
                        <div class="card bg-secondary h-100">
                            <div class="card-body text-center d-flex flex-column">
                                <i class="fas fa-layer-group fa-3x mb-3 text-success"></i>
                                <h5 class="card-title">{{ __('custom.mazzi') }}</h5>
                                <p class="card-text flex-grow-1">Gestisci e condividi i tuoi mazzi</p>
                                <a href="{{ route('mazzi') }}" class="btn btn-success">
                                    <i class="fas fa-layer-group me-2"></i>Visualizza Mazzi
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Collezione (solo se loggato) -->
                    @auth
                    <div class="col-md-4">
                        <div class="card bg-secondary h-100">
                            <div class="card-body text-center d-flex flex-column">
                                <i class="fas fa-star fa-3x mb-3 text-warning"></i>
                                <h5 class="card-title">{{ __('custom.Collezione') }}</h5>
                                <p class="card-text flex-grow-1">Gestisci la tua collezione personale</p>
                                <a href="{{ route('collezione') }}" class="btn btn-warning">
                                    <i class="fas fa-star me-2"></i>La Mia Collezione
                                </a>
                            </div>
                        </div>
                    </div>
                    @endauth
                </div>

                @guest
                <!-- Sezione per utenti non loggati -->
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card bg-dark border-primary">
                            <div class="card-body text-center">
                                <h5 class="card-title text-primary">Accedi per sbloccare tutte le funzionalità</h5>
                                <p class="card-text">Registrati o accedi per gestire la tua collezione e creare mazzi personalizzati</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="{{ route('login') }}" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt me-2"></i>{{ __('custom.Login') }}
                                    </a>
                                    <a href="{{ route('register') }}" class="btn btn-outline-primary">
                                        <i class="fas fa-user-plus me-2"></i>{{ __('custom.Register') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endguest
            </div>
        </div>
    </div>
@endsection