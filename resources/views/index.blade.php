@extends('layouts.app')
@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1 class="text-center mb-5">
                    {{ __("custom.welcome")}}
                </h1>

                <!-- Campo di ricerca -->
                <div class="row justify-content-center mb-5">
                    <div class="col-12 @if(Auth::check() && Auth::admin()) col-lg-12 @else col-lg-9 @endif">
                        <div class="card bg-dark border-primary">
                            <div class="card-body">
                                <h5 class="card-title text-center mb-3">
                                    <i class="fas fa-search me-2 text-primary"></i>Ricerca Rapida
                                </h5>
                                <form action="{{ route('carte') }}" method="GET">
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-primary text-white">
                                            <i class="fas fa-search"></i>
                                        </span>
                                        <input class="form-control form-control-lg"
                                               type="text"
                                               placeholder="{{ __('custom.searchCard') }}"
                                               name="nome"
                                               id="home-search"
                                               autocomplete="off">
                                        <button class="btn btn-primary btn-lg" type="submit">
                                            <i class="fas fa-arrow-right me-2"></i>Cerca
                                        </button>
                                    </div>
                                    <div class="form-text text-center mt-2 text-muted">
                                        Cerca carte per nome
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pulsanti principali -->
                <div class="row g-4 mb-5 @guest justify-content-center @else @if(!Auth::admin()) justify-content-center @endif @endguest">
                    <!-- Ricerca/Carte -->
                    <div class="col-md-4 @guest col-lg-3 @else @if(!Auth::admin()) col-lg-4 @else col-lg-3 @endif @endguest">
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
                    <div class="col-md-4 @guest col-lg-3 @else @if(!Auth::admin()) col-lg-4 @else col-lg-3 @endif @endguest">
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
                    <div class="col-md-4 @if(!Auth::admin()) col-lg-4 @else col-lg-3 @endif">
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

                    <!-- Dashboard Admin (solo se admin) -->
                    @if(Auth::check() && Auth::admin())
                    <div class="col-md-4 col-lg-3">
                        <div class="card bg-secondary h-100">
                            <div class="card-body text-center d-flex flex-column">
                                <i class="fas fa-shield-alt fa-3x mb-3 text-danger"></i>
                                <h5 class="card-title">Dashboard Admin</h5>
                                <p class="card-text flex-grow-1">Gestisci il sistema e monitora le attività</p>
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-danger">
                                    <i class="fas fa-tachometer-alt me-2"></i>Amministrazione
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
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