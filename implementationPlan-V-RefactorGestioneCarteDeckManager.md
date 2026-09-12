# Implementation Plan — Refactor gestione carte nella pagina Deck Manager

Stato: COMPLETATO
Ambito: SOLO la sezione di gestione carte della pagina `mazzi/show.blade.php` (aggiunta carte, lista "Mazzo", "Carte aggiunte", "Carte rimosse", salvataggio).
Fuori ambito (NON toccare): la sezione "Analisi Statistiche del Mazzo" in `deck-manager.blade.php` (grafici Chart.js, `calcolaStatistiche()`), le pagine `carte/index.blade.php` e `collezione/index.blade.php`, `DeckBuildManager` (pagina "Build Mazzo").

## 1. Problema da risolvere

1. **Bug funzionale** (in parte già mitigato in questa conversazione con `#[On]`): l'aggiunta di una carta non ancora presente nel mazzo non funzionava per via della sintassi Livewire v2 (`protected $listeners`) non supportata in Livewire 3.
2. **Bug strutturale (causa del 413 Request Entity Too Large)**: `DeckManager` (proprietà `$cards`) e `AddCardSection` (proprietà `$availableCards`/`$filteredCards`) tengono l'intero catalogo carte come stato pubblico Livewire. Livewire re-invia l'intero snapshot dei componenti ad ogni interazione (anche solo aprire un radio delle copie), quindi ogni richiesta AJAX porta con sé l'intero catalogo carte serializzato → supera facilmente il limite nginx.
3. **Richiesta esplicita dell'utente**: rifare anche graficamente la sezione di gestione carte (non il bugfix minimale), mantenendo lo stile del sito (stesso stile "card" a griglia con immagine/aspetti/rarità già usato in `carte/index.blade.php` e `collezione/index.blade.php`).

## 2. Architettura attuale (da sostituire)

```
mazzi/show.blade.php
 └─ <livewire:deck-manager :carte="tutte le carte" :mazzo="composizione mazzo">
      ├─ proprietà pubbliche: $cards (TUTTO il catalogo), $mazzo, $aggiunte, $rimosse
      ├─ @livewire('add-card-section', ['availableCards' => $cards, ...])
      │    ├─ proprietà pubbliche: $availableCards (TUTTO il catalogo), $filteredCards
      │    ├─ @livewire('search-filter', ['mode' => 'popup'])  (dispatcha 'cardsFiltered')
      │    ├─ <select> con TUTTE le carte filtrate
      │    └─ bottone "Aggiungi al mazzo" → dispatch('cardAdded', {cardId, copies})
      └─ mazzo/aggiunte/rimosse renderizzati come semplici righe di testo con +/-
```

## 3. Architettura nuova

```
mazzi/show.blade.php
 └─ <livewire:deck-manager :mazzo="composizione mazzo">   (NIENTE PIÙ :carte)
      ├─ proprietà pubbliche: SOLO $mazzo, $aggiunte, $rimosse (limitate alla dimensione del mazzo, mai al catalogo)
      ├─ #[On('cardAdded')] addCard($data) riceve i DATI COMPLETI della carta dal JS (non più lookup su un catalogo interno)
      ├─ dispatch('deckCompositionChanged', [id => copie]) ogni volta che il mazzo cambia (payload piccolo)
      └─ pannelli "Mazzo" / "Carte aggiunte" / "Carte rimosse" ristilizzati come griglia di card compatte (stesso stile sito)

  (nella stessa pagina, FUORI da deck-manager, per evitare stato pesante)
 └─ @livewire('search-filter', ['mode' => 'popup'])   (INVARIATO, dispatcha 'cardsFiltered')
 └─ <div id="add-card-results">...</div>  popolato via JS puro (stesso pattern di carte/index.blade.php)
      └─ ogni card ha uno stepper copie + bottone "Aggiungi"
           → Livewire.dispatchTo('deck-manager', 'cardAdded', {card: {...}, copies: n})

Component AddCardSection: RIMOSSO (file .php e .blade.php eliminati)
```

