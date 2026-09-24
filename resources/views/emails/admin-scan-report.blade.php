@php /** @var \Illuminate\Support\Collection<App\Models\SystemError> $errors */ @endphp
{{-- blade-formatter-disable --}}
<x-mail::message>
# Report Scan per Admin - UnlimitedDB

*Ci sono stati {{ $errors->count() }} errori durante lo scan*

<x-mail::table>
    |Carta|Errore|apri errore|
    |:-:|:-:|:-:|
    @foreach ($errors as $error)
        | {{ $error->message }} | {{ $error->context['error'] }} | [Apri]( {{ route('admin.errors.show', $error) }} ) |
    @endforeach
</x-mail::table>
</x-mail::message>