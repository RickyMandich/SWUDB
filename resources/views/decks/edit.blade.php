@php
    $rows = $deck->cards
        ->map(
            fn($c) => [
                'card_id' => $c->id,
                'name' => $c->title ? "{$c->name}, {$c->title}" : $c->name,
                'type' => $c->type,
                'quantity' => (int) $c->pivot->quantity,
            ],
        )
        ->values();
    $editorConfig = [
        'rows' => $rows,
        'format' => $deck->format->value,
        'searchUrl' => route('decks.search-cards', [$deck->user->name, $deck->name]),
    ];
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
            @if (!empty(session('import-warnings')))
                <x-bladewind.alert type="warning">
                    Alcune righe dell'import non sono state riconosciute e sono state saltate:
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('import-warnings') as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
                x-data="deckEditor(@js($editorConfig))" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex justify-end"><x-primary-button>Salva modifiche</x-primary-button></div>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <x-input-label for="card-search" value="Aggiungi carte" />
                    <div class="mt-1 flex gap-2">
                        <input id="card-search" type="search" x-model="query" @input.debounce.300ms="search()"
                            @keydown.enter.prevent="search()" placeholder="Nome della carta (almeno 2 lettere)"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <select x-model="type" @change="search()"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">Tutti i tipi</option>
                            @foreach (['Leader', 'Base', 'Unità', 'Evento', 'Miglioria'] as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>
                    <p x-show="searched && !results.length && !error"
                        class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nessuna carta trovata.</p>
                    <ul x-show="results.length" class="mt-3 divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="card in results" :key="card.id">
                            <li
                                class="flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100">
                                <span class="flex items-center gap-3">
                                    <img x-show="card.image" :src="card.image" :alt="card.name"
                                        class="h-10 rounded" loading="lazy">
                                    <span x-text="card.label"></span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <span class="text-gray-500 dark:text-gray-400" x-text="card.type"></span>
                                    <button type="button" class="font-medium text-indigo-600 dark:text-indigo-400"
                                        @click="add(card)">Aggiungi</button>
                                </span>
                            </li>
                        </template>
                    </ul>
                </x-bladewind.card>

                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <p x-show="!rows.length" class="text-sm text-gray-500 dark:text-gray-400">Il mazzo è vuoto: cerca
                        una carta qui sopra.</p>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- Riga-carta inline: l'estrazione in <x-deck-card-row> è rimandata al piano 08 (Step 8.3.4) --}}
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

            {{-- Form separati dal principale: i form non si annidano --}}
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

    {{-- Il layout non ha @stack('scripts'): il componente sta qui. Alpine parte da resources/js/app.js (modulo differito),
         quindi questo listener `alpine:init` viene registrato prima dell'avvio. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('deckEditor', (config) => ({
                rows: config.rows,
                format: config.format,
                query: '',
                type: '',
                results: [],
                searched: false,
                error: '',
                seq: 0,

                async search() {
                    this.error = '';
                    if (this.query.trim().length < 2) {
                        this.results = [];
                        this.searched = false;
                        return;
                    }
                    const params = new URLSearchParams({
                        nome: this.query.trim()
                    });
                    if (this.type) params.append('tipi[]', this.type);
                    const current = ++this.seq; // scarta le risposte arrivate fuori ordine
                    try {
                        const response = await fetch(`${config.searchUrl}?${params}`, {
                            headers: {
                                Accept: 'application/json'
                            }
                        });
                        if (!response.ok) throw new Error(response.status);
                        const data = await response.json();
                        if (current === this.seq) {
                            this.results = data;
                            this.searched = true;
                        }
                    } catch (e) {
                        if (current === this.seq) this.error = 'Ricerca non riuscita, riprova.';
                    }
                },

                add(card) {
                    const single = ['Leader', 'Base'].includes(card.type);
                    const existing = this.rows.find((r) => r.card_id === card.id);
                    if (existing) {
                        if (!single) existing.quantity++;
                        return;
                    }
                    if (single) {
                        const max = card.type === 'Leader' && this.format === 'twin_suns' ? 2 : 1;
                        const same = this.rows.filter((r) => r.type === card.type);
                        if (same.length >= max) {
                            this.rows.splice(this.rows.indexOf(same[0]),
                            1); // sostituisce il più vecchio
                        }
                    }
                    this.rows.push({
                        card_id: card.id,
                        name: card.title ? `${card.name}, ${card.title}` : card.name,
                        type: card.type,
                        quantity: 1,
                    });
                },
            }));
        });
    </script>
</x-app-layout>
