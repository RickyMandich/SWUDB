@php
    $body = $deck->cards->reject(fn($c) => in_array($c->type, ['Leader', 'Base']));
    $groups = $body->groupBy('type')->map(fn($g) => $g->sortBy([['cost', 'asc'], ['name', 'asc']]));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $deck->name }} <span class="text-sm text-gray-500 dark:text-gray-400">v{{ $deck->version }} ·
                    {{ $deck->user->name }}</span>
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('decks.versions', [$deck->user->name, $deck->name]) }}"><x-secondary-button
                        type="button">Cronologia versioni</x-secondary-button></a>
                <a href="{{ route('decks.statistics', [$deck->user->name, $deck->name]) }}"><x-secondary-button
                        type="button">Statistiche</x-secondary-button></a>
                @can('update', $deck)
                    <a href="{{ route('decks.edit', [$deck->user->name, $deck->name]) }}"><x-primary-button
                            type="button">Modifica mazzo</x-primary-button></a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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
                    @foreach ($deck->leaders as $leader)
                        @if ($leader->front_art_path)
                            <img class="h-40 rounded-lg" src="{{ asset('storage/' . $leader->front_art_path) }}"
                                alt="{{ $leader->name }}">
                        @endif
                    @endforeach
                    @if ($deck->baseCard?->front_art_path)
                        <img class="h-40 rounded-lg" src="{{ asset('storage/' . $deck->baseCard->front_art_path) }}"
                            alt="{{ $deck->baseCard->name }}">
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
                            <li class="flex items-center justify-between py-2 text-sm">
                                <a class="text-gray-800 hover:underline dark:text-gray-100"
                                    href="{{ route('cards.show', [$card->expansion, $card->number]) }}">{{ $card->name }}</a>
                                <span class="text-gray-500 dark:text-gray-400">costo {{ $card->cost }} ·
                                    ×{{ $card->pivot->quantity }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-bladewind.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
