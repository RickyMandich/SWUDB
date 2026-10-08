<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Ultime carte uscite
        </h2>
    </x-slot>

    <form action="{{ route('cards.new-releases') }}" method="GET" class="m-6 form-control">
        <x-bladewind.datepicker name="since" required="false" placeholder="Ultime carte uscite" fill_from_old="true"
            format="dd/mm/yyyy" week_starts="monday" size="big" class="shadow-sm"
            selected_value="{{ $since }}" min_date="{{ $firstRelease }}" max_date="{{ $lastRelease }}" />
        <x-bladewind.button can_submit="true" class="mx-auto">
            Cerca
        </x-bladewind.button>
    </form>

    <x-flash-message />

    <x-bladewind.alert>
        {{ $cards->count() }} carte divise in {{ $groups->count() }} date trovate rilasciate dal {{ $since }} a
        oggi
    </x-bladewind.alert>
    @foreach ($groups as $date => $group)
        <div
            class="mx-auto grid max-w-7xl grid-cols-1 gap-4 border border-gray-200 rounded-lg bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:grid-cols-2 lg:grid-cols-3 m-4">
            <h3 class="col-span-full text-center">{{ $date === 'nd' ? 'Data non disponibile' : $date }}</h3>
            @foreach ($group as $card)
                <x-card :card="$card" />
            @endforeach
        </div>
    @endforeach

    <div class="mx-auto max-w-7xl px-4 py-6">
        {{ $cards->links() }}
    </div>
</x-app-layout>
