# Implementation plan: fix logo 404 e navbar mobile in verticale

> Regola del progetto (`.agent/rules/main.md`): questo piano descrive le modifiche al codice, le applica l'utente. Io tocco solo piano, `todo.md` e `README.md`.

## Problemi
1. **Logo 404.** `resources/views/components/application-logo.blade.php` punta a `asset('icon-mine.svg')`, cioè `/icon-mine.svg`. Quel file vive solo in `public/` dell'immagine PHP: nginx serve soltanto `/build/*` (volume `build_assets`), `/storage/*` e le rotte PHP, quindi la richiesta finisce in `error_page 404 /index.php` e Laravel risponde 404. Le icone vere, generate dallo stage `icon-builder` del `Dockerfile` (`docker/icons/generate-icons.sh`), stanno in `public/build/icons/` e sono già servite (le usa `layouts/favicons.blade.php`).
2. **Navbar da telefono in verticale.** Su schermi < `sm` (640px) i link desktop sono `hidden` e dovrebbe comparire il pulsante hamburger che apre il blocco responsive. Non compare nulla di usabile. Cause da verificare (vedi Step 0), con un fix che non dipende da quale sia quella giusta.

## Step 0 — Diagnosi della navbar (2 minuti, DevTools in modalità mobile, ~375px)
Aprire una pagina qualsiasi, ispezionare `<nav>` e controllare:
- **a)** il pulsante hamburger è nel DOM ma fuori schermo (overflow orizzontale del contenitore sinistro con logo + nome)? Guardare la larghezza di `div.flex` (quello con logo e link) e la posizione del bottone.
- **b)** l'hamburger c'è ma il click non apre il menu: in *Computed* del `div` "Responsive Navigation Menu", dopo il click `display` resta `none` anche con la classe `block` presente? Se sì, il CSS **non a layer** di Bladewind (`bladewind-ui-no-preflight.min.css`, caricato in `layouts/app.blade.php`) batte le utility Tailwind 4, che stanno in `@layer utilities` (in CSS un layer perde sempre contro uno stile senza layer, qualunque sia la specificità).
- **c)** l'hamburger non c'è proprio: controllare in *Computed* di `div.sm\:hidden` che `display` non sia `none` sotto 640px.

Annotare quale ipotesi è vera: lo Step 2 le copre tutte, ma se è la (b) vale la pena anche lo Step 2.4.

## Step 1 — Logo dai file generati dal build
File: `resources/views/components/application-logo.blade.php`

Sostituire il contenuto con:
```blade
{{-- Logo del sito: PNG generato dal Dockerfile (stage icon-builder) in public/build/icons.
     Non referenziare public/icon-mine.svg: nginx non lo serve (vedi layouts/favicons.blade.php) --}}
<img src="{{ asset('build/icons/android-chrome-512x512.png') }}" {{ $attributes->merge(['alt' => 'Logo ' . config('app.name')]) }}>
```
Note:
- Si usa il 512×512 (quadrato, trasparente) perché il logo compare a 64px nella navbar e a 256px in `layouts/guest.blade.php`: un solo file regge entrambi i casi, il browser lo riduce.
- `fill-current` e `text-*` sulle classi chiamanti non hanno più effetto su un `<img>` (non c'erano già con l'SVG esterno): si possono togliere in `navigation.blade.php` e `guest.blade.php`, ma non è obbligatorio.
- Il componente non va usato fuori dalle viste servite da nginx: in locale e in produzione `public/build/icons` esiste solo dopo il build dell'immagine (`docker compose -f docker-compose.dev.yml up -d --build`). Senza build il logo dà 404, come già le favicon.

## Step 2 — Navbar mobile robusta
File: `resources/views/layouts/navigation.blade.php` e `resources/css/app.css`.

### 2.1 Contenitore sinistro che non spinge fuori l'hamburger
Nella riga principale (`<div class="flex justify-between h-16">`) e nel blocco del logo:
```blade
<div class="flex min-w-0">
    <!-- Logo -->
    <div class="shrink-0 flex items-center min-w-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <x-application-logo class="h-10 w-10 shrink-0" />
            <span class="truncate text-xl font-bold">{{ config('app.name') }}</span>
        </a>
    </div>
    ...
```
- `h1` → `span`: nella navbar il titolo di pagina è già nello slot `header`, avere un `<h1>` in ogni pagina è scorretto per l'accessibilità. Se si preferisce tenerlo, basta mantenere `class="truncate ..."`.
- Logo a `h-10 w-10` (non più `h-16 w-auto`): in una barra alta 64px il logo da 64px toccava i bordi.

