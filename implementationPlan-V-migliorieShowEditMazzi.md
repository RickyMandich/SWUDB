# Piano di Implementazione: Migliorie a Show ed Edit del Mazzo

Questo piano definisce i dettagli e i passi per applicare le modifiche richieste alle viste `decks/show` e `decks/edit`, con estensioni trasversali (hover/mobile preview e statistiche incorporate a fondo pagina).

---

## 1. Dettaglio Modifiche

### 1.1 Pagina Show (`resources/views/decks/show.blade.php`)
- **Distinzione Unità Terrestri e Spaziali**:
  - Attualmente le carte del corpo del mazzo vengono raggruppate per `$card->type`.
  - Quando `$card->type === 'Unità'`, distinguere in base alla colonna `$card->arena`:
    - `arena` = `'Terrestre'` (o case-insensitive / 'Ground') -> gruppo `"Unità terrestri"`
    - `arena` = `'Spaziale'` (o case-insensitive / 'Space') -> gruppo `"Unità spaziali"`
    - altro/nullo -> gruppo `"Unità"`
  - Ordinamento delle sezioni: "Unità terrestri", "Unità spaziali", "Miglioria" (o altre), "Evento", ecc.

### 1.2 Pagina Edit (`resources/views/decks/edit.blade.php`)
- **Link alla lista delle carte**:
  - Nella lista delle carte del mazzo (Alpine `rows`), il nome della carta diventa un link interattivo con `target="_blank"` che punta alla scheda della carta (`route('cards.show', [$c->expansion, $c->number])`).
  - Passare `'url'` sia nel mapping iniziale `$rows` in Blade, sia nel controller `DeckController::searchCards`, sia nella funzione `add(card)`.
- **Allineamento dei tipi delle carte**:
  - Sostituire il layout `justify-between` generico sulle 3 parti con un layout flex a colonne fisse / strutturate:
    - Colonna nome carta: `flex-1 min-w-0 pr-2 truncate`
    - Colonna tipo carta: `w-28 shrink-0 text-left` (allineato verticalmente per ogni riga)
    - Colonna quantità e azioni: `flex items-center justify-end gap-2 shrink-0 w-36`
- **Rimozione pulsante salva in alto**:
  - Rimuovere il blocco con il pulsante "Salva modifiche" in alto sopra la card del mazzo.
  - Spostare l'indicatore reattivo `Modifiche non salvate` (`x-show="dirty"`) accanto al pulsante "Salva modifiche" in fondo alla pagina.

### 1.3 Entrambe le Pagine (Show ed Edit)
- **Anteprima della Carta (Desktop Hover + Mobile Modal)**:
  - **Desktop**: al passaggio del mouse (`@mouseenter` / `@mousemove` / `@mouseleave`), mostrare in sovraimpressione un tooltip fluttuante con la `front_art_path` della carta (`shadow-2xl`, bordo, angoli arrotondati, posizionamento clamped alla viewport per non debordare).
  - **Mobile / Touch**: quando il dispositivo è touch o con schermo ridotto, il tocco sul nome della carta apre un modal dialog con:
    - Nome della carta.
    - Immagine frontale grande (tappabile per andare alla pagina della carta).
    - Pulsante "Apri pagina della carta".
    - Pulsante "Chiudi" (o tocco all'esterno).
- **Statistiche incorporate a fondo pagina**:
  - Creare un partial Blade `resources/views/decks/partials/statistics.blade.php` contenente le statistiche del mazzo (carte totali, medie potenza/vita, curva costi con Chart.js, tipi di carta con Chart.js, tratti principali).
  - Includere il partial a fondo pagina sia in `decks/show.blade.php` sia in `decks/edit.blade.php`.
  - In `DeckController`:
    - Estrarre il calcolo statistiche in `getDeckStatisticsData(Deck $deck): array`.
    - Passare i dati delle statistiche sia a `show()` sia a `edit()`.
    - La rotta `decks.statistics` reindirizza alla vista `decks.show` con fragment `#statistiche`.
    - Rimuovere il pulsante "Statistiche" verso pagina esterna dall'header di `show` (o sostituirlo con link ad ancore `#statistiche`).

---

## 2. Passi di Esecuzione

1. **Backend / Controller**:
   - Aggiungere `getDeckStatisticsData(Deck $deck)` a `DeckController`.
   - In `DeckController::show`: passare i dati statistiche alla vista.
   - In `DeckController::edit`: passare i dati statistiche alla vista.
   - In `DeckController::searchCards`: aggiungere `'url'` a ogni elemento restituito.
   - In `DeckController::statistics`: redirect alla rotta `decks.show` con fragment `#statistiche`.
2. **Partial Statistiche**:
   - Creare `resources/views/decks/partials/statistics.blade.php` con grafici Chart.js e sezioni statistiche.
3. **Componente Anteprima / Pop-up Carta**:
   - Implementare l'anteprima sia per desktop (hover) sia per mobile (modal) via Alpine.
4. **Aggiornamento `resources/views/decks/show.blade.php`**:
   - Raggruppamento con distinzione "Unità terrestri" e "Unità spaziali".
   - Aggancio anteprima hover e mobile.
   - Inclusione del partial statistiche a fondo pagina.
5. **Aggiornamento `resources/views/decks/edit.blade.php`**:
   - Link alle carte in `rows` (e in `searchCards` / `add`).
   - Allineamento a colonna per i tipi di carta.
   - Rimozione pulsante salva in alto e spostamento avviso `dirty` in basso.
   - Aggancio anteprima hover e mobile.
   - Inclusione del partial statistiche a fondo pagina.
6. **Test e Documentazione**:
   - Eseguire i test di regressione (`DeckStatisticsTest`).
   - Aggiornare `README.md` e `todo.md`.
