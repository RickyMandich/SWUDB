@component('mail::message')
# Test Fallito: {{ $testResult->test_name }}

Si è verificato un errore durante l'esecuzione dei test pre-scansione.

**Dettagli:**
- **Test:** {{ $testResult->test_name }}
- **Data:** {{ $testResult->created_at->format('d/m/Y H:i:s') }}
- **ID Esecuzione:** {{ $testResult->run_id }}

**Output Errore:**
@component('mail::panel')
{{ $testResult->output }}
@endcomponent

La scansione delle carte è stata bloccata per prevenire corruzione dei dati.

@component('mail::button', ['url' => config('app.url') . '/admin/tests'])
Visualizza Report Completo
@endcomponent

Grazie,<br>
{{ config('app.name') }}
@endcomponent