### 2.2 Hamburger che non si restringe
Sul contenitore del pulsante:
```blade
<div class="-me-2 flex items-center shrink-0 ms-2 sm:hidden">
```
e aggiungere `aria-label="{{ __('Menu') }}"` e `:aria-expanded="open"` al `<button>`.

### 2.3 Apertura del menu con `x-show`, non con le classi `hidden`/`block`
Sostituire il blocco responsive:
```blade
<!-- Responsive Navigation Menu -->
<div x-show="open" x-cloak x-collapse class="sm:hidden">
```
al posto di `:class="{ 'block': open, 'hidden': !open }" class="hidden sm:hidden"`. `x-show` scrive `display` inline, quindi non è soggetto a conflitti di CSS tra Bladewind e Tailwind; `x-collapse` è già installato (`resources/js/app.js` registra `@alpinejs/collapse`) e anima l'apertura.

Stesso trattamento per le due icone del pulsante (hamburger / X): al posto di `:class="{ 'hidden': open, 'inline-flex': !open }"` usare `x-show="!open"` e `x-show="open" x-cloak` sui rispettivi `<path>` (in un `<svg>` va bene `x-show` su `<g>`: avvolgere ciascun `<path>` in un `<g x-show=...>`).

### 2.4 Regola `x-cloak` (necessaria per 2.3)
In `resources/css/app.css`, fuori da qualunque `@layer`, per non essere battuta dal CSS di Bladewind:
```css
/* Alpine: nasconde gli elementi x-cloak finché Alpine non è partito */
[x-cloak] { display: none !important; }
```

### 2.5 Solo se lo Step 0 ha confermato l'ipotesi (b) — CSS Bladewind non a layer
Rendere il CSS di Bladewind un layer a precedenza bassa, così le utility Tailwind tornano a vincere. In `layouts/app.blade.php` sostituire il `<link>` di Bladewind con:
```blade
<style>
    @import url('{{ asset('vendor/bladewind/css/bladewind-ui-no-preflight.min.css') }}') layer(bladewind);
</style>
```
e, in cima a `resources/css/app.css`, dichiarare l'ordine dei layer prima di `@import 'tailwindcss'`:
```css
@layer bladewind, theme, base, components, utilities;
```
Poi verificare che i componenti Bladewind (datepicker di `/nuove-uscite`, `<x-bladewind.card>` in `cards/show`) mantengano l'aspetto. Se qualcosa si rompe, annullare questo sotto-step: il fix 2.1–2.4 basta da solo per l'hamburger.

## Step 3 — Build e verifica
1. `docker compose -f docker-compose.dev.yml up -d --build` (serve per rigenerare `public/build/icons` e ricompilare il CSS con le nuove classi).
2. DevTools → richieste di rete: `build/icons/android-chrome-512x512.png` risponde 200, niente più 404 su `icon-mine.svg`.
3. Mobile (~375px, verticale): logo + nome visibili e troncati con `…` se lunghi, hamburger sempre a destra; il tocco apre/chiude il menu con animazione; i link cambiano con ospite / utente / admin (`@can`); il tema scuro regge.
4. Orizzontale (≥640px): link desktop visibili, hamburger nascosto.
5. Pagine guest (`/login`, `/register`): logo a 256px nitido (`layouts/guest.blade.php`).

## Step 4 — Documentazione
1. `README.md`: nella parte su icone/layout annotare che il logo `<x-application-logo>` usa `public/build/icons/android-chrome-512x512.png` (generato dal build) e non `public/icon-mine.svg`; se il README descrive la navbar, riportare il menu mobile con `x-show`.
2. `todo.md`: spuntare le voci della sezione "Fix logo e navbar mobile".
3. Rinominare questo file in `implementationPlan-V-fixLogoENavbarMobile.md`.
