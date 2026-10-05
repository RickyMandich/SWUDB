{{-- resources/views/components/badge.blade.php --}}
@props(['color' => '#9ca3af', 'textColor' => '#ffffff'])
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 rounded text-xs']) }}
    style="background-color: {{ $color }}; color: {{ $textColor }}">
    {{ $slot }}
</span>
