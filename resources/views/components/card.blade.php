<article class="h-full rounded-xl border border-slate-200 bg-blue-300 p-3 shadow-sm transition hover:shadow-md">
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
                <h5 class="mb-2 text-lg font-semibold leading-tight text-gray-900">
                    {{ $card->snippet }}
                </h5>

                {{-- Tratti --}}
                @if ($card->traits->isNotEmpty())
                    <div class="mb-2 text-sm text-gray-700">
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
                            <span class="inline-block rounded px-2 py-0.5 text-xs font-medium"
                                style="background-color: {{ $aspect->color }}; color: {{ $aspect->text_color }};">
                                {{ $aspect->name }}
                            </span>
                        @endforeach
                    </div>
                @endif

                {{-- Costo --}}
                <div class="mb-1 flex items-center  text-yellow-800">
                    <span class="w-9/12">
                        costo:
                    </span>

                    <span class="w-3/12 text-center font-semibold">
                        {{ $card->cost ?? '-' }}
                    </span>
                </div>

                {{-- Potenza --}}
                <div class="mb-1 flex items-center  text-red-800">
                    <span class="w-9/12">
                        potenza:
                    </span>

                    <span class="w-3/12 text-center font-semibold">
                        {{ $card->power ?? '-' }}
                    </span>
                </div>

                {{-- Vita --}}
                <div class="mb-1 flex items-center  text-blue-900">
                    <span class="w-9/12">
                        vita:
                    </span>

                    <span class="w-3/12 text-center font-semibold">
                        {{ $card->health ?? '-' }}
                    </span>
                </div>

                {{-- Rarità --}}
                <div class="mt-2 text-center">
                    <span class="font-semibold">
                        {{ $card->rarity }}
                    </span>
                </div>
            </div>
        </div>
    </a>
</article>
