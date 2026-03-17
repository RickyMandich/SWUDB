@component('mail::message')
# {{ $subject }}

{{ $messageBody }}

Grazie per essere parte di SWUDB,<br>
Il Team di {{ config('app.name') }}
@endcomponent
