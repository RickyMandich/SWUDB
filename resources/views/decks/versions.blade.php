<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Versioni di {{ $deck->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-3 px-4 sm:px-6 lg:px-8">
            @foreach ($decks as $v)
                <x-bladewind.card class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:bg-gray-700 dark:text-gray-100">v{{ $v->version }}</span>
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400">{{ $v->created_at->format('d/m/Y') }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $v->is_public ? 'pubblico' : 'privato' }}{{ $v->assembled ? ' · assemblato' : '' }}
                            </span>
                            @if ($v->is($deck))
                                <span class="text-xs text-gray-500 dark:text-gray-400">(questa)</span>
                            @endif
                        </div>
                        <a class="text-sm text-gray-800 hover:underline dark:text-gray-100"
                            href="{{ route('decks.show', [$deck->user->name, $deck->name, $v->version]) }}">Consulta</a>
                    </div>
                </x-bladewind.card>
            @endforeach
        </div>
    </div>
</x-app-layout>
