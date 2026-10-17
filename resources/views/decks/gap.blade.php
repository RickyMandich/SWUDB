@php
    // chiavi di $quantities, titolo, colore del badge ed etichetta: una sezione per ogni lista mostrata
    $sections =
        $mode === 'presenti'
            ? [
                [
                    'key' => 'present',
                    'title' => 'Copie possedute e libere',
                    'color' => '#16a34a',
                    'label' => 'Presente',
                    'empty' => 'Nessuna carta del mazzo è tra le copie libere della tua collezione.',
                ],
            ]
            : [
                [
                    'key' => 'missing',
                    'title' => 'Mancanti del tutto',
                    'color' => '#dc2626',
                    'label' => 'Mancante',
                    'empty' => 'Non ti manca nessuna carta: hai tutto quello che serve.',
                ],
                [
                    'key' => 'reserved',
                    'title' => 'Impegnate in altri mazzi assemblati',
                    'color' => '#d97706',
                    'label' => 'In un altro mazzo',
                    'empty' => 'Nessuna carta di questo mazzo è impegnata in un altro mazzo assemblato.',
                ],
            ];

    $tabs = ['mancanti' => 'Carte mancanti', 'presenti' => 'Carte presenti'];

    $tiles = [
        'Richieste dal mazzo' => $summary['needed'],
        'Presenti' => $summary['present'],
        'Impegnate altrove' => $summary['reserved'],
        'Mancanti' => $summary['missing'],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Carte per {{ $deck->name }} <span
                    class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }}
                    · {{ $deck->user->name }}</span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('decks.show', [$deck->user->name, $deck->name, $deck->version]) }}">
                    <x-secondary-button type="button">Torna al mazzo</x-secondary-button>
                </a>
                <a href="{{ route('collection.index') }}">
                    <x-secondary-button type="button">La mia collezione</x-secondary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Il confronto usa la <strong>tua</strong> collezione, anche se il mazzo è di un altro utente.
            </p>

            {{-- Riepilogo in copie --}}
            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($tiles as $label => $value)
                    <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            {{-- Toggle mancanti / presenti: due link GET, la scelta resta nell'URL --}}
            <nav aria-label="Modalità"
                class="inline-flex overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('decks.gap', ['username' => $deck->user->name, 'deckname' => $deck->name, 'version' => $deck->version, 'mostra' => $key]) }}"
                        @if ($mode === $key) aria-current="page" @endif
                        class="px-4 py-2 text-sm font-medium transition-colors {{ $mode === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' }} {{ $loop->first ? '' : 'border-l border-gray-200 dark:border-gray-700' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @foreach ($sections as $section)
                {{-- carte della sezione, nell'ordine di default di $cards --}}
                @php($rows = $cards->filter(fn($card) => isset($quantities[$section['key']][$card->id])))
                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">{{ $section['title'] }}</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $rows->count() }} carte · {{ array_sum($quantities[$section['key']]) }} copie
                        </span>
                    </div>

                    @if ($rows->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $section['empty'] }}</p>
                    @else
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($rows as $card)
                                <x-deck-card-row :card="$card" :quantity="$quantities[$section['key']][$card->id]">
                                    <x-badge :color="$section['color']">{{ $section['label'] }}</x-badge>
                                </x-deck-card-row>
                            @endforeach
                        </ul>
                    @endif
                </x-bladewind.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
