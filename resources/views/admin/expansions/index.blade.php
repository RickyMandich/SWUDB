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
                        <th class="px-3 py-2 border-b dark:border-gray-700">Confermata</th>
                        <th class="px-3 py-2 border-b dark:border-gray-700"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expansions as $expansion)
                        <tr>
                            <td class="px-3 py-2 border-b dark:border-gray-700">{{ $expansion->expansion }}</td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-text-input type="date" name="legal_date" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->legal_date?->format('Y-m-d')" />
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-text-input type="text" name="rotation" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->rotation" maxlength="1" class="w-14" />
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-select-input name="group_main_expansion" form="exp-{{ $expansion->expansion }}">
                                    <option value="">—</option>
                                    @foreach ($expansions as $option)
                                        <option value="{{ $option->expansion }}" @selected($expansion->group_main_expansion === $option->expansion)>
                                            {{ $option->expansion }}
                                        </option>
                                    @endforeach
                                </x-select-input>
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <input type="checkbox" name="confirmed" value="1"
                                    form="exp-{{ $expansion->expansion }}" @checked($expansion->confirmed)
                                    class="rounded-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800">
                            </td>
                            <td class="px-3 py-2 border-b dark:border-gray-700">
                                <x-primary-button form="exp-{{ $expansion->expansion }}">Salva</x-primary-button>
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
