@extends('emails.layout')
@section('button_url', $buttonUrl ?? route('admin.expansions'))
@section('content')
    <h2 style="color: #2c3e50;">🆕 Nuova espansione rilevata!</h2>
    <p style="font-size: 16px;">È stata rilevata una nuova espansione nel sistema:</p>

    <div style="margin: 20px 0; padding: 20px; background-color: #495057; border-left: 4px solid #28a745; border-radius: 8px;">
        <h3 style="color: #28a745; margin-top: 0; font-size: 20px;">
            📦 {{ $expansion['espansione'] ?? 'N/A' }}
        </h3>
        
        <div style="margin: 15px 0;">
            @if(isset($expansion['uscita']))
                <p style="margin: 5px 0; font-size: 14px;">
                    <strong style="color: #ffc107;">📅 Data di uscita:</strong> 
                    <span style="color: #e9ecef;">{{ $expansion['uscita'] }}</span>
                </p>
            @endif
            
            @if(isset($expansion['rotazione']))
                <p style="margin: 5px 0; font-size: 14px;">
                    <strong style="color: #ffc107;">🔄 Rotazione:</strong> 
                    <span style="color: #e9ecef;">{{ $expansion['rotazione'] }}</span>
                </p>
            @endif
            
            <p style="margin: 5px 0; font-size: 14px;">
                <strong style="color: #ffc107;">✅ Stato:</strong> 
                <span style="color: #dc3545;">Non confermata</span>
            </p>
        </div>

        @if(isset($expansion['cards_url']))
            <div style="margin-top: 15px;">
                <a href="{{ $expansion['cards_url'] }}" 
                   style="display: inline-block; padding: 8px 16px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; margin-right: 10px;">
                    🔍 Visualizza carte di questa espansione
                </a>
            </div>
        @endif
    </div>

    <div style="background-color: #6c757d; padding: 15px; border-radius: 6px; margin: 20px 0;">
        <h4 style="color: #ffc107; margin-top: 0; font-size: 16px;">⚠️ Azione richiesta</h4>
        <p style="margin: 8px 0; font-size: 14px; color: #e9ecef;">
            Questa espansione è stata creata automaticamente e necessita di conferma. 
            Verifica i dettagli e conferma l'espansione dalla pagina di gestione.
        </p>
    </div>

    <p style="margin-top: 20px; font-size: 14px; color: #adb5bd;">
        💡 <strong>Suggerimento:</strong> Controlla che la data di uscita e la rotazione siano corrette prima di confermare l'espansione.
    </p>
@endsection
