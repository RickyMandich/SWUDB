<!doctype html>
<html data-bs-theme="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'SWUDB'))</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- inclusion -->
    @yield('include')

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<style>
    .bg-custom-light{
        background-color: #555555;
    }
    nav{
        z-index: 1021;
    }
    a{
        text-decoration: none;
        color: inherit;
    }
</style>
<body>
    <div id="app" class="d-flex flex-column justify-content-between min-vh-100">
        <nav class="navbar navbar-expand-md shadow-sm bg-custom-light">
            <div class="container">
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config('app.domain', 'SWUDB.net') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('mazzi') }}">{{ __('custom.mazzi') }}</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('carte') }}">{{ __('custom.carte') }}</a>
                        </li>
                        <li class="nav-item">
                            <form action="{{ route('carte') }}">
                                <input class="form-control" type="text" placeholder="{{ __("custom.searchCard") }}" name="nome" id="nome">
                            </form>
                        </li> 
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('custom.Login') }}</a>
                                </li>
                            @endif

                            @if (Route::has('register'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('register') }}">{{ __('custom.Register') }}</a>
                                </li>
                            @endif
                        @else
                        <li class="nav-item dropdown">
                            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                {{ Auth::user()->name }}
                            </a>

                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="{{ route('dashboard') }}">
                                    {{ __('custom.Dashboard') }}
                                </a>

                                <a class="dropdown-item" href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                                 document.getElementById('logout-form').submit();">
                                    {{ __('custom.Logout') }}
                                </a>

                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4 flex-grow-1">
            <div class="container content @yield('content-class')">
                @yield('content')
            </div>
        </main>

        <footer class="bg-custom-light mt-auto pt-2">
            <div class="container">
                <div class="row">
                    <div class="col text-center">
                        <p>{{ __("custom.upperFooter") }}</p>
                        <p>{{ __("custom.lowerFooter") }} <a href="/docs/tos">Terms of Service<a>.</p>
                    </div>
                </div>
            </div>
            <div class="container">
                <div class="row">
                    <div class="col text-center">
                        <p>
                            Created by 
                            <small class="text-muted text-uppercase">
                                Mandich Riccardo
                            </small>
                            <br>
                            with
                            <small class="text-muted text-uppercase">
                                <a href="https://laravel.com/docs/12.x">laravel</a>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
@yield('script')
</html>