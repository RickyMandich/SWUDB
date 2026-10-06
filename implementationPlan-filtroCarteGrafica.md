# Filtro carte: grafica (espansioni/tratti con datalist, slider a doppio input per costo, vita e potenza)

> Piano trasversale alla Fase 10.1.5 di `implementationPlan-05-ricostruzioneCatalogoPubblico.md` (il form dei filtri della lista carte).
> Questo piano copre **solo la grafica** (view, componenti Blade, CSS). Il backend (`CardController`, `CardSearch`) è la Parte B, da dettagliare dopo aver verificato la grafica.
> Regola di progetto (`.agent/rules/vault.md`): il codice lo applica l'utente, l'agente scrive solo questo piano.

## Contesto e stato di partenza

- `resources/views/components/cards-filter.blade.php`: tipi e aspetti sono già badge cliccabili (checkbox `hidden peer` + `<label>` con `peer-checked:`), wrappati in uno `<span>` per isolare il `peer`. Anche espansioni e tratti sono ancora badge (40 e 60: troppi). Il costo è un `<input type="number">`.
- Decisioni prese:
  - **espansioni e tratti**: input di testo con `<datalist>` (suggerimenti) + valori scelti mostrati come chip rimovibili sotto l'input; più valori selezionabili, logica AND lato backend (Parte B);
  - **tipi e aspetti**: restano badge cliccabili, invariati;
  - **costo, vita, potenza**: uno slider a doppio input ciascuno (una linea, due pallini per gli estremi), con valori minimo/massimo letti dal DB (Parte B). In questa parte i limiti sono **provvisori** e vivono in un unico punto (la prop `bounds` di `x-cards-filter`).
- Nomi dei parametri GET introdotti da questo piano (da usare nella Parte B):
  - `espansioni[]`, `tipi[]`, `aspetti[]`, `tratti[]` (già così nel form attuale)
  - `costo_min`, `costo_max`, `vita_min`, `vita_max`, `potenza_min`, `potenza_max` (sostituiscono `costo`)
  - Colonne DB corrispondenti: `cards.cost`, `cards.health`, `cards.power`.

## Problemi trovati nel form attuale (corretti da questo piano)

- Gli `id` degli input sono `{{ $expansion }}`, `{{ $type }}`, `{{ $trait }}`, `{{ $aspect->id }}`: con i tratti contengono spazi (`Cacciatore Di Taglie`, `Nave Ammiraglia`) e possono collidere tra gruppi, quindi `<label for>` può puntare all'input sbagliato o a nessuno. Si prefissano e si passano da `Str::slug()`.
- `class="d-block"` sulle label dei gruppi è una classe Bootstrap, in Tailwind non esiste: il titolo del gruppo finisce dentro il `flex-wrap` come un badge qualsiasi. Si separa il titolo dal contenitore dei badge.
- `x-collapse` è usato in `cards-filter` ma il plugin `@alpinejs/collapse` non è in `package.json` né registrato in `resources/js/app.js`: l'apertura/chiusura funziona (c'è `x-show`) ma senza animazione. Vedi lo Step 6 (opzionale).

## Step 1 — CSS dei pallini dello slider

Aggiungere **in fondo** a `resources/css/app.css`. Serve perché i due `<input type="range">` sono sovrapposti sulla stessa linea: l'input intero ignora il mouse (`pointer-events: none`) e solo il pallino lo riceve (`pointer-events: auto`), così si possono trascinare entrambi.

```css
/* Slider a doppio input: components/range-slider.blade.php */
.range-thumb {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    background: transparent;
    -webkit-appearance: none;
    appearance: none;
    pointer-events: none;
}

.range-thumb::-webkit-slider-runnable-track {
    height: 1.25rem;
    background: transparent;
}

.range-thumb::-moz-range-track {
    background: transparent;
}

.range-thumb::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    pointer-events: auto;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    border: 2px solid #4b5563;
    background: #fff;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.25);
    cursor: pointer;
}

.range-thumb::-moz-range-thumb {
    pointer-events: auto;
    width: 1.25rem;
    height: 1.25rem;
    border-radius: 9999px;
    border: 2px solid #4b5563;
    background: #fff;
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.25);
    cursor: pointer;
}

.range-thumb:focus-visible::-webkit-slider-thumb {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}

.range-thumb:focus-visible::-moz-range-thumb {
    outline: 2px solid #6366f1;
    outline-offset: 2px;
}
```

## Step 2 — Componente `x-range-slider`

Creare `resources/views/components/range-slider.blade.php`.

Come funziona:
- due `<input type="range">` sovrapposti, uno per l'estremo inferiore (`{name}_min`) e uno per il superiore (`{name}_max`);
- la linea grigia e la parte colorata tra i due pallini sono due `<div>` posizionati con `left`/`width` in percentuale, calcolati da Alpine;
- `clampFrom()` / `clampTo()` impediscono che i pallini si incrocino;
- se i due pallini finiscono sullo stesso punto all'estremo destro quello "da" deve stare sopra, altrimenti non si riuscirebbe più a trascinarlo: per questo il suo `z-index` sale quando supera metà corsa;
- il testo a destra del titolo mostra l'intervallo scelto.

```blade
{{-- resources/views/components/range-slider.blade.php --}}
@props(['name', 'label', 'min', 'max', 'selectedMin' => null, 'selectedMax' => null])

<div
    x-data="{
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
        <span class="text-sm text-gray-600" x-text="from === to ? from : from + ' – ' + to"></span>
    </div>

    <div class="relative mt-2 h-5">
        <div class="absolute top-1/2 h-1 w-full -translate-y-1/2 rounded bg-gray-200"></div>
        <div class="absolute top-1/2 h-1 -translate-y-1/2 rounded bg-gray-600"
            :style="`left: ${fromPct}%; width: ${toPct - fromPct}%`"></div>

        <input type="range" name="{{ $name }}_min" :min="min" :max="max" step="1"
            x-model.number="from" x-on:input="clampFrom()" class="range-thumb"
            :class="fromPct > 50 ? 'z-20' : 'z-10'" aria-label="{{ $label }} minimo">
        <input type="range" name="{{ $name }}_max" :min="min" :max="max" step="1"
            x-model.number="to" x-on:input="clampTo()" class="range-thumb z-10"
            aria-label="{{ $label }} massimo">
    </div>

    <div class="mt-1 flex justify-between text-xs text-gray-400">
        <span x-text="min"></span>
        <span x-text="max"></span>
    </div>
</div>
```

## Step 3 — Componente `x-multi-datalist`

Creare `resources/views/components/multi-datalist.blade.php`. Una `<datalist>` permette di scegliere **un** valore alla volta, quindi il componente trasforma ogni valore scelto in un chip con un `<input type="hidden" name="{name}[]">`: al submit il backend riceve l'array, come con i badge.

Come funziona:
- l'utente scrive nell'input di testo e sceglie un suggerimento (o preme Invio);
- `add()` accetta il valore solo se corrisponde (senza distinguere maiuscole/minuscole) a una delle opzioni reali, lo aggiunge a `selected` e svuota il campo;
- Invio è intercettato (`.prevent`) per non inviare il form a metà selezione;
- i chip hanno lo stesso aspetto dei badge selezionati (sfondo pieno) e la `×` li rimuove;
- le opzioni già scelte spariscono dalla `<datalist>`;
- `selected` parte dai valori già presenti in `$filters`, così dopo la ricerca i chip tornano visibili.

```blade
{{-- resources/views/components/multi-datalist.blade.php --}}
@props(['name', 'label', 'options', 'selected' => [], 'placeholder' => 'Scrivi per cercare…'])

<div
    x-data="{
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
        placeholder="{{ $placeholder }}" class="mt-1 block w-full" x-model="query"
        x-on:change="add()" x-on:keydown.enter.prevent="add()" />

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
```

Note:
- `@js(...)` produce un `JSON.parse('…')` già escapato per gli attributi HTML, quindi apostrofi come in `Twi'lek` non rompono l'attributo.
- `$name` è usato per gli `id` (`espansioni-input`, `espansioni-list`, `tratti-input`, `tratti-list`), quindi i due componenti nella stessa pagina non collidono.

## Step 4 — Riscrivere `resources/views/components/cards-filter.blade.php`

Sostituire l'intero file. Cambiamenti rispetto a ora:
- nuova prop opzionale `bounds` con i limiti **provvisori** degli slider (nella Parte B arrivano dal controller);
- espansioni e tratti passano a `<x-multi-datalist>`;
- costo/vita/potenza passano a `<x-range-slider>`;
- tipi e aspetti restano badge, con `id` univoci e titolo separato dal contenitore dei badge;
- il layout passa a 3 colonne (`lg:grid-cols-3`) e raggruppa i campi: testo/datalist, badge, slider, azioni.

