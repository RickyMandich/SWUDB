{{-- resources/views/components/multi-datalist.blade.php --}}
@props(['name', 'label', 'options', 'selected' => [], 'placeholder' => 'Scrivi per cercare…'])

<div x-data="{
    options: @js($options),
    selected: @js(array_values((array) $selected)),
    query: '',
    get available() { return this.options.filter(o => !this.selected.includes(o)) },
    add() {
        const value = this.options.find(o => o.toLowerCase() === this.query.trim().toLowerCase())
        if (!value) return
        if (!this.selected.includes(value)) this.selected.push(value)
        this.query = ''
    },
    remove(value) { this.selected = this.selected.filter(v => v !== value) },
}">
    <x-input-label :for="$name . '-input'" :value="$label" />

    <x-text-input id="{{ $name }}-input" type="text" list="{{ $name }}-list" autocomplete="off"
        placeholder="{{ $placeholder }}" class="mt-1 block w-full" x-model="query" x-on:change="add()"
        x-on:keydown.enter.prevent="add()" />

    <datalist id="{{ $name }}-list">
        <template x-for="option in available" :key="option">
            <option :value="option"></option>
        </template>
    </datalist>

    <div class="mt-2 flex flex-wrap gap-2">
        <template x-for="value in selected" :key="value">
            <span
                class="inline-flex items-center gap-1 rounded-md border-2 border-gray-600 bg-gray-600 px-2 py-1 text-sm font-medium text-white">
                <span x-text="value"></span>
                <input type="hidden" name="{{ $name }}[]" :value="value">
                <button type="button" x-on:click="remove(value)" class="leading-none hover:text-gray-200"
                    :aria-label="'Rimuovi ' + value">&times;</button>
            </span>
        </template>
    </div>
</div>
