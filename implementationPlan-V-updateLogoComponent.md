# Implementation Plan: Update Logo Component

## Goal
Modify the `application-logo.blade.php` component to usare l'immagine `icon-mine.svg` esterna tramite la funzione `asset()` invece dell'SVG inline.

## File da modificare
- `resources/views/components/application-logo.blade.php`

## Passaggi
1. Aprire il file `resources/views/components/application-logo.blade.php`.
2. Sostituire il contenuto attuale (il tag `<svg>` inline) con un tag `<img>` che punta all'asset corretto, assicurandosi di mantenere `{{ $attributes }}` in modo che le classi CSS passate al componente continuino a funzionare.

### Nuovo contenuto del file:
```blade
<img src="{{ asset('icon-mine.svg') }}" {{ $attributes }} alt="Application Logo">
```