```blade
@props([
    'aspects',
    'traits',
    'types',
    'expansions',
    'filters',
    'action',
    // Provisional limits: Part B of implementationPlan-filtroCarteGrafica.md replaces them with the real DB min/max
    // Limiti provvisori: la Parte B di implementationPlan-filtroCarteGrafica.md li sostituisce con i veri min/max del DB
    'bounds' => ['cost' => [0, 10], 'health' => [0, 30], 'power' => [0, 12]],
])

<div x-data="{ open: {{ collect($filters)->filter()->isNotEmpty() ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
        class="flex w-full items-center justify-between rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
        <span>Filtri</span>
        <svg :class="{ 'rotate-180': open }" class="h-5 w-5 transition-transform duration-200" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" x-collapse x-cloak>
        <form action="{{ $action }}" method="get"
            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

            {{-- Testo e datalist --}}
            <div>
                <x-input-label for="nome" value="Nome" />
                <x-text-input id="nome" name="nome" type="text" class="mt-1 block w-full"
                    :value="$filters['nome'] ?? ''" />
            </div>

            <x-multi-datalist name="espansioni" label="Espansione" :options="$expansions"
                :selected="$filters['espansioni'] ?? []" />

            <x-multi-datalist name="tratti" label="Tratto" :options="$traits"
                :selected="$filters['tratti'] ?? []" />

            {{-- Badge --}}
            <div class="lg:col-span-2">
                <x-input-label value="Tipo" />
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($types as $type)
                        <span>
                            <input type="checkbox" name="tipi[]" value="{{ $type }}"
                                id="tipo-{{ Str::slug($type) }}" class="hidden peer"
                                @checked(in_array($type, (array) ($filters['tipi'] ?? [])))>
                            <label for="tipo-{{ Str::slug($type) }}"
                                class="inline-block cursor-pointer select-none rounded-md border-2 bg-transparent px-2 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 peer-checked:bg-gray-600 peer-checked:text-white">
                                {{ $type }}
                            </label>
                        </span>
                    @endforeach
                </div>
            </div>

            <div>
                <x-input-label value="Aspetto" />
                <div class="mt-1 flex flex-wrap gap-2">
                    @foreach ($aspects as $aspect)
                        <span>
                            <input type="checkbox" name="aspetti[]" value="{{ $aspect->id }}"
                                id="aspetto-{{ $aspect->id }}" class="hidden peer"
                                @checked(in_array($aspect->id, (array) ($filters['aspetti'] ?? [])))>
                            <label for="aspetto-{{ $aspect->id }}"
                                class="inline-block cursor-pointer select-none rounded-md border-2 bg-transparent px-2 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 peer-checked:bg-gray-600 peer-checked:text-white">
                                {{ $aspect->name }}
                            </label>
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Slider a doppio input --}}
            <x-range-slider name="costo" label="Costo" :min="$bounds['cost'][0]" :max="$bounds['cost'][1]"
                :selected-min="$filters['costo_min'] ?? null" :selected-max="$filters['costo_max'] ?? null" />

            <x-range-slider name="vita" label="Vita" :min="$bounds['health'][0]" :max="$bounds['health'][1]"
                :selected-min="$filters['vita_min'] ?? null" :selected-max="$filters['vita_max'] ?? null" />

            <x-range-slider name="potenza" label="Potenza" :min="$bounds['power'][0]" :max="$bounds['power'][1]"
                :selected-min="$filters['potenza_min'] ?? null" :selected-max="$filters['potenza_max'] ?? null" />

            {{-- Azioni --}}
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="unique_card" value="1" @checked(!empty($filters['unique_card']))
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">Solo carte uniche</span>
                </label>
            </div>

            <div class="flex items-end gap-3 lg:col-span-2">
                <x-primary-button>Cerca</x-primary-button>
                <a href="{{ route('cards.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Azzera</a>
            </div>
        </form>
    </div>
</div>
```

`resources/views/cards/index.blade.php` **non cambia** in questa parte: `x-cards-filter` usa il default di `bounds`.

## Step 5 — Build e verifica visiva

