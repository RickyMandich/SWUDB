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
        <form method="POST" action="{{ route('admin.errors.bulk-update') }}">
            @csrf
            @method('PATCH')

            @foreach ($errors as $error)
                @php
                    $bgClass = match (true) {
                        $error->status === 'open' => 'bg-red-400',
                        $error->status === 'resolved' => 'bg-green-400',
                        default => 'bg-gray-400',
                    };
                @endphp
                <div class="border border-gray-200 p-4 mb-3 rounded {{ $bgClass }}">
                    <input type="checkbox" name="ids[]" value="{{ $error->id }}">
                    <p>{{ $error->message }}</p>
                    <p class="font-semibold">{{ $error->source }}</p>
                    @if ($error->status === 'open')
                        <pre class="mt-2 text-xs text-gray-500">{{ $error->stack }}</pre>
                    @endif

                    <div class="flex gap-2 mt-3">
                        <button type="submit" form="resolve-{{ $error->id }}"
                            class="px-3 py-1 bg-green-500 rounded">
                            Segna come risolto
                        </button>
                        <button type="submit" form="ignore-{{ $error->id }}" class="px-3 py-1 bg-gray-500 rounded">
                            Ignora
                        </button>
                        <button type="submit" form="reopen-{{ $error->id }}" class="px-3 py-1 bg-red-500 rounded">
                            Riapri
                        </button>
                    </div>
                </div>
            @endforeach

            <div class="flex gap-2 mt-4">
                <button type="submit" name="status" value="resolved" class="px-3 py-1 bg-green-500 rounded">
                    Risolvi selezionati
                </button>
                <button type="submit" name="status" value="ignored" class="px-3 py-1 bg-gray-500 rounded">
                    Ignora selezionati
                </button>
                <button type="submit" name="status" value="open" class="px-3 py-1 bg-red-500 rounded">
                    Riapri selezionati
                </button>
            </div>
        </form>

        {{-- Form esterni per le azioni riga-per-riga --}}
        @foreach ($errors as $error)
            <form id="resolve-{{ $error->id }}" method="POST" action="{{ route('admin.errors.update', $error) }}"
                class="hidden">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="resolved">
            </form>
            <form id="ignore-{{ $error->id }}" method="POST" action="{{ route('admin.errors.update', $error) }}"
                class="hidden">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="ignored">
            </form>
            <form id="reopen-{{ $error->id }}" method="POST" action="{{ route('admin.errors.update', $error) }}"
                class="hidden">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="open">
            </form>
        @endforeach
    @endif

</x-app-layout>
