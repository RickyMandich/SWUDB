<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestione espansioni</h2>
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
                        <th class="px-3 py-2 border-b">Codice</th>
                        <th class="px-3 py-2 border-b">Legal date</th>
                        <th class="px-3 py-2 border-b">Rotation</th>
                        <th class="px-3 py-2 border-b">Gruppo</th>
                        <th class="px-3 py-2 border-b">Confermata</th>
                        <th class="px-3 py-2 border-b"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expansions as $expansion)
                        <tr>
                            <td class="px-3 py-2 border-b">{{ $expansion->expansion }}</td>
                            <td class="px-3 py-2 border-b">
                                <x-text-input type="date" name="legal_date" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->legal_date?->format('Y-m-d')" />
                            </td>
                            <td class="px-3 py-2 border-b">
                                <x-text-input type="text" name="rotation" form="exp-{{ $expansion->expansion }}"
                                    :value="$expansion->rotation" maxlength="1" class="w-14" />
                            </td>
                            <td class="px-3 py-2 border-b">
                                <select name="group_main_expansion" form="exp-{{ $expansion->expansion }}"
                                    class="rounded-md border-gray-300 shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($expansions as $option)
                                        <option value="{{ $option->expansion }}" @selected($expansion->group_main_expansion === $option->expansion)>
                                            {{ $option->expansion }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2 border-b">
                                <input type="checkbox" name="confirmed" value="1"
                                    form="exp-{{ $expansion->expansion }}" @checked($expansion->confirmed)
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2 border-b">
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
