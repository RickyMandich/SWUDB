# Implementation Plan — Completamento dark mode

Continua `implementationPlan-V-12-darkModeUi.md`. Il tema scuro segue le preferenze del sistema (`prefers-color-scheme`, Tailwind `darkMode: 'media'` di default: `tailwind.config.js` non lo cambia).

## Problemi segnalati
1. **Navbar desktop**: la voce della pagina attiva (`x-nav-link`, `active`) ha `text-gray-900` senza variante dark → quasi invisibile su `dark:bg-gray-800`.
2. **`<select>` normale** (filtro stato in `admin/errors/index`, gruppo in `admin/expansions/index`): usa solo `rounded-md border-gray-300 shadow-sm`, quindi in dark resta bianco con bordo chiaro, fuori palette.

## Verifica del contrasto (WCAG AA: 4.5:1 testo, 3:1 componenti UI)
| Coppia (dark) | Rapporto | Esito |
|---|---|---|
| `gray-100` su `gray-800` (nav attiva) | ~12:1 | OK |
| `gray-400` su `gray-800` (nav non attiva) | ~5.9:1 | OK |
| `indigo-300` su `indigo-900/50` (nav responsive attiva) | > 7:1 | OK |
| `gray-300` su `gray-900` (input, select) | ~11:1 | OK |
| `gray-400` su `gray-900` (testo secondario, label slider) | ~6:1 | OK |
| `gray-600` su `gray-900` (testo "Showing" della paginazione, oggi) | ~2.9:1 | **KO** → `dark:text-gray-400` |
| `gray-400` su `gray-100` (label slider in light, oggi) | ~2.4:1 | **KO** → `text-gray-500` |

## Step

### 1. Componenti di navigazione
- `components/nav-link.blade.php`
  - attivo: aggiungere `dark:text-gray-100 dark:border-indigo-400 dark:focus:border-indigo-300`
  - non attivo: `dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600 dark:focus:text-gray-200 dark:focus:border-gray-600`
- `components/responsive-nav-link.blade.php`
  - attivo: `dark:text-indigo-300 dark:bg-indigo-900/50 dark:border-indigo-400 dark:focus:text-indigo-200 dark:focus:bg-indigo-900/70 dark:focus:border-indigo-300`
  - non attivo: `dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:text-gray-200 dark:focus:bg-gray-700 dark:focus:border-gray-600`

### 2. Select
- Creare `components/select-input.blade.php` con le stesse classi di `x-text-input` (`dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700` e focus indigo), così input e select restano coerenti.
- Usarlo in `admin/errors/index` (filtro stato) e `admin/expansions/index` (gruppo).
- `x-text-input`: aggiungere `dark:[color-scheme:dark]` (l'icona del calendario di `type="date"` in dark era scura su fondo scuro).

### 3. Componenti restanti
- `secondary-button`: `dark:bg-gray-800 dark:border-gray-500 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800`
- `input-error`: `dark:text-red-400`; `auth-session-status`: `dark:text-green-400`
- `modal`: pannello `dark:bg-gray-800`, overlay `dark:bg-gray-900`
- `range-slider`: etichette min/max `text-gray-500 dark:text-gray-400` (contrasto light)
- `pagination.blade.php`: "Showing … results" `dark:text-gray-400`

### 4. Viste
Sfondo secondario `bg-white dark:bg-gray-800`, testo `text-gray-900 dark:text-gray-100` / `text-gray-600 dark:text-gray-400`, bordi `dark:border-gray-700` (palette del README) in:
- `dashboard`, `profile/edit` e i tre partial di `profile/partials/`
- `admin/errors/index` e `show`, `admin/users/index` e `edit`, `admin/expansions/index` (select e checkbox)
- `auth/*` (testi, link, checkbox "Remember me")
- Titoli `<h2>` negli slot `header` senza `dark:text-gray-200`
- Checkbox: pattern Breeze `dark:bg-gray-900 dark:border-gray-700 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800`
- Colori degli aspetti: **non toccati** (fissi a DB)

### 5. Documentazione
- `README.md`, sezione "Dark Mode e Pallette UI": aggiungere navigazione attiva/non attiva, input/select/checkbox, link, e la nota che il tema segue il sistema.
- `todo.md`: nuova voce 12.5.

### 6. Verifica
- Ricostruire gli asset (`docker compose -f docker-compose.dev.yml up -d --build`, oppure `npm run dev`) e controllare in dark e light: navbar (pagina attiva, hover, menu responsive), `/admin/errori` (select), `/admin/espansioni` (select, date, checkbox), `/profile`, `/login`, `/carte`.
- Rinominare questo file in `implementationPlan-V-completamentoDarkMode.md`.
