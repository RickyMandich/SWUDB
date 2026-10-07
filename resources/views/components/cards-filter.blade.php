@props([
    'aspects',
    'traits',
    'types',
    'expansions',
    'filters',
    'action',
    // Provisional limits: Part B of implementationPlan-filtroCarteGrafica.md replaces them with the real DB min/max
    // Limiti provvisori: la Parte B di implementationPlan-filtroCarteGrafica.md li sostituisce con i veri min/max del DB
    'bounds' => ['cost' => [0, 10], 'health' => [0, 30], 'power' => [0, 12]],
])

@php
    $sliders = ['cost' => 'costo', 'health' => 'vita', 'power' => 'potenza'];

    $hasFilters =
        collect(['nome', 'espansioni', 'tipi', 'aspetti', 'tratti', 'unique_card'])->contains(
            fn($key) => filled($filters[$key] ?? null),
        ) ||
        collect($sliders)->contains(
            fn($prefix, $key) => (int) ($filters[$prefix . '_min'] ?? $bounds[$key][0]) > $bounds[$key][0] ||
                (int) ($filters[$prefix . '_max'] ?? $bounds[$key][1]) < $bounds[$key][1],
        );
@endphp

<div x-data="{ open: {{ $hasFilters ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
        class="flex w-full items-center justify-between rounded-md bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-xs hover:bg-gray-50 dark:hover:bg-gray-700">
        <span>Filtri</span>
        <svg :class="{ 'rotate-180': open }" class="h-5 w-5 transition-transform duration-200" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" x-collapse x-cloak>
        <form action="{{ $action }}" method="get"
            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

            {{-- Testo e datalist --}}
            <div>
                <x-input-label for="nome" value="Nome" />
                <x-text-input id="nome" placeholder="Scrivi per cercare..." name="nome" type="text"
                    class="mt-1 block w-full" :value="$filters['nome'] ?? ''" />
            </div>

            <x-multi-datalist name="espansioni" label="Espansione" :options="$expansions" :selected="$filters['espansioni'] ?? []" />

            <x-multi-datalist name="tratti" label="Tratto" :options="$traits" :selected="$filters['tratti'] ?? []" />

            {{-- Badge --}}
            <div class="lg:col-span-2">
                <x-input-label value="Tipo" />
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($types as $type)
                        <span>
                            <input type="checkbox" name="tipi[]" value="{{ $type }}"
                                id="tipo-{{ Str::slug($type) }}" class="hidden peer" @checked(in_array($type, (array) ($filters['tipi'] ?? [])))>
                            <label for="tipo-{{ Str::slug($type) }}"
                                class="inline-block cursor-pointer select-none rounded-md border-2 border-gray-300 dark:border-gray-600 bg-transparent px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 peer-checked:bg-gray-600 dark:peer-checked:bg-gray-500 peer-checked:text-white dark:peer-checked:text-white">
                                {{ $type }}
                            </label>
                        </span>
                    @endforeach
                </div>
            </div>

            <div>
                <x-input-label value="Aspetto" />
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($aspects as $aspect)
                        <span>
                            <input type="checkbox" name="aspetti[]" value="{{ $aspect->id }}"
                                id="aspetto-{{ $aspect->id }}" class="hidden peer" @checked(in_array($aspect->id, (array) ($filters['aspetti'] ?? [])))>
                            <label for="aspetto-{{ $aspect->id }}"
                                class="inline-block cursor-pointer select-none rounded-md border-2 border-gray-300 dark:border-gray-600 bg-transparent px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 peer-checked:bg-gray-600 dark:peer-checked:bg-gray-500 peer-checked:text-white dark:peer-checked:text-white">
                                {{ $aspect->name }}
                            </label>
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Slider a doppio input --}}

            <x-range-slider name="costo" label="Costo" :min="$bounds['cost'][0]" :max="$bounds['cost'][1]" :selected-min="$filters['costo_min'] ?? null"
                :selected-max="$filters['costo_max'] ?? null" />

            <x-range-slider name="vita" label="Vita" :min="$bounds['health'][0]" :max="$bounds['health'][1]" :selected-min="$filters['vita_min'] ?? null"
                :selected-max="$filters['vita_max'] ?? null" />

            <x-range-slider name="potenza" label="Potenza" :min="$bounds['power'][0]" :max="$bounds['power'][1]" :selected-min="$filters['potenza_min'] ?? null"
                :selected-max="$filters['potenza_max'] ?? null" />

            {{-- Azioni --}}
            <div>
                <x-input-label value="Carte uniche" />
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ([['', 'Tutte'], ['1', 'Solo uniche'], ['0', 'Solo non uniche']] as [$value, $text])
                        <span>
                            <input type="radio" name="unique_card" value="{{ $value }}"
                                id="unica-{{ $loop->index }}" class="hidden peer" @checked((string) ($filters['unique_card'] ?? '') === $value)>
                            <label for="unica-{{ $loop->index }}"
                                class="inline-block cursor-pointer select-none rounded-md border-2 border-gray-300 dark:border-gray-600 bg-transparent px-2 py-1 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 peer-checked:bg-gray-600 dark:peer-checked:bg-gray-500 peer-checked:text-white dark:peer-checked:text-white">
                                {{ $text }}
                            </label>
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="flex items-end gap-3 lg:col-span-2">
                <x-primary-button>Cerca</x-primary-button>
                <a href="{{ route('cards.index') }}"
                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Azzera</a>
            </div>
        </form>
    </div>
</div>