- Rebuild degli asset (il CSS nuovo deve finire nel bundle): `npm run build`, oppure con il compose di sviluppo lo fa già il rebuild automatico degli asset.
- Aprire `/carte`, espandere "Filtri" e controllare:
  - [ ] **Espansione**: scrivendo `SO` compare il suggerimento `SOR`; sceglierlo crea il chip con la `×`; la `×` lo toglie; l'opzione scelta sparisce dai suggerimenti
  - [ ] **Espansione**: un testo che non corrisponde a nessuna espansione non crea nessun chip
  - [ ] **Tratto**: stessa cosa, incluso `Twi'lek` e `Cacciatore Di Taglie`
  - [ ] **Invio** nei due campi non invia il form
  - [ ] **Costo / Vita / Potenza**: i due pallini si muovono indipendentemente, non si incrociano, la barra scura segue i pallini, il testo a destra del titolo si aggiorna; con i due pallini sullo stesso punto (anche all'estremo destro) si riescono a trascinare entrambi
  - [ ] **Tipo / Aspetto**: cliccare una label seleziona solo quel badge (e non i successivi)
  - [ ] Layout corretto a larghezza telefono, tablet e desktop; gli slider si usano anche col touch
  - [ ] Nel DevTools: `<form>` contiene gli `input[name="espansioni[]"]` e `input[name="tratti[]"]` nascosti solo per i chip scelti, più `costo_min`, `costo_max`, `vita_min`, `vita_max`, `potenza_min`, `potenza_max`
- Nota attesa: finché non si fa la Parte B il form **non filtra ancora nulla** (`CardController` e `CardSearch` leggono ancora i nomi singoli `espansione`, `tipo`, `aspetto`, `tratto`, `costo`) e i limiti degli slider sono quelli provvisori.

## Step 6 — (opzionale) Animazione di apertura dei filtri

Per far funzionare `x-collapse` nel componente: `npm install @alpinejs/collapse`, poi in `resources/js/app.js`:

```js
import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

window.Alpine = Alpine;

Alpine.start();
```

Se si preferisce non aggiungere la dipendenza, togliere `x-collapse` dal `<div x-show="open" ...>` (non cambia il comportamento, solo l'animazione).

## Step 7 — `todo.md` e README

- `todo.md`: già aggiornato con la sezione di questo piano (spuntare le voci man mano).
- README: non toccarlo finché la grafica non è applicata; poi aggiungere (nella sezione sulla lista carte) i componenti `x-range-slider` e `x-multi-datalist`, i nuovi parametri GET e, **solo dopo la Parte B**, il comportamento reale dei filtri.
- A lavoro finito (Parte A + Parte B): rinominare questo file in `implementationPlan-V-filtroCarteGrafica.md`.

---

# Parte B — Backend (da dettagliare dopo la verifica della grafica)

Scheletro, per non perdere le decisioni già emerse. I passi operativi con il codice li scrivo in questo file quando la Parte A è confermata.

## B.1 — `CardController::index`: nuovi nomi dei filtri

`$request->only([...])` deve passare a `CardSearch` i nomi nuovi: `nome`, `espansioni`, `tipi`, `aspetti`, `tratti`, `costo_min`, `costo_max`, `vita_min`, `vita_max`, `potenza_min`, `potenza_max`, `unique_card`.

## B.2 — Limiti reali degli slider dal DB

Un'unica query aggregata (`min`/`max` di `cost`, `health`, `power`, che ignorano i `NULL`), passata alla vista come `$bounds` con la stessa struttura della prop di `x-cards-filter` (`['cost' => [min, max], 'health' => [...], 'power' => [...]]`), e girata a `<x-cards-filter :bounds="$bounds">` in `cards/index.blade.php`. Valutare una cache breve (i limiti cambiano solo con l'import delle carte) e fallback `[0, 0]` se la tabella è vuota (il componente gestisce `max == min`).

## B.3 — `CardSearch::apply`: logica dei filtri

- `espansioni` e `tipi` (colonne dirette): `whereIn`.
- `aspetti` e `tratti` (relazioni): un `whereHas` per ogni valore selezionato → logica AND (la carta deve averli tutti).
- `costo`/`vita`/`potenza`: ogni estremo è valutato **da solo**. Gli slider inviano sempre entrambi i valori anche se non li tocchi, quindi un estremo è "attivo" solo se è diverso dal limite del DB:
  - `*_min` attivo solo se `*_min > min DB` → `where(colonna, '>=', *_min)`;
  - `*_max` attivo solo se `*_max < max DB` → `where(colonna, '<=', *_max)`;
  - estremo fermo sul suo limite = estremo non filtrato (così un pallino lasciato a 0 non scarta nulla).
  - Se nessuno dei due estremi è attivo il campo non viene filtrato e le carte con valore `NULL` (Basi, Segnalini, ecc.) restano nei risultati.
- Decisione presa: appena almeno un estremo è attivo le carte con valore `NULL` per quel campo vengono scartate (è il comportamento naturale del confronto SQL, nessun codice in più); con tutti e due gli estremi attivi vale a maggior ragione.
- Limite noto: con lo slider tutto aperto (da min a max) non si può chiedere "solo carte che hanno un costo", perché equivale a nessun filtro. Se servisse, aggiungere in seguito una checkbox dedicata.
- `CardSearch` è condiviso con l'API pubblica (Fase 11): i nuovi nomi vanno riportati nella documentazione dell'API quando esisterà.

## B.4 — Test

Test Pest di `CardSearch` per: più aspetti in AND, `whereIn` su espansioni/tipi, intervallo che coincide con i limiti (nessun filtro), intervallo ristretto, carte con valori `NULL`.
