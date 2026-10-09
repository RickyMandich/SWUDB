<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Nuovo mazzo</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <form method="POST" action="{{ route('decks.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Nome" />
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

                    <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                        <input type="hidden" name="is_public" value="0">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900">
                        Mazzo pubblico
                    </label>

                    <div class="flex justify-end"><x-primary-button>Crea mazzo</x-primary-button></div>
                </form>
            </x-bladewind.card>
        </div>
    </div>
</x-app-layout>
