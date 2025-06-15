<x-mail::message>
# 🚨 Errore Sistema Rilevato

Si è verificato un errore sul sistema **{{ config('app.name') }}** che richiede la tua attenzione.

## Dettagli Errore

**Tipo:** {{ $exceptionClass }}  
**Messaggio:** {{ $errorMessage }}  
**File:** `{{ $errorFile }}`  
**Linea:** {{ $errorLine }}  
**Timestamp:** {{ $timestamp }}

## Informazioni Request

@if($requestUrl)
**URL:** {{ $requestUrl }}  
@endif

@if($requestMethod)
**Metodo:** {{ $requestMethod }}  
@endif

@if($userAgent)
**User Agent:** {{ $userAgent }}  
@endif

## Azioni Consigliate

- Controlla i log del server per maggiori dettagli
- Verifica se l'errore è ricorrente
- Considera se è necessario un intervento immediato

<x-mail::button :url="config('app.url')" color="primary">
Accedi al Sistema
</x-mail::button>

---

Questa è una notifica automatica del sistema di monitoraggio errori.  
Se ricevi troppe notifiche, considera di rivedere le impostazioni di logging.

Grazie,  
{{ config('app.name') }} Team
</x-mail::message>
