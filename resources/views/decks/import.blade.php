<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Importa mazzo</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (!empty(session('import-warnings')))
                <x-bladewind.alert type="warning">
                    Righe non riconosciute:
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ((array) session('import-warnings') as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </x-bladewind.alert>
            @endif

            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('decks.import.store') }}" enctype="multipart/form-data"
                    x-data="{ tab: '{{ old('url') ? 'url' : 'file' }}' }" class="space-y-6">
                    @csrf

                    <x-input-error :messages="$errors->get('import')" />

                    <div>
                        <x-input-label for="name" value="Nome del mazzo" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="format" value="Formato" />
                        <select id="format" name="format"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @foreach (\App\Enums\DeckFormat::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('format') === $case->value)>{{ $case->value }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('format')" class="mt-2" />
                    </div>

                    <div class="flex gap-2">
                        <x-secondary-button type="button" @click="tab = 'file'" ::class="tab === 'file' ? 'ring-2 ring-indigo-500' : ''">Da
                            file</x-secondary-button>
                        <x-secondary-button type="button" @click="tab = 'url'" ::class="tab === 'url' ? 'ring-2 ring-indigo-500' : ''">Da
                            URL</x-secondary-button>
                    </div>

                    <div x-show="tab === 'file'">
                        <x-input-label for="file" value="File del mazzo (TXT o JSON, max 2 MB)" />
                        <input id="file" name="file" type="file" accept=".txt,.json"
                            class="mt-1 block w-full text-sm text-gray-800 dark:text-gray-200">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <div x-show="tab === 'url'">
                        <x-input-label for="url" value="URL del mazzo (https)" />
                        <x-text-input id="url" name="url" type="url" class="mt-1 block w-full"
                            :value="old('url')" placeholder="https://swudb.com/deck/..." />
                        <x-input-error :messages="$errors->get('url')" class="mt-2" />
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="hidden" name="is_public" value="0">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        Mazzo pubblico
                    </label>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Per il JSON si riconosce la carta dall'id (<code>SOR_134</code>), per il TXT dal nome in
                        italiano:
                        i file esportati da questo sito si reimportano senza perdite, quelli con i nomi inglesi vanno
                        importati dal JSON.
                        La sideboard viene ignorata.
                    </p>

                    <div class="flex justify-end"><x-primary-button>Importa</x-primary-button></div>
                </form>
            </x-bladewind.card>
        </div>
    </div>
</x-app-layout>
