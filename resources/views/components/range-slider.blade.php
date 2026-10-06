{{-- resources/views/components/range-slider.blade.php --}}
@props(['name', 'label', 'min', 'max', 'selectedMin' => null, 'selectedMax' => null])

<div x-data="{
    min: {{ (int) $min }},
    max: {{ (int) $max }},
    from: {{ (int) ($selectedMin ?? $min) }},
    to: {{ (int) ($selectedMax ?? $max) }},
    get span() { return (this.max - this.min) || 1 },
    get fromPct() { return ((this.from - this.min) / this.span) * 100 },
    get toPct() { return ((this.to - this.min) / this.span) * 100 },
    clampFrom() { if (this.from > this.to) this.from = this.to },
    clampTo() { if (this.to < this.from) this.to = this.from },
}">
    <div class="flex items-baseline justify-between">
        <x-input-label :value="$label" />
        <span class="text-sm text-gray-600 dark:text-gray-400" x-text="from === to ? from : from + ' - ' + to"></span>
    </div>

    <div class="relative mt-2 h-5">
        <div class="absolute top-1/2 h-1 w-full -translate-y-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
        <div class="absolute top-1/2 h-1 -translate-y-1/2 rounded bg-gray-600 dark:bg-gray-400"
            :style="`left: ${fromPct}%; width: ${toPct - fromPct}%`"></div>

        <input type="range" name="{{ $name }}_min" :min="min" :max="max" step="1"
            x-model.number="from" x-on:input="clampFrom()" class="range-thumb" :class="fromPct > 50 ? 'z-20' : 'z-10'"
            aria-label="{{ $label }} minimo">
        <input type="range" name="{{ $name }}_max" :min="min" :max="max"
            step="1" x-model.number="to" x-on:input="clampTo()" class="range-thumb z-10"
            aria-label="{{ $label }} massimo">
    </div>

    <div class="mt-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
        <span x-text="min"></span>
        <span x-text="max"></span>
    </div>
</div>
