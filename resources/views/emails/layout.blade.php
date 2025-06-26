<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }} - Notifica</title>
</head>
<body style="background-color: #212529; font-family: Arial, sans-serif; padding: 30px; color: #e9ecef;">
    <div style="max-width: 600px; margin: auto; background: #343a40; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.3);">
        @yield('content')

        @if(!isset($hideDefaultFooter))
        <p style="margin-top: 20px;">Siamo felici di averti con noi. Se hai domande o problemi, non esitare a contattarci.</p>
        @endif

        <a href="{{ url('/') }}" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 4px;">
            @if(isset($buttonText))
                {{ $buttonText }}
            @else
                Torna al sito
            @endif
        </a>
    </div>
    <p style="text-align: center; font-size: 12px; color: #6c757d; margin-top: 40px;">
        © {{ now()->year }} {{ config('app.name') }}. Tutti i diritti riservati.
    </p>
</body>
</html>
