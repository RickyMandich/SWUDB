{{-- resources/views/components/badge.blade.php --}}
@props(['color' => '#9ca3af', 'textColor' => '#ffffff'])
@php(\Log::debug('debugging color in badge component', [$color, $textColor]))
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 rounded-sm text-xs']) }}
    style="background-color: {{ $color }}; color: {{ $textColor }}">
    {{ $slot }}
</span>
