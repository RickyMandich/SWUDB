@php
    $body = $deck->cards->reject(fn($c) => in_array($c->type, ['Leader', 'Base']));

    $groupOrder = [
        'Unità' => 1,
        'Miglioria' => 2,
        'Migliorie' => 2,
        'Evento' => 3,
        'Eventi' => 3,
    ];

    $groups = $body
        ->groupBy('type')
        ->sortBy(fn($cards, $key) => $groupOrder[$key] ?? 99)
        ->map(fn($g) => $g->sortBy([['cost', 'asc'], ['name', 'asc']]));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $deck->name }} <span class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }} ·
                    {{ $deck->user->name }}</span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('decks.versions', [$deck->user->name, $deck->name]) }}">
                    <x-secondary-button type="button">Cronologia versioni</x-secondary-button></a>
                <a href="#statistiche">
                    <x-secondary-button type="button">Statistiche</x-secondary-button>
                </a>
                <a href="{{ route('decks.export', [$deck->user->name, $deck->name, 'txt', $deck->version]) }}">
                    <x-secondary-button type="button">Esporta TXT</x-secondary-button>
                </a>
                <a href="{{ route('decks.export', [$deck->user->name, $deck->name, 'json', $deck->version]) }}">
                    <x-secondary-button type="button">Esporta JSON</x-secondary-button>
                </a>
                @can('update', $deck)
                    @if ($isLatest)
                        <a href="{{ route('decks.edit', [$deck->user->name, $deck->name]) }}"><x-primary-button
                                type="button">Modifica mazzo</x-primary-button></a>
                    @else
                        <a
                            href="{{ route('decks.edit', [$deck->user->name, $deck->name, 'base_version' => $deck->version]) }}"><x-primary-button
                                type="button">Copia come nuovo mazzo</x-primary-button></a>
                    @endif
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="deckCardPreview()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @unless ($isLatest)
                <x-bladewind.alert type="info">
                    Stai consultando la versione v{{ $deck->version }}, in sola lettura.
                    <a class="underline" href="{{ route('decks.show', [$deck->user->name, $deck->name]) }}">Vai all'ultima
                        versione</a>
                </x-bladewind.alert>
            @endunless
            @if (!empty($validationErrors))
                <x-bladewind.alert type="warning">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) $validationErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-4">
                    @forelse ($deck->leaders as $leader)
                        @if ($leader->front_art_path)
                            <a href="{{ route('cards.show', [$leader->expansion, $leader->number]) }}"
                               class="transition-transform hover:scale-105">
                                <img class="h-40 rounded-lg shadow" src="{{ asset('storage/' . $leader->front_art_path) }}"
                                    alt="{{ $leader->name }}">
                            </a>
                        @endif
                    @empty
                        <span class="text-gray-500 dark:text-gray-400">Nessun leader.</span>
                    @endforelse

                    @php($base = $deck->baseCard->first())
                    @if ($base?->front_art_path)
                        <a href="{{ route('cards.show', [$base->expansion, $base->number]) }}"
                           class="transition-transform hover:scale-105">
                            <img class="h-40 rounded shadow" src="{{ asset('storage/' . $base->front_art_path) }}"
                                alt="{{ $base->name }}">
                        </a>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">Nessuna base.</span>
                    @endif
                </div>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ $deck->format->value }} · {{ $body->sum('pivot.quantity') }} carte
                </p>
            </x-bladewind.card>

            @foreach ($groups as $type => $cards)
                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <h3 class="mb-3 font-semibold text-gray-800 dark:text-gray-100">{{ $type }}
                        ({{ $cards->sum('pivot.quantity') }})</h3>
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($cards as $card)
                            @php($cardImg = $card->front_art_path ? asset('storage/' . $card->front_art_path) : '')
                            @php($cardUrl = route('cards.show', [$card->expansion, $card->number]))
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <a class="text-gray-800 hover:underline dark:text-gray-100 font-medium truncate flex-1 min-w-0"
                                    href="{{ $cardUrl }}"
                                    @mouseenter="showHover('{{ $cardImg }}', '{{ addslashes($card->name) }}', $event)"
                                    @mousemove="moveHover($event)"
                                    @mouseleave="hideHover()"
                                    @click="handleCardClick('{{ $cardImg }}', '{{ addslashes($card->name) }}', '{{ $cardUrl }}', $event)">
                                    {{ $card->name }}
                                </a>
                                <div class="flex items-center gap-2.5 shrink-0">
                                    @if ($card->arena)
                                        @php($isSpace = in_array(strtolower($card->arena), ['spaziale', 'space']))
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $isSpace ? 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' }}">
                                            {{ $card->arena }}
                                        </span>
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400">costo {{ $card->cost }} ·
                                        ×{{ $card->pivot->quantity }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-bladewind.card>
            @endforeach

            {{-- Statistiche incorporate a fondo pagina --}}
            @include('decks.partials.statistics')

            {{-- Componente anteprima carta (desktop hover + mobile modal) --}}
            @include('decks.partials.card-preview')
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('deckCardPreview', () => ({
                preview: {
                    hover: false,
                    modal: false,
                    image: '',
                    name: '',
                    url: '',
                    x: 0,
                    y: 0,
                },
                showHover(image, name, event) {
                    if (!image || !window.matchMedia('(hover: hover)').matches) return;
                    this.preview.image = image;
                    this.preview.name = name;
                    this.preview.hover = true;
                    this.updatePosition(event);
                },
                moveHover(event) {
                    if (this.preview.hover) {
                        this.updatePosition(event);
                    }
                },
                hideHover() {
                    this.preview.hover = false;
                },
                updatePosition(e) {
                    const w = 240;
                    const h = 340;
                    const p = 16;
                    let x = e.clientX + 20;
                    if (x + w > window.innerWidth - p) {
                        x = e.clientX - w - 20;
                    }
                    let y = e.clientY;
                    const minY = h / 2 + p;
                    const maxY = window.innerHeight - (h / 2) - p;
                    y = Math.max(minY, Math.min(maxY, y));
                    this.preview.x = x;
                    this.preview.y = y;
                },
                handleCardClick(image, name, url, event) {
                    if (window.matchMedia('(hover: none)').matches || window.innerWidth < 768) {
                        event.preventDefault();
                        this.preview.image = image;
                        this.preview.name = name;
                        this.preview.url = url;
                        this.preview.hover = false;
                        this.preview.modal = true;
                    }
                },
                closeModal() {
                    this.preview.modal = false;
                }
            }));
        });
    </script>
</x-app-layout>
