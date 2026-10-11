@props(['deck'])
@php
    $leaders = $deck->leaders->values()->take(2);
    $base = $deck->baseCard->first();
    $twinSuns = $leaders->count() >= 2;
    $art = fn($card) => $card?->front_art_path ? asset('storage/' . $card->front_art_path) : null;
@endphp
<div {{ $attributes->class('relative h-25 w-35 shrink-0') }}>
    {{-- Base: dietro, in alto e a sinistra --}}
    <div
        class="absolute left-0 top-0 z-0 aspect-[7/5] w-28 overflow-hidden rounded
                border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-700">
        @if ($art($base))
            <img class="h-full w-full object-cover" src="{{ $art($base) }}" alt="{{ $base->name }}" loading="lazy">
        @else
            <span
                class="flex h-full items-center justify-center border border-dashed border-gray-300 text-center text-[10px] leading-tight text-gray-500 dark:border-gray-600 dark:text-gray-400">Nessuna
                base.</span>
        @endif
    </div>

    {{-- Leader: davanti, in basso a destra --}}
    <div
        class="absolute bottom-0 right-0 z-10 flex aspect-[7/5] w-28 overflow-hidden rounded
                border border-gray-300 bg-gray-100 shadow-md dark:border-gray-600 dark:bg-gray-700">
        @forelse ($leaders as $leader)
            @if ($twinSuns)
                {{-- Twin Suns: metà sinistra di ciascun leader, affiancate --}}
                <div
                    class="relative h-full w-1/2 overflow-hidden {{ $loop->first ? 'border-r border-gray-300 dark:border-gray-600' : '' }}">
                    @if ($art($leader))
                        <img class="h-full w-[200%] max-w-none object-cover object-left" src="{{ $art($leader) }}"
                            alt="{{ $leader->name }}" loading="lazy">
                    @endif
                </div>
            @elseif ($art($leader))
                <img class="h-full w-full object-cover" src="{{ $art($leader) }}" alt="{{ $leader->name }}"
                    loading="lazy">
            @endif
        @empty
            <span
                class="flex h-full w-full items-center justify-center text-center text-[10px] leading-tight text-gray-500 dark:text-gray-400">Nessun
                leader.</span>
        @endforelse
    </div>
</div>