Punto chiave: **nessun componente Livewire tiene più il catalogo completo delle carte come proprietà pubblica**. Il catalogo viene interrogato dal DB solo da `SearchFilter` (che già lo fa) e non viene mai duplicato altrove. `DeckManager` riceve i dati della singola carta aggiunta via evento JS invece di cercarli in un array locale.

## 4. Modifiche file per file

### 4.1 `app/Http/Controllers/DecksController.php`
- Metodo `show()`: rimuovere il recupero di `$carte` (query `Card::select(...)->get()`) e la relativa chiave `"carte" => $carte` passata alla view (non più necessaria).

### 4.2 `resources/views/mazzi/show.blade.php`
- Rimuovere `:carte="$carte"` dal tag `<livewire:deck-manager>`.
- Aggiungere, sotto il componente `deck-manager`, il blocco "Aggiungi carte" con `@livewire('search-filter', ['mode' => 'popup'])` + contenitore `#add-card-results` + script di rendering (vedi 4.5). Nota: la sezione "Aggiungi carte" deve restare visibile solo per `$proprietario` (stessa logica già presente).

### 4.3 `app/Livewire/DeckManager.php`
- `mount()`: rimuovere il parametro `$carte` e la proprietà `$cards` (e la relativa conversione in `mapWithKeys`).
- Rimuovere `updateAddCardSection()` nella sua forma attuale (basata su `AddCardSection`); sostituire con `dispatchDeckComposition()` che fa `$this->dispatch('deckCompositionChanged', $currentDeckCards)`.
- `addCard($data)`: nuova firma, riceve `$data['card']` (array con TUTTI i campi della carta: id, espansione, numero, nome, snippet, maxCopie, tipo, tratti, costo, vita, potenza, aspects, frontArt, rarita, ecc.) e `$data['copies']`. Cicla `copies` volte chiamando un nuovo metodo `aggiungiCartaAlMazzo($cardData)`.
- Nuovo metodo `aggiungiCartaAlMazzo($cardData)`: stessa logica di `aumentaCopia()` ma usa `$cardData` passato (non un lookup su `$this->cards`) quando la carta non è ancora nel mazzo.
- `aumentaCopia($id)` (usato dai bottoni "+" su carte già presenti in "Mazzo" e "Carte rimosse"): quando la carta non è in `$this->mazzo`, invece di leggere da `$this->cards[$id]` (rimosso), usare `$this->rimosse[$id]` se presente (la carta è stata rimossa in precedenza e i suoi dati sono già lì). Se non è in nessuno dei due, non fare nulla (caso non dovrebbe più verificarsi: una carta "nuova" arriva sempre da `addCard()`).
- Dopo ogni modifica del mazzo (`aumentaCopia`, `diminuisciCopia`, `aggiungiCartaAlMazzo`) chiamare `dispatchDeckComposition()` invece di `updateAddCardSection()`.
- **NON toccare**: `calcolaStatistiche()`, `render()` (a parte la view invariata), tutte le proprietà/metodi relativi a statistiche, rinomina, save.

### 4.4 Rimozione `AddCardSection`
- Eliminare `app/Livewire/AddCardSection.php`.
- Eliminare `resources/views/livewire/add-card-section.blade.php`.

### 4.5 Nuovo script di rendering "Aggiungi carte" (dentro `mazzi/show.blade.php`, sezione `@push('scripts')`)
- Riusa lo stesso stile visivo delle card a griglia già presente in `carte/index.blade.php` (classe `innerCarta`, `rounded-4 border-primary-subtle bg-secondary-subtle`, badge aspetti, colori rarità tramite `toCssClass($rarita)`), ma con in più:
  - contatore "già nel mazzo: N / maxCopie"
  - stepper quantità (min 1, max = maxCopie - copie attuali nel mazzo)
  - bottone "Aggiungi" disabilitato se già al massimo
