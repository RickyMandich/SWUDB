<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @include('layouts.favicons')

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <a href="/">
                <x-application-logo class="w-64 h-64 fill-current text-gray-500" />
            </a>
        </div>

        <div>
            <a href="{{ route('login') }}"
                class="{{ request()->routeIs('login') ? 'pointer-events-none opacity-50' : 'hover:bg-blue-700' }}">
                <button
                    class="{{ request()->routeIs('login') ? 'bg-blue-900 text-white' : 'bg-blue-500 text-black' }} font-bold py-2 px-4 rounded">
                    Login
                </button>
            </a>
            <a href="{{ route('register') }}"
                class="{{ request()->routeIs('register') ? 'pointer-events-none opacity-50' : 'hover:bg-blue-700' }}">
                <button
                    class="{{ request()->routeIs('register') ? 'bg-blue-900 text-white' : 'bg-blue-500 text-black' }} font-bold py-2 px-4 rounded">
                    Registrati
                </button>
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            {{ $slot }}
        </div>
    </div>
</body>

</html>
