<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Gestione Errori di Sistema</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4">
        <x-flash-message />

        <form method="GET" action="{{ route('admin.errors.index') }}" class="mb-4">
            <x-select-input name="status" onchange="this.form.submit()">
                <option value="">Tutti</option>
                <option value="open" @selected($status === 'open')>Aperti</option>
                <option value="resolved" @selected($status === 'resolved')>Risolti</option>
                <option value="ignored" @selected($status === 'ignored')>Ignorati</option>
            </x-select-input>
        </form>

        @if ($systemErrors->isEmpty())
            <div class="p-6 text-gray-500 dark:text-gray-400">Nessun errore.</div>
        @else
            <form method="POST" action="{{ route('admin.errors.bulk-update') }}">
                @csrf
                @method('PATCH')
                <x-input-error :messages="$errors->get('ids')" class="mb-2" />

                @foreach ($systemErrors as $error)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 mb-3 rounded">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="ids[]" value="{{ $error->id }}"
                                class="mt-1 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <x-badge :color="$error->statusColor()">{{ $error->status }}</x-badge>
                                    <span class="font-semibold">{{ $error->source }}</span>
                                </div>
                                <p class="mt-1">{{ $error->message }}</p>

                                <div class="flex flex-wrap gap-2 mt-3">
                                    @if ($error->status === 'open')
                                        <button type="submit" form="resolve-{{ $error->id }}"
                                            class="px-3 py-1 bg-green-600 text-white rounded">Segna come
                                            risolto</button>
                                        <button type="submit" form="ignore-{{ $error->id }}"
                                            class="px-3 py-1 bg-gray-600 text-white rounded">Ignora</button>
                                    @else
                                        <button type="submit" form="reopen-{{ $error->id }}"
                                            class="px-3 py-1 bg-red-600 text-white rounded">Riapri</button>
                                    @endif
                                    <a href="{{ route('admin.errors.show', $error) }}"
                                        class="px-3 py-1 bg-blue-600 text-white rounded">Mostra</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="submit" name="status" value="resolved"
                        class="px-3 py-1 bg-green-600 text-white rounded">Risolvi selezionati</button>
                    <button type="submit" name="status" value="ignored"
                        class="px-3 py-1 bg-gray-600 text-white rounded">Ignora selezionati</button>
                    <button type="submit" name="status" value="open"
                        class="px-3 py-1 bg-red-600 text-white rounded">Riapri selezionati</button>
                </div>
            </form>

            {{ $systemErrors->links() }}

            {{-- Form esterni per le azioni riga-per-riga (un <form> non può stare dentro un altro) --}}
            @foreach ($systemErrors as $error)
                @foreach (['resolve' => 'resolved', 'ignore' => 'ignored', 'reopen' => 'open'] as $prefix => $newStatus)
                    <form id="{{ $prefix }}-{{ $error->id }}" method="POST"
                        action="{{ route('admin.errors.update', $error) }}" class="hidden">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $newStatus }}">
                    </form>
                @endforeach
            @endforeach
        @endif
    </div>
</x-app-layout>
