@php
    \Log::debug('', [$expansions, $filter, $all_expansions]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Gestione espansioni</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4">
        @if ($filter)
            <x-primary-button form="filter-form">Mostra Tutte</x-primary-button>
        @else
            <x-primary-button form="filter-form">Mostra solo Non Confermate</x-primary-button>
        @endif
        <x-flash-message />

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-3 py-2 border-b dark:border-gray-700">Codice</th>
                        <th class="px-3 py-2 border-b dark:border-gray-700">Legal date</th>
                        <th class="px-3 py-2 border-b dark:border-gray-700">Rotation</th>
                        <th class="px-3 py-2 border-b dark:border-gray-700">Gruppo</th>
                        <th class="px-3 py-2 border-b dark:border-gray-700">azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expansions as $expansion)
                        <tr>
                            <td class="px-3 py-2 border-b dark:border-gray-700">{{ $expansion->expansion }}</td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-bladewind.datepicker name="legal_date" required="false" fill_from_old="true"
                                    format="yyyy-mm-dd" week_starts="monday" size="big" class="shadow-sm"
                                    selected_value="{{ $expansion->legal_date->format('Y-m-d') }}" />
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-text-input type="text" name="rotation" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->rotation" maxlength="1" class="w-14" />
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-select-input name="group_main_expansion" form="exp-{{ $expansion->expansion }}">
                                    <option value="">—</option>
                                    <option value="null">Espansione a sé stante</option>
                                    <option value="{{ $expansion->expansion }}" @selected($expansion->group_main_expansion === $expansion->expansion)>
                                        Espansione principale del Gruppo
                                    </option>
                                    @foreach ($all_expansions as $option)
                                        @if ($option->expansion !== $expansion->expansion)
                                            <option value="{{ $option->expansion }}" @selected($expansion->group_main_expansion === $option->expansion)>
                                                {{ $option->expansion }}
                                            </option>
                                        @endif
                                    @endforeach
                                </x-select-input>
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-primary-button form="exp-{{ $expansion->expansion }}">Salva</x-primary-button>
                                <x-primary-button :target="'_blank'" :href="route('cards.index', ['espansioni[]' => [$expansion->expansion]])">cards</x-primary-button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-input-error :messages="$errors->all()" class="mt-2" />
        </div>

        @foreach ($expansions as $expansion)
            <form id="exp-{{ $expansion->expansion }}" method="POST"
                action="{{ route('admin.expansions.update', $expansion) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <form action="{{ route('admin.expansions.index') }}" id="filter-form" method="GET" class="hidden">
            @if ($filter)
                <input type="hidden" name="filter" value="0" />
            @else
                <input type="hidden" name="filter" value="1" />
            @endif
        </form>
    </div>
</x-app-layout>
