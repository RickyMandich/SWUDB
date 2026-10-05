<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Carte
        </h2>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-6">
        <form action="{{ route('cards.index') }}" method="get">
            {{-- filtri --}}
        </form>
    </div>

    <x-flash-message />

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-4 px-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            <x-card :card="$card" />
        @endforeach
    </div>

    <div class="mx-auto max-w-7xl px-4 py-6">
        {{ $cards->links() }}
    </div>
</x-app-layout>
