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
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8" x-data="deckEditor(@js($editorConfig))">
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

            {{-- Ricerca: fuori dal form del mazzo, perché <x-cards-filter> è un suo <form> e i form non si annidano --}}
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">Aggiungi carte</h3>

                <x-cards-filter embedded :open="true" :aspects="$aspects" :traits="$traits" :types="$types"
                    :expansions="$expansions" :filters="[]" :bounds="$bounds" :action="route('decks.search-cards', [$deck->user->name, $deck->name])"
                    x-on:submit.prevent="search($event.target)" />

                <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600 dark:text-red-400"></p>
                <p x-show="searched && !results.length && !error" class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Nessuna carta trovata.</p>
                <ul x-show="results.length"
                    class="mt-3 max-h-96 divide-y divide-gray-200 overflow-y-auto dark:divide-gray-700">
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
                <div x-show="results.length" class="mt-3 flex items-center justify-between gap-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="`${results.length} di ${total} carte`">
                    </p>
                    <x-secondary-button type="button" x-show="page < lastPage" x-bind:disabled="loading"
                        @click="search()">Carica altre carte</x-secondary-button>
                </div>
            </x-bladewind.card>

            <form method="POST" action="{{ route('decks.sync-cards', [$deck->user->name, $deck->name]) }}"
                @submit="submitting = true" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="flex items-center justify-end gap-3">
                    <span x-show="dirty" style="display: none"
                        class="text-sm text-amber-600 dark:text-amber-400">Modifiche non salvate</span>
                    <x-primary-button>Salva modifiche</x-primary-button>
                </div>

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
                criteria: '', // query string dell'ultima ricerca: "Carica altre carte" la riusa con la pagina successiva
                error: '',
                format: config.format,
                initial: '',
                lastPage: 1,
                loading: false,
                page: 1,
                results: [],
                rows: config.rows,
                searched: false,
                seq: 0,
                submitting: false,
                total: 0,

                /**
                 * search(form): nuova ricerca con i criteri del filtro, dalla pagina 1
                 * search():     pagina successiva della ricerca in corso ("Carica altre carte")
                 */
                async search(form = null) {
                    const append = form === null;
                    if (append && (this.loading || this.page >= this.lastPage)) return;
                    if (!append) this.criteria = new URLSearchParams(new FormData(form)).toString();

                    this.error = '';
                    const params = new URLSearchParams(this.criteria);
                    params.set('page', append ? this.page + 1 : 1);
                    const current = ++this
                        .seq; // scarta le risposte arrivate fuori ordine (anche una "Carica altre" ancora in volo)
                    this.loading = true;
                    try {
                        const response = await fetch(`${config.searchUrl}?${params}`, {
                            headers: {
                                Accept: 'application/json'
                            }
                        });
                        if (!response.ok) throw new Error(response.status);
                        const data = await response.json();
                        if (current === this.seq) {
                            this.results = append ? this.results.concat(data.data) : data.data;
                            this.page = data.current_page;
                            this.lastPage = data.last_page;
                            this.total = data.total;
                            this.searched = true;
                        }
                    } catch (e) {
                        if (current === this.seq) this.error = 'Ricerca non riuscita, riprova.';
                    } finally {
                        if (current === this.seq) this.loading = false;
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
                /**
                 * Stato delle righe come stringa confrontabile: per ogni carta id e quantità, in ordine di id
                 * (l'ordine delle righe non cambia il mazzo salvato)
                 */
                snapshot() {
                    return JSON.stringify(
                        this.rows
                        .map((r) => [r.card_id, r.quantity])
                        .sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : 0))
                    );
                },

                get dirty() {
                    return this.snapshot() !== this.initial;
                },

                init() {
                    this.initial = this.snapshot();
                    window.addEventListener('beforeunload', (event) => {
                        if (this.dirty && !this.submitting) {
                            event.preventDefault();
                            event.returnValue =
                                ''; // necessario per Chrome; il testo del messaggio lo decide il browser
                        }
                    });
                },
            }));
        });
    </script>
</x-app-layout>