- Mantiene una mappa JS locale `deckComposition = {}` aggiornata da:
  - stato iniziale passato dal server (`@json(...)` delle copie attuali del mazzo, per popolare al primo render)
  - evento `Livewire.on('deckCompositionChanged', (data) => {...})` per gli aggiornamenti successivi
- Al click su "Aggiungi": `Livewire.dispatchTo('deck-manager', 'cardAdded', { card: cartaJson, copies: n })`, poi reset dello stepper.

### 4.6 Ristilizzazione pannelli "Mazzo" / "Carte aggiunte" / "Carte rimosse" in `deck-manager.blade.php`
- Sostituire le righe `<span class="d-flex mt-4">...</span>` con tile in stile card compatto (riquadro con piccola immagine `frontArt`, nome, badge rarità colorato, contatore copie, bottoni +/- come icone), mantenendo invariati i `wire:click="aumentaCopia(...)"` / `diminuisciCopia(...)`.
- Nessuna modifica alla sezione "Analisi Statistiche del Mazzo" più sotto nello stesso file.

### 4.7 Documentazione
- Aggiornare `documentation.md` (sezione architettura Livewire / componenti) per riflettere: rimozione di `AddCardSection`, nuovo flusso "aggiunta carte" basato su evento JS + `SearchFilter` diretto, nota sul limite `client_max_body_size` di nginx e la ragione architetturale (evitare stato Livewire con l'intero catalogo).

## 5. Ordine di esecuzione

1. `DecksController::show()` — rimuovere `$carte`.
2. `DeckManager.php` — refactor proprietà/metodi come sopra.
3. Eliminare `AddCardSection.php` e la sua view.
4. `mazzi/show.blade.php` — rimuovere `:carte`, aggiungere sezione "Aggiungi carte" con search-filter + JS di rendering.
5. `deck-manager.blade.php` — ristilizzare pannelli Mazzo/Aggiunte/Rimosse (solo quella parte, statistiche invariate).
6. Aggiornare `documentation.md`.
7. Verifica statica incrociata (nessun riferimento residuo a `add-card-section` o a `$this->cards` in `DeckManager`).

## 6. Verifica (manuale, da fare sul sito una volta deployato)

- [ ] Aprire un mazzo di proprietà, la sezione "Aggiungi carte" mostra i filtri e, dopo una ricerca, la griglia di carte.
- [ ] Aggiungere una carta MAI presente nel mazzo → compare in "Carte aggiunte" e il contatore si aggiorna, nessun 413 in console.
- [ ] Aggiungere ulteriori copie della stessa carta fino al `maxCopie` → il bottone "Aggiungi"/stepper si blocca al limite.
- [ ] Rimuovere una carta dal mazzo (bottone "-") → compare in "Carte rimosse"; ri-aggiungerla da lì col bottone "+" funziona ancora.
- [ ] Salvare il mazzo (bottone "Save") → redirect con messaggio di successo, versione mazzo incrementata, dati persistiti correttamente in DB.
- [ ] La sezione statistiche in fondo alla pagina si aggiorna correttamente aggiungendo/rimuovendo carte, invariata rispetto a prima.
- [ ] Controllare la Network tab del browser: le richieste `/livewire/update` restano piccole (poche decine di KB), non più i ~1MB+ di prima.

## 7. Rollback

Le modifiche sono contenute in: `DecksController.php` (metodo `show`), `app/Livewire/DeckManager.php`, `app/Livewire/AddCardSection.php` (eliminato), `resources/views/livewire/add-card-section.blade.php` (eliminato), `resources/views/mazzi/show.blade.php`, `resources/views/livewire/deck-manager.blade.php`, `documentation.md`. In caso di problemi, ripristinare queste versioni dal controllo versione (git) precedente a questo piano.
