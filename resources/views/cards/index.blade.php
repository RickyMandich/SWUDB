<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Carte</h2>
    </x-slot>

    <div class="max-w-5xl mx-auto py-6 px-4">
        <form action="{{ route('cards.index') }}" method="get">

        </form>
    </div>
    <x-flash-message />
    <div class="overflow-x-auto">
        @foreach ($cards as $card)
            <div class="card">
                <div class="image-box">
                    <img src="{{ asset('storage/' . $card->front_art_path) }}" alt="{{ $card->id }}" loading="lazy">
                    <script>
                        console.log('{{ $card->front_art_path }}');
                    </script>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
