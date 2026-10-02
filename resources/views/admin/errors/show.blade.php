<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Errore #{{ $systemError->id }}</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto py-6 px-4">
        <x-flash-message />
        <a href="{{ route('admin.errors.index') }}" class="text-sm text-indigo-600 hover:underline">← Torna alla lista</a>

        <div class="bg-white shadow-sm rounded-lg p-6 my-4 text-gray-700">
            <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2">
                <dt class="font-semibold">Sorgente</dt>
                <dd>{{ $systemError->source }}</dd>
                <dt class="font-semibold">Stato</dt>
                <dd><x-badge :color="$systemError->statusColor()">{{ $systemError->status }}</x-badge></dd>
                <dt class="font-semibold">Creato il</dt>
                <dd>{{ $systemError->created_at?->format('d/m/Y H:i') }}</dd>
                <dt class="font-semibold">Risolto il</dt>
                <dd>{{ $systemError->resolved_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt class="font-semibold">Messaggio</dt>
                <dd>{{ $systemError->message }}</dd>
            </dl>

            <div class="flex flex-wrap gap-2 mt-4">
                @foreach (['resolved' => 'Segna come risolto', 'ignored' => 'Ignora', 'open' => 'Riapri'] as $newStatus => $label)
                    @if ($systemError->status !== $newStatus)
                        <form method="POST" action="{{ route('admin.errors.update', $systemError) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $newStatus }}">
                            <x-secondary-button type="submit">{{ $label }}</x-secondary-button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6 mb-6 text-gray-700">
            <h3 class="text-lg font-semibold mb-2">Stack trace</h3>
            <pre class="bg-gray-100 p-4 rounded text-sm overflow-x-auto">{{ $systemError->stack_trace ?? 'Nessuno stack trace per questo errore.' }}</pre>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6 text-gray-700">
            <h3 class="text-lg font-semibold mb-2">Contesto</h3>
            <pre class="bg-gray-100 p-4 rounded text-sm overflow-x-auto">{{ $systemError->context ? json_encode($systemError->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Nessun contesto salvato per questo errore.' }}</pre>
        </div>
    </div>
</x-app-layout>
