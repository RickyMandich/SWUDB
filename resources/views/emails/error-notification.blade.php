@extends('emails.layout', ['hideDefaultFooter' => true, 'buttonText' => 'Accedi al Sistema'])

@section('content')
    <h2 style="color: #dc3545; margin-bottom: 20px;">🚨 Errore Sistema Rilevato</h2>

    <p style="font-size: 16px; margin-bottom: 20px;">
        Si è verificato un errore sul sistema <strong>{{ config('app.name') }}</strong> che richiede la tua attenzione.
    </p>

    <div style="background-color: #495057; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #e9ecef; margin-top: 0;">Dettagli Errore</h3>
        <p style="margin: 5px 0;"><strong>Tipo:</strong> {{ $exceptionClass }}</p>
        <p style="margin: 5px 0;"><strong>Messaggio:</strong> <span style="color: #dc3545;">{{ $errorMessage }}</span></p>
        <p style="margin: 5px 0;"><strong>File:</strong> <code style="background-color: #6c757d; padding: 2px 4px; border-radius: 3px;">{{ $errorFile }}</code></p>
        <p style="margin: 5px 0;"><strong>Linea:</strong> {{ $errorLine }}</p>
        <p style="margin: 5px 0;"><strong>Timestamp:</strong> {{ $timestamp }}</p>
    </div>

    @if($requestUrl || $requestMethod || $userAgent)
    <div style="background-color: #1e3a5f; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #87ceeb; margin-top: 0;">Informazioni Request</h3>
        @if($requestUrl)
        <p style="margin: 5px 0;"><strong>URL:</strong> <a href="{{ $requestUrl }}" style="color: #87ceeb;">{{ $requestUrl }}</a></p>
        @endif
        @if($requestMethod)
        <p style="margin: 5px 0;"><strong>Metodo:</strong> <span style="background-color: #0d6efd; color: white; padding: 2px 6px; border-radius: 3px; font-size: 12px;">{{ $requestMethod }}</span></p>
        @endif
        @if($userAgent)
        <p style="margin: 5px 0;"><strong>User Agent:</strong> <small style="color: #adb5bd;">{{ $userAgent }}</small></p>
        @endif
    </div>
    @endif

    <div style="background-color: #664d03; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <h3 style="color: #ffc107; margin-top: 0;">Azioni Consigliate</h3>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li>Controlla i log del server per maggiori dettagli</li>
            <li>Verifica se l'errore è ricorrente</li>
            <li>Considera se è necessario un intervento immediato</li>
        </ul>
    </div>

    @if($systemError)
    <div style="background-color: #1e3a5f; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
        <h3 style="color: #87ceeb; margin-top: 0;">🔧 Azioni Rapide</h3>
        <p style="margin-bottom: 15px;">Gestisci questo errore direttamente:</p>

        <div style="margin-bottom: 15px;">
            <a href="{{ route('admin.errors.show', $systemError) }}"
               style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 0 5px;">
                👁️ Visualizza Dettagli
            </a>
        </div>

        <div>
            <a href="{{ url('/admin/errors/quick-action/' . $systemError->id . '/resolved') }}"
               style="display: inline-block; padding: 8px 16px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; margin: 0 5px; font-size: 14px;">
                ✅ Segna come Risolto
            </a>
            <a href="{{ url('/admin/errors/quick-action/' . $systemError->id . '/ignored') }}"
               style="display: inline-block; padding: 8px 16px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; margin: 0 5px; font-size: 14px;">
                ❌ Segna come Ignorato
            </a>
        </div>
    </div>
    @endif

    <hr style="border: none; border-top: 1px solid #dee2e6; margin: 20px 0;">

    <p style="font-size: 14px; color: #6c757d; margin-bottom: 0;">
        Questa è una notifica automatica del sistema di monitoraggio errori.<br>
        Se ricevi troppe notifiche, considera di rivedere le impostazioni di logging.
    </p>
@endsection
