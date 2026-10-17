{{-- resources/views/components/deck-card-row.blade.php --}}
@props(['card', 'quantity'])
<li
    {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 py-2 text-sm text-gray-800 dark:text-gray-100']) }}>
    <a href="{{ route('cards.show', [$card->expansion, $card->number]) }}"
        class="flex min-w-0 flex-1 items-center gap-3 hover:underline">
        @if ($card->front_art_path)
            <img src="{{ asset('storage/' . $card->front_art_path) }}" alt="{{ $card->name }}"
                class="h-10 shrink-0 rounded shadow-xs" loading="lazy">
        @endif
        <span class="truncate font-medium">{{ $card->snippet }}</span>
    </a>
    <span class="flex shrink-0 items-center gap-3">
        <span class="hidden w-24 text-right text-gray-500 dark:text-gray-400 sm:inline">{{ $card->type }}</span>
        <span class="w-10 text-right font-semibold">×{{ $quantity }}</span>
        {{ $slot }}
    </span>
</li>
