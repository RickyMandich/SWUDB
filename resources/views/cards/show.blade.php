<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ $card->snippet }}
        </h2>
    </x-slot>
    <div id="foto">
        <img src="{{ Storage::url($card->front_art_path) }}" alt="{{ $card->name }}">
    </div>
    <div id="dati">

    </div>
</x-app-layout>
