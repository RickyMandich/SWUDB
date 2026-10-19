@php
    $variants = [
        'normal' => 'Normale',
        'foil' => 'Foil',
        'hyper' => 'Hyperspace',
        'hyper_foil' => 'Hyperspace foil',
        'prestige' => 'Prestige',
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">La mia collezione</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-6">
        <x-cards-filter :aspects="$aspects" :traits="$traits" :types="$types" :expansions="$expansions" :filters="$filters"
            :bounds="$bounds" :action="route('collection.index')" />
    </div>

    <x-flash-message />

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-4 px-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $card)
            @php($owned = $card->collectionCards->pluck('quantity', 'variant'))
            <div class="h-full">
                <x-card :card="$card">
                    <div
                        class="grid grid-cols-5 gap-2 rounded-lg border border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-800">
                        @foreach ($variants as $key => $label)
                            <label class="text-center text-xs text-gray-500 dark:text-gray-400">
                                {{ $label }}
                                <input type="number" min="0" max="65535" value="{{ $owned[$key] ?? 0 }}"
                                    data-collection data-card="{{ $card->id }}" data-variant="{{ $key }}"
                                    class="mt-1 w-full rounded border-gray-300 text-center text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">
                            </label>
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endforeach
        @if ($cards->isEmpty())
            <p class="col-span-full text-center text-gray-800 dark:text-gray-200">Non ho trovato carte che soddisfino
                tutti i criteri di ricerca.</p>
        @endif
    </div>

    <div class="mx-auto max-w-7xl px-4 py-6">
        {{ $cards->links() }}
    </div>

    <script>
        document.addEventListener('change', async (event) => {
            const input = event.target.closest('input[data-collection]');
            if (!input) return;
            const quantity = Math.max(0, parseInt(input.value, 10) || 0);
            input.value = quantity;
            input.classList.remove('border-red-500');
            try {
                const response = await fetch("{{ route('collection.update') }}", {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        card_id: input.dataset.card,
                        variant: input.dataset.variant,
                        quantity,
                    }),
                });
                if (!response.ok) throw new Error(response.status);
            } catch (e) {
                input.classList.add(
                    'border-red-500'
                ); // salvataggio non riuscito: il valore a schermo non è quello nel database
            }
        });
    </script>
</x-app-layout>
