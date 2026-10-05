@props(['aspects', 'traits', 'types', 'expansions', 'filters', 'action'])

<div x-data="{ open: {{ collect($filters)->filter()->isNotEmpty() ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
        class="flex w-full items-center justify-between rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
        <span>Filtri</span>
        <svg :class="{ 'rotate-180': open }" class="h-5 w-5 transition-transform duration-200" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" x-collapse x-cloak>
        <form action="{{ $action }}" method="get"
            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-input-label for="nome" value="Nome" />
                <x-text-input id="nome" name="nome" type="text" class="mt-1 block w-full"
                    :value="$filters['nome'] ?? ''" />
            </div>

            <div class="flex flex-wrap gap-2">
                <x-input-label class="d-block" for="espansione" value="Espansione" />
                @foreach ($expansions as $expansion)
                    <span>
                        <input type="checkbox" name="espansioni[]" value="{{ $expansion }}" id="{{ $expansion }}"
                            class="hidden peer" @checked(in_array($expansion, (array) ($filters['espansioni'] ?? [])))>
                        <label for="{{ $expansion }}"
                            class="inline-block px-2 py-1 text-sm font-medium text-gray-700 bg-transparent border-2 rounded-md hover:bg-gray-50 peer-checked:bg-gray-600 cursor-pointer peer-checked:text-white">
                            {{ $expansion }}
                        </label>
                    </span>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                <x-input-label class="d-block" for="tipo" value="Tipo" />
                @foreach ($types as $type)
                    <span>
                        <input type="checkbox" name="tipi[]" value="{{ $type }}" id="{{ $type }}"
                            class="hidden peer" @checked(in_array($type, (array) ($filters['tipi'] ?? [])))>
                        <label for="{{ $type }}"
                            class="inline-block px-2 py-1 text-sm font-medium text-gray-700 bg-transparent border-2 rounded-md hover:bg-gray-50 peer-checked:bg-gray-600 cursor-pointer peer-checked:text-white">
                            {{ $type }}
                        </label>
                    </span>
                @endforeach
            </div>

            <div>
                <x-input-label for="costo" value="Costo" />
                <x-text-input id="costo" name="costo" type="number" min="0" class="mt-1 block w-full"
                    :value="$filters['costo'] ?? ''" />
            </div>

            <div class="flex flex-wrap gap-2">
                <x-input-label class="d-block" for="aspetto" value="Aspetto" />
                @foreach ($aspects as $aspect)
                    <span>
                        <input type="checkbox" name="aspetti[]" value="{{ $aspect->id }}" id="{{ $aspect->id }}"
                            class="hidden peer" @checked(in_array($aspect->id, (array) ($filters['aspetti'] ?? [])))>
                        <label for="{{ $aspect->id }}"
                            class="inline-block px-2 py-1 text-sm font-medium text-gray-700 bg-transparent border-2 rounded-md hover:bg-gray-50 peer-checked:bg-gray-600 cursor-pointer peer-checked:text-white">
                            {{ $aspect->name }}
                        </label>
                    </span>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2">
                <x-input-label class="d-block" for="tratto" value="Tratto" />
                @foreach ($traits as $trait)
                    <span>
                        <input type="checkbox" name="tratti[]" value="{{ $trait }}" id="{{ $trait }}"
                            class="hidden peer" @checked(in_array($trait, (array) ($filters['tratti'] ?? [])))>
                        <label for="{{ $trait }}"
                            class="inline-block px-2 py-1 text-sm font-medium text-gray-700 bg-transparent border-2 rounded-md hover:bg-gray-50 peer-checked:bg-gray-600 cursor-pointer peer-checked:text-white">
                            {{ $trait }}
                        </label>
                    </span>
                @endforeach
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="unique_card" value="1" @checked(!empty($filters['unique_card']))
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Solo carte uniche</span>
                </label>
            </div>

            <div class="flex items-end gap-3">
                <x-primary-button>Cerca</x-primary-button>
                <a href="{{ route('cards.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Azzera</a>
            </div>
        </form>
    </div>
</div>
