@php
    /** @var \Illuminate\Support\Collection<App\Models\SystemError> $errors */
@endphp
{{-- blade-formatter-disable --}}
<x-mail::message>
# Report Scan per Admin - UnlimitedDB
*Ci sono stati {{ $total }} errori durante lo scan*

<x-mail::table>
|Carta|Errore|apri errore|
|:-:|:-:|:-:|
@foreach ($errors as $systemError)
| {{ $systemError->message }} | {{ $systemError->context['error_message'] ?? 'Errore sconosciuto' }}|[Apri]({{ route('admin.errors.show', $systemError) }})|
@endforeach
</x-mail::table>


@if ($total > $errors->count())
Sono mostrati gli ultimi {{ $errors->count() }}: gli altri sono in [Gestione errori]({{ url('/admin/errori') }}).
@endif
</x-mail::message>
