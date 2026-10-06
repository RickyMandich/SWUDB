<article class="h-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 shadow-sm transition hover:shadow-md">
    <a href="{{ route('cards.show', [
        'expansion' => $card->expansion,
        'number' => $card->number,
    ]) }}"
        class="block h-full">
        <div class="grid grid-cols-2 gap-4">
            {{-- Immagine --}}
            <div>
                <img src="{{ Storage::url($card->front_art_path) }}" alt="Immagine di {{ $card->snippet }}"
                    class="w-full rounded-lg">
            </div>

            {{-- Informazioni --}}
            <div class="min-w-0">
                <h5 class="mb-2 text-lg font-semibold leading-tight text-gray-900 dark:text-gray-100">
                    {{ $card->snippet }}
                </h5>

                {{-- Tratti --}}
                @if ($card->traits->isNotEmpty())
                    <div class="mb-2 text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($card->traits as $trait)
                            <span class="mr-1 font-semibold">
                                {{ $trait->name }}
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Aspetti --}}
                @if ($card->aspects->isNotEmpty())
                    <div class="mb-2 flex flex-wrap gap-1">
                        @foreach ($card->aspects as $aspect)
                            <x-badge :color="$aspect->color" :text-color="$aspect->text_color">
                                {{ $aspect->name }}
                            </x-badge>
                        @endforeach
                    </div>
                @endif

                {{-- Costo --}}
                <div class="mb-1 flex items-center text-yellow-600 dark:text-yellow-300">
                    <span class="w-9/12">
                        costo:
                    </span>

                    <span class="w-3/12 text-center font-semibold">
                        {{ $card->cost ?? '-' }}
                    </span>
                </div>

                {{-- Potenza --}}
                <div class="mb-1 flex items-center text-red-600 dark:text-red-400">
                    <span class="w-9/12">
                        potenza:
                    </span>

                    <span class="w-3/12 text-center font-semibold">
                        {{ $card->power ?? '-' }}
                    </span>
                </div>

                {{-- Vita --}}
                <div class="mb-1 flex items-center text-blue-600 dark:text-blue-400">
                    <span class="w-9/12">
                        vita:
                    </span>

                    <span class="w-3/12 text-center font-semibold">
                        {{ $card->health ?? '-' }}
                    </span>
                </div>

                {{-- Rarità --}}
                <div class="mt-2 text-center">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">
                        {{ $card->rarity }}
                    </span>
                </div>
            </div>
        </div>
    </a>
</article>
