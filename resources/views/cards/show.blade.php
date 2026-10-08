<x-app-layout>

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ $card->snippet }}
        </h2>
    </x-slot>

    <div class="p-4">

        {{-- Contenitore principale --}}
        <x-bladewind.card has_shadow="true" radius="large" no_padding="true" class="bg-white dark:bg-gray-800">

            {{-- Immagine + dati --}}
            <div class="flex flex-col gap-6 p-5 md:flex-row md:items-start">

                {{-- Immagine --}}
                <div class="w-full md:w-auto md:max-w-md" x-data="{ angle: 0 }">
                    <x-bladewind.card compact="true" has_shadow="false" radius="medium"
                        class="bg-white dark:bg-gray-800">
                        @if ($card->back_art_path)
                            <div class="mb-3 flex items-center justify-center">
                                <x-bladewind.button x-on:click="angle += 180">
                                    Gira la carta
                                </x-bladewind.button>
                            </div>
                        @endif

                        <div class="perspective-distant">
                            <div class="grid transform-3d transition-transform duration-500 motion-reduce:transition-none"
                                :style="{ transform: 'rotate3d(1, 1, 0, ' + angle + 'deg)' }">
                                <img src="{{ Storage::url($card->front_art_path) }}" alt="{{ $card->name }}"
                                    class="col-start-1 row-start-1 block h-auto w-full self-center rounded-lg backface-hidden">
                                @if ($card->back_art_path)
                                    <img src="{{ Storage::url($card->back_art_path) }}"
                                        alt="{{ $card->name }} (retro)"
                                        class="col-start-1 row-start-1 block h-auto w-full rounded-lg backface-hidden flip-diagonal">
                                @endif
                            </div>
                        </div>
                    </x-bladewind.card>
                </div>


                {{-- Dati carta --}}
                <div class="min-w-0 flex-1">

                    <x-bladewind.card compact="true" has_shadow="false" radius="medium"
                        class="bg-white dark:bg-gray-800">
                        <x-bladewind.description-list striped="true" divided="true">

                            <x-bladewind.description-list.item label="Aspetti"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                @foreach ($card->aspects as $aspect)
                                    <x-badge :color="$aspect->color" :text-color="$aspect->text_color">
                                        {{ $aspect->name }}
                                    </x-badge>
                                @endforeach
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Tratti"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                @foreach ($card->traits as $trait)
                                    <x-badge>
                                        {{ $trait->name }}
                                    </x-badge>
                                @endforeach
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Tipo"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->type }}
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Rarità"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->rarity }}
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Costo"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->cost }}
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Vita"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->health }}
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Potenza"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->power }}
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Arena"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->arena }}
                            </x-bladewind.description-list.item>

                            <x-bladewind.description-list.item label="Artista"
                                class="text-gray-800 dark:text-gray-200 bg-transparent">
                                {{ $card->artist }}
                            </x-bladewind.description-list.item>

                        </x-bladewind.description-list>
                    </x-bladewind.card>

                </div>

            </div>


            {{-- Abilità / descrizione --}}
            <div class="px-5 pb-5 card-ability">

                <x-bladewind.card title="Abilità" has_shadow="false" radius="medium"
                    class="bg-white dark:bg-gray-800 m-4">
                    <div class="prose max-w-none text-gray-800 dark:text-gray-200">
                        {!! $card->text !!}
                    </div>
                </x-bladewind.card>

                @isset($card->deploy_text)
                    <x-bladewind.card title="Abilità Da Schierato" has_shadow="false" radius="medium"
                        class="bg-white dark:bg-gray-800 m-4">
                        <div class="prose max-w-none text-gray-800 dark:text-gray-200">
                            {!! $card->deploy_text !!}
                        </div>
                    </x-bladewind.card>
                @endisset

            </div>

        </x-bladewind.card>

    </div>

</x-app-layout>
