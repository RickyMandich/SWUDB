<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Mazzi</h2>
            @auth
                <a href="{{ route('decks.create') }}"><x-primary-button type="button">Nuovo mazzo</x-primary-button></a>
                <a href="{{ route('decks.import') }}"><x-secondary-button type="button">Importa
                        mazzo</x-secondary-button></a>
            @endauth
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <x-bladewind.table>
                    <x-slot name="header">
                        <th>Mazzo</th>
                        <th>Leader / Base</th>
                        <th>Formato</th>
                        <th>Autore</th>
                        <th>Versione</th>
                    </x-slot>
                    @forelse ($decks as $deck)
                        <tr>
                            <td>
                                <a class="font-medium text-gray-800 hover:underline dark:text-gray-100"
                                    href="{{ route('decks.show', [$deck->user->name, $deck->name]) }}">{{ $deck->name }}
                                </a>
                                <x-badge :textColor="'#000000'" class="ml-2">
                                    {{ $deck->is_public ? 'pubblico' : 'privato' }}
                                </x-badge>
                            </td>
                            <td><x-deck-cover :deck="$deck" /></td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $deck->format->value }}</td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $deck->user->name }}</td>
                            <td class="text-gray-500 dark:text-gray-400">v{{ $deck->version }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-gray-500 dark:text-gray-400">Nessun mazzo.</td>
                        </tr>
                    @endforelse
                </x-bladewind.table>
            </x-bladewind.card>

            {{ $decks->links() }}
        </div>
    </div>
</x-app-layout>
