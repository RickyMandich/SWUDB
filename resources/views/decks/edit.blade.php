@php
    $rows = $deck->cards
        ->map(
            fn($c) => [
                'card_id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'quantity' => (int) $c->pivot->quantity,
            ],
        )
        ->values();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Modifica {{ $deck->name }} <span
                class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <x-bladewind.alert type="success">{{ session('status') }}</x-bladewind.alert>
            @endif
            @if (!empty(session('deck-errors')))
                <x-bladewind.alert type="warning">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('deck-errors') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
                x-data="{ rows: @js($rows) }" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>

                {{-- Ricerca carte (CardSearch, Step 10.1): selezionando un risultato fa
                     rows.push({ card_id, name, type, quantity: 1 }). Per leader e base filtrare per tipo. --}}

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Riga-carta: da estrarre in <x-deck-card-row> (riusata anche in decks/gap) --}}
                        <template x-for="(row, i) in rows" :key="row.card_id">
                            <li
                                class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                <input type="hidden" :name="`cards[${i}][card_id]`" :value="row.card_id">
                                <input type="hidden" :name="`cards[${i}][quantity]`" :value="row.quantity">
                                <span x-text="row.name"></span>
                                <span class="text-gray-500 dark:text-gray-400" x-text="row.type"></span>
                                <span class="flex items-center gap-2">
                                    {{-- Leader e base restano a quantità 1: niente +/- --}}
                                    <template x-if="!['Leader', 'Base'].includes(row.type)">
                                        <span class="flex items-center gap-2">
                                            <button type="button" class="px-2"
                                                @click="row.quantity = Math.max(1, row.quantity - 1)">−</button>
                                            <span x-text="row.quantity"></span>
                                            <button type="button" class="px-2" @click="row.quantity++">+</button>
                                        </span>
                                    </template>
                                    <button type="button" class="text-red-600 dark:text-red-400"
                                        @click="rows.splice(i, 1)">Rimuovi</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </x-bladewind.card>

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>
            </form>

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('decks.toggle-assembled', [$deck->user->name, $deck->name]) }}">
                    @csrf @method('PATCH')
                    <x-secondary-button
                        type="submit">{{ $deck->assembled ? 'Segna come smontato' : 'Segna come assemblato' }}</x-secondary-button>
                </form>
                <form method="POST" action="{{ route('decks.create-version', [$deck->user->name, $deck->name]) }}">
                    @csrf
                    <x-secondary-button type="submit">Crea nuova versione</x-secondary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
