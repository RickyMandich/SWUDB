@extends('emails.layout', ['hideDefaultFooter' => true, 'buttonText' => 'Accedi al Sistema'])

@section('content')
    <h2 style="color: #dc3545; margin-bottom: 20px;">🚨 Errore Sistema Rilevato</h2>

    <p style="font-size: 16px; margin-bottom: 20px;">
        Si è verificato un errore sul sistema <strong>{{ config('app.name') }}</strong> che richiede la tua attenzione.
    </p>

    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #495057; margin-top: 0;">Dettagli Errore</h3>
        <p style="margin: 5px 0;"><strong>Tipo:</strong> {{ $exceptionClass }}</p>
        <p style="margin: 5px 0;"><strong>Messaggio:</strong> <span style="color: #dc3545;">{{ $errorMessage }}</span></p>
        <p style="margin: 5px 0;"><strong>File:</strong> <code style="background-color: #e9ecef; padding: 2px 4px; border-radius: 3px;">{{ $errorFile }}</code></p>
        <p style="margin: 5px 0;"><strong>Linea:</strong> {{ $errorLine }}</p>
        <p style="margin: 5px 0;"><strong>Timestamp:</strong> {{ $timestamp }}</p>
    </div>

    @if($requestUrl || $requestMethod || $userAgent)
    <div style="background-color: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #1976d2; margin-top: 0;">Informazioni Request</h3>
        @if($requestUrl)
        <p style="margin: 5px 0;"><strong>URL:</strong> <a href="{{ $requestUrl }}" style="color: #1976d2;">{{ $requestUrl }}</a></p>
        @endif
        @if($requestMethod)
        <p style="margin: 5px 0;"><strong>Metodo:</strong> <span style="background-color: #2196f3; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">{{ $requestMethod }}</span></p>
        @endif
        @if($userAgent)
        <p style="margin: 5px 0;"><strong>User Agent:</strong> <small style="color: #666;">{{ $userAgent }}</small></p>
        @endif
    </div>
    @endif

    <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #856404; margin-top: 0;">Azioni Consigliate</h3>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Controlla i log del server per maggiori dettagli</li>
            <li>Verifica se l'errore è ricorrente</li>
            <li>Considera se è necessario un intervento immediato</li>
        </ul>
    </div>

    <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">

    <p style="font-size: 14px; color: #6c757d; margin-bottom: 0;">
        Questa è una notifica automatica del sistema di monitoraggio errori.<br>
        Se ricevi troppe notifiche, considera di rivedere le impostazioni di logging.
    </p>
@endsection
