# Implementation Plan 12 - UI Dark Mode

## Analisi
Attualmente il progetto utilizza Tailwind CSS, ma le classi per la dark mode (`dark:...`) sono parziali o assenti nelle viste e nei componenti. L'obiettivo è supportare in modo nativo il tema scuro utilizzando la classe `dark:` di Tailwind e documentare la pallette adottata per garantire coerenza futura, in linea con le convenzioni Tailwind/Breeze.
I colori degli aspetti (Vigilanza, Eroismo, ecc.) sono fissi a database e non subiranno variazioni dipendenti dal tema.

## Pallette
- **Background primario (sfondo della pagina)**:
  - Light: `bg-gray-100`
  - Dark: `dark:bg-gray-900`
- **Background secondario (header, schede, navbar)**:
  - Light: `bg-white`
  - Dark: `dark:bg-gray-800`
- **Testo principale**:
  - Light: `text-gray-800` o `text-gray-900`
  - Dark: `dark:text-gray-100` o `dark:text-gray-200`
- **Testo secondario / mutato**:
  - Light: `text-gray-500` o `text-gray-600`
  - Dark: `dark:text-gray-400`
- **Bordi**:
  - Light: `border-gray-100`, `border-gray-200`, `border-gray-300`
  - Dark: `dark:border-gray-700` o `dark:border-gray-600`
- **Background input**:
  - Light: `bg-white`
  - Dark: `dark:bg-gray-900`

## Step di implementazione

- [ ] **Step 1: Aggiornamento documentazione (`README.md` e `todo.md`)**
  - Aggiungere al `README.md` una sezione con le specifiche della pallette UI.
  - Aggiungere al `todo.md` questa fase sotto una nuova sezione `## 12 — Dark Mode UI`.

- [ ] **Step 2: Layouts**
  - In `layouts/app.blade.php`, `layouts/guest.blade.php`, aggiornare la pagina per utilizzare lo sfondo primario e il text mode per l'intera app (`dark:bg-gray-900 dark:text-gray-100`).

- [ ] **Step 3: Navigazione (`navigation.blade.php`)**
  - Adattare sfondo e testo, gestire hover ed espansioni del menu.

- [ ] **Step 4: Componenti e Viste base**
  - Esaminare componenti e applicare le classi dark (in particolare `components/card.blade.php`, `x-badge`, ecc.).

- [ ] **Step 5: Rinomina del piano**
  - Rinominare il file in `implementationPlan-V-12-darkModeUi.md`.
