<x-app-layout>
    {{-- Vista dettaglio — resources/views/admin/errors/show.blade.php: mostra message, stack_trace in un <pre>, e context formattato con <pre>{{ json_encode($systemError->context, JSON_PRETTY_PRINT) }}</pre> (il cast context => array gia' presente sul model lo restituisce come array PHP, va ri-serializzato per la vista). --}}
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold mb-4">Errore #{{ $systemError->id }}</h1>

        <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 mb-6 text-gray-700 dark:text-gray-300">
            <p>
                <strong>Message:</strong> {{ $systemError->message }}
            </p>
            <p>
                <strong>Stack Trace:</strong>
            </p>
            <pre class="bg-gray-100 dark:bg-gray-900 p-4 rounded mt-2 overflow-x-auto">
@if ($systemError->stack_trace)
{{ $systemError->stack_trace }}
@else
Lo Stack Trace non è presente per questo errore.
@endif
</pre>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg p-6 text-gray-700 dark:text-gray-300">
            <h2 class="text-xl font-semibold mb-4">Contesto</h2>
            <pre class="bg-gray-100 dark:bg-gray-900 p-4 rounded overflow-x-auto">
@if ($systemError->context)
{{ json_encode($systemError->context, JSON_PRETTY_PRINT) }}
@else
Nessun contesto salvato per questo errore.
@endif
</pre>
        </div>
    </div>
</x-app-layout>
