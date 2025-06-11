# Implementazione del Componente SearchFilter

## Panoramica

È stato implementato un componente Livewire `SearchFilter` avanzato che sostituisce il sistema di ricerca esistente nella pagina delle carte e si integra nel popup `AddCardPopUp` per migliorare l'esperienza utente.

## Caratteristiche Principali

### 1. Filtri Disponibili

Il componente offre filtri per tutti gli attributi del model `Card`:

**Filtri Standard:**
- **Nome**: Ricerca testuale con debounce
- **Titolo**: Ricerca testuale con debounce
- **Espansione**: Select con tutte le espansioni disponibili
- **Tipo**: Select con tutti i tipi di carta
- **Aspetto Primario**: Select con tutti gli aspetti primari
- **Aspetto Secondario**: Select con tutti gli aspetti secondari
- **Rarità**: Select con tutte le rarità disponibili
- **Unica**: Select per carte uniche

**Filtri Avanzati (collassabili):**
- **Costo**: Range numerico (min-max)
- **Potenza**: Range numerico (min-max)
- **Vita**: Range numerico (min-max)
- **Tratti**: Ricerca testuale con debounce
- **Arena**: Select con tutte le arene disponibili
- **Artista**: Select con tutti gli artisti

### 2. Modalità di Utilizzo

Il componente supporta due modalità:

- **Modalità Pagina** (`mode='page'`): Per la pagina principale delle carte
- **Modalità Popup** (`mode='popup'`): Per il popup di aggiunta carte

### 3. Funzionalità Avanzate

- **Filtri Collassabili**: I filtri avanzati sono nascosti di default
- **Aggiornamento in Tempo Reale**: I risultati si aggiornano automaticamente
- **Ordinamento Automatico**: Utilizza il sistema di ordinamento esistente
- **Interfaccia Responsive**: Ottimizzata per dispositivi mobili
- **Contatore Risultati**: Posizionato tra i filtri e le carte, aggiornato dinamicamente
- **Solo Bootstrap 5.3**: Nessun CSS personalizzato, solo classi Bootstrap

## File Modificati/Creati

### Nuovi File

1. **`app/Livewire/SearchFilter.php`** - Logica del componente
2. **`resources/views/livewire/search-filter.blade.php`** - Template del componente

### File Modificati

1. **`resources/views/carte/index.blade.php`** - Integrazione del componente
2. **`app/Livewire/AddCardPopUp.php`** - Aggiunta supporto filtri
3. **`resources/views/livewire/add-card-pop-up.blade.php`** - UI aggiornata
4. **`resources/views/layouts/app.blade.php`** - Abilitazione Livewire e stack scripts
5. **`resources/sass/app.scss`** - Inclusione CSS personalizzato

## Utilizzo

### Nella Pagina Principale

```blade
@livewire('search-filter', ['mode' => 'page', 'initialEspansione' => $espansione])
```

### Nel Popup

```blade
@livewire('search-filter', ['mode' => 'popup'])
```

## Funzionalità Tecniche

### Eventi Livewire

- **`cardsFiltered`**: Emesso quando i filtri cambiano (modalità pagina)
- **`resetFilters`**: Per resettare tutti i filtri
- **`applyFiltersForPopup`**: Per ottenere carte filtrate nel popup

### Metodi Principali

- **`applyFilters()`**: Applica tutti i filtri e ordina i risultati
- **`resetAllFilters()`**: Resetta tutti i filtri ai valori di default
- **`loadFilterOptions()`**: Carica le opzioni per i select
- **`updateFilteredCards()`**: Aggiorna le carte filtrate nel popup

### Ordinamento

Il componente utilizza il metodo `mergeSort` esistente del `CardsController` per mantenere la compatibilità con l'ordinamento personalizzato delle carte.

## Miglioramenti dell'Interfaccia

### Design

- **Bootstrap 5.3 Puro**: Utilizza esclusivamente classi Bootstrap senza CSS personalizzato
- **Card Layout**: Il filtro è presentato in una card collassabile con ombra
- **Header Bootstrap**: Header blu primario con testo bianco
- **Icone FontAwesome**: Per migliorare l'usabilità
- **Layout Responsive**: Grid system Bootstrap per tutti i dispositivi
- **Contatore Risultati**: Alert info posizionato tra filtri e carte

### Accessibilità

- **Focus Management**: Outline personalizzati per la navigazione da tastiera
- **Label Semantiche**: Tutte le form hanno label appropriate
- **Responsive Design**: Ottimizzato per tutti i dispositivi

### Performance

- **Debounce**: Per i campi di testo (300ms)
- **Lazy Loading**: Aggiornamenti solo quando necessario
- **Caching Opzioni**: Le opzioni dei select sono caricate una volta

## Compatibilità

Il sistema è completamente compatibile con:

- **Laravel 10+**: Utilizza `whereLike` nativo
- **Livewire 3**: Sintassi moderna
- **Bootstrap 5**: Classi CSS esistenti
- **Sistema Esistente**: Mantiene l'ordinamento personalizzato

## Testing

Per testare l'implementazione:

1. Compilare gli asset: `npm run build`
2. Avviare il server: `php artisan serve`
3. Navigare a `/carte`
4. Testare i vari filtri
5. Verificare il popup di aggiunta carte

## Note di Sviluppo

- Il componente è progettato per essere estensibile
- I filtri possono essere facilmente aggiunti/rimossi
- L'interfaccia è modulare e riutilizzabile
- Il codice segue le best practice di Livewire

## Prossimi Sviluppi

Possibili miglioramenti futuri:

- **Salvataggio Filtri**: Persistenza dei filtri in sessione
- **Filtri Predefiniti**: Set di filtri comuni
- **Export Risultati**: Esportazione delle carte filtrate
- **Filtri Avanzati**: Combinazioni logiche complesse
