<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Mazzi</h2>
            @auth
                <a href="{{ route('decks.create') }}"><x-primary-button type="button">Nuovo mazzo</x-primary-button></a>
            @endauth
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <x-bladewind.table>
                    <x-slot name="header">
                        <th>Mazzo</th>
                        <th>Leader</th>
                        <th>Base</th>
                        <th>Formato</th>
                        <th>Autore</th>
                        <th>Versione</th>
                    </x-slot>
                    @forelse ($decks as $deck)
                        <tr>
                            <td>
                                <a class="font-medium text-gray-800 hover:underline dark:text-gray-100"
                                    href="{{ route('decks.show', [$deck->user->name, $deck->name]) }}">{{ $deck->name }}</a>
                                @unless ($deck->is_public)
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">privato</span>
                                @endunless
                            </td>
                            <td>
                                @foreach ($deck->leaders as $leader)
                                    @if ($leader->front_art_path)
                                        <img class="mr-1 inline h-12 rounded"
                                            src="{{ asset('storage/' . $leader->front_art_path) }}"
                                            alt="{{ $leader->name }}">
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                @if ($deck->baseCard?->front_art_path)
                                    <img class="h-12 rounded"
                                        src="{{ asset('storage/' . $deck->baseCard->front_art_path) }}"
                                        alt="{{ $deck->baseCard->name }}">
                                @endif
                            </td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $deck->format->value }}</td>
                            <td class="text-gray-500 dark:text-gray-400">{{ $deck->user->name }}</td>
                            <td class="text-gray-500 dark:text-gray-400">v{{ $deck->version }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-gray-500 dark:text-gray-400">Nessun mazzo.</td>
                        </tr>
                    @endforelse
                </x-bladewind.table>
            </x-bladewind.card>

            {{ $decks->links() }}
        </div>
    </div>
</x-app-layout>
