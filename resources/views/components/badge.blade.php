{{-- resources/views/components/badge.blade.php --}}
@props(['color' => '#9ca3af'])
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 rounded text-xs text-white']) }}
    style="background-color: {{ $color }}">
    {{ $slot }}
</span>
