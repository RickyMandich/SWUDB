{{-- Floating Desktop Hover Preview --}}
<div x-show="preview.hover && preview.image" 
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 scale-95"
     x-transition:enter-end="opacity-100 scale-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100 scale-100"
     x-transition:leave-end="opacity-0 scale-95"
     :style="`top: ${preview.y}px; left: ${preview.x}px;`"
     class="fixed pointer-events-none z-50 transform -translate-y-1/2"
     style="display: none;">
    <div class="bg-gray-900/90 p-1.5 rounded-xl shadow-2xl border border-gray-700 backdrop-blur-xs max-w-[240px]">
        <img :src="preview.image" :alt="preview.name" class="w-56 h-auto rounded-lg shadow">
    </div>
</div>

{{-- Mobile Touch Modal Preview --}}
<div x-show="preview.modal" 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs"
     style="display: none;"
     @click.self="closeModal()"
     @keydown.escape.window="closeModal()">
    
    <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 max-w-sm w-full p-5 flex flex-col items-center gap-4 text-center"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-90"
         x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
        
        <h4 class="font-semibold text-gray-900 dark:text-gray-100 text-base" x-text="preview.name"></h4>

        <template x-if="preview.image">
            <a :href="preview.url" class="block group">
                <img :src="preview.image" :alt="preview.name" class="max-h-[55vh] w-auto rounded-xl shadow-lg transition-transform group-hover:scale-[1.02]">
                <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-2">Tocca l'immagine per aprire la carta</p>
            </a>
        </template>
        <template x-if="!preview.image">
            <div class="h-48 w-36 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-xl text-gray-400 text-sm">
                Nessuna immagine
            </div>
        </template>

        <div class="flex items-center gap-3 w-full justify-center pt-3 border-t border-gray-100 dark:border-gray-700">
            <template x-if="preview.url">
                <a :href="preview.url" class="inline-flex">
                    <x-primary-button type="button">Vedi dettagli</x-primary-button>
                </a>
            </template>
            <x-secondary-button type="button" @click="closeModal()">Chiudi</x-secondary-button>
        </div>
    </div>
</div>
