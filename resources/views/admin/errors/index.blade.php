<x-app-layout>
    <form method="GET" action="{{ route('admin.errors.index') }}" class="mb-4 flex gap-4">
        <select name="status" onchange="this.form.submit()">
            <option value="">Tutti</option>
            <option value="open" @selected($status === 'open')>Aperti</option>
            <option value="resolved" @selected($status === 'resolved')>Risolti</option>
            <option value="ignored" @selected($status === 'ignored')>Ignorati</option>
        </select>
    </form>

    @if ($errors->isEmpty())
    <div class="p-6 text-gray-500">Nessun errore.</div>
    @else
        @foreach ($errors as $error)
        <div class="border border-gray-200 p-4 mb-3 rounded">
            <!-- Messaggio -->
            <p>{{ $error->message }}</p>
            <!-- Sorgente -->
            <p class="font-semibold">{{ $error->source }}</p>
            <!-- Stack (solo aperto) -->
            @if ($error->status === 'open')
                <pre class="mt-2 text-xs text-gray-500">{{ $error->stack }}</pre>
            @endif
            <!-- Azioni -->
            <div class="flex gap-2 mt-3">
                <button onclick="window.location.href = '{{ route('admin.errors.update', $error)}}'">Segna come risolto</button>
                {{-- <form method="POST" action="{{ route('admin.errors.update', $error) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="resolved">
                    <input type="hidden" name="resolved_at" value="{{ now()->toISOString() }}">
                    <button class="px-3 py-1 bg-green-200 rounded">Segna come risolto</button>
                </form> --}}

                <form method="POST" action="{{ route('admin.errors.update', $error) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="ignored">
                    <button class="px-3 py-1 bg-gray-200 rounded">Ignora</button>
                </form>
            </div>
        </div>
        @endforeach
    @endif

</x-app-layout>