# Implementation Plan — Toggle "Mancanti / Possedute" nella pagina Build Mazzo

Stato: COMPLETATO (verifica manuale del punto 5 da fare dopo il deploy)
Ambito: pagina "Build Mazzo" (`/mazzo/{user}/{mazzo}/build`), componente `DeckBuildManager` e relativo export TXT.
Fuori ambito (NON toccare): la tabella "Mazzo vs Collezione" e il suo filtro esistente (`$filtro`: tutte / mancanti / possedute), i KPI in alto, `mazzi/show.blade.php`, `DeckManager`.

## 1. Richiesta

Nella pagina di deckbuilding (Build Mazzo) la lista testuale generata (textarea + export TXT + pulsante "Scarica" in intestazione) oggi contiene SOLO le carte mancanti. L'utente deve poter scegliere con un toggle se la lista generata sia quella delle carte **mancanti** o quella delle carte **presenti** (possedute) in collezione.

## 2. Decisioni di progetto

- Il toggle è indipendente dal filtro della tabella (`$filtro`): il filtro tabella cambia solo cosa si vede nella tabella, il nuovo toggle cambia solo la lista testuale generata / esportata.
- Default: `mancanti` (comportamento attuale invariato, anche per l'export chiamato senza parametri).
- Definizione "carte presenti": per ogni carta del mazzo, quantità = `min(copie nel mazzo, copie in collezione)`; sono incluse solo le carte con quantità > 0. Così `mancanti + possedute = totale carte del mazzo` e la lista possedute è direttamente "quello che ho già per questo mazzo".
- Formato riga identico per entrambe le liste: `{qty}x {espansione} {numero} {nome} ({rarità})`.
- Ordinamento: stesso `CardsController::mergeSort` già usato.
- Lo stato del toggle vive in una proprietà pubblica Livewire (`$tipoLista`, stringa) e viene passato all'export come query string `?tipo=mancanti|possedute`. Qualunque valore diverso da `possedute` viene trattato come `mancanti` (la proprietà pubblica Livewire è manomettibile dal client).

## 3. Modifiche file per file

### 3.1 `app/Livewire/DeckBuildManager.php`
- Nuova proprietà pubblica `public $tipoLista = 'mancanti'; // 'mancanti', 'possedute'` accanto a `$filtro`.
- In `render()`:
  - normalizzare: `$tipoLista = $this->tipoLista === 'possedute' ? 'possedute' : 'mancanti';`
  - nel ciclo sulle composizioni, oltre a `$missingCards`, costruire `$ownedCards`: se `min($copieMazzo, $copieCollezione) > 0` clonare la carta e valorizzare `copie_possedute`.
  - ordinare `$ownedCards` con lo stesso blocco `load('aspects')` + `mergeSort` di `$missingCards` (estrarre il blocco in un metodo privato `ordinaCarte($collection)` usato per entrambe le collezioni, per non duplicarlo).
  - scegliere la collezione della lista in base a `$tipoLista` e generare `$listaTxt` con la quantità corretta (`copie_mancanti` oppure `copie_possedute`).
  - passare alla view: `listaTxt` (sostituisce `missingTxt`), `tipoLista`, `countCarteListaDistinte` non necessario (i KPI restano quelli esistenti).
- Nessuna modifica a KPI, filtro tabella, `modificaCopiaCollezione()`.

### 3.2 `resources/views/livewire/deck-build-manager.blade.php`
- Pulsante "Scarica Lista Mancanti TXT" in intestazione → etichetta dinamica ("Scarica Lista Mancanti TXT" / "Scarica Lista Possedute TXT") e `route(..., ['tipo' => $tipoLista])`.
- Card "Lista Carte Mancanti (Post-MergeSort)": titolo/icona dinamici in base a `$tipoLista`; aggiungere nell'header un `btn-group` con due pulsanti (`wire:click="$set('tipoLista', 'mancanti')"` / `'possedute'`), stesso stile Bootstrap del filtro tabella (danger per mancanti, success per possedute, versione outline quando non attivo).
- Link "Esporta TXT" nella card → aggiungere `'tipo' => $tipoLista`.
- Textarea: `id` da `missingTxtContent` a `listaTxtContent`, contenuto `$listaTxt`.
- Stato vuoto: se `mancanti` resta il messaggio "Complimenti!"; se `possedute` mostrare un alert `alert-info` ("Non possiedi ancora nessuna delle carte necessarie per questo mazzo.").
- JS: rinominare `copyMissingTxtToClipboard` → `copyListaTxtToClipboard`, aggiornare id textarea e messaggio dell'alert ("Nessuna carta da copiare!").

### 3.3 `app/Http/Controllers/DecksController.php` — `exportBuildTxt()`
- Leggere `$tipo = $request->query('tipo') === 'possedute' ? 'possedute' : 'mancanti';` → aggiungere `Request $request` alla firma: `exportBuildTxt(Request $request, $user, $deck)` (Laravel risolve la Request per type-hint, la route non cambia).
- Nel ciclo composizioni: per `mancanti` comportamento attuale; per `possedute` quantità `min($copieMazzo, $copieCollezione)`, includendo solo quantità > 0.
- Stessa riga di output, stesso mergeSort.
- Nome file: `..._carte_mancanti.txt` oppure `..._carte_possedute.txt`.
- Aggiornare il PHPDoc del metodo (bilingue EN/IT come da standard del progetto) descrivendo il parametro `tipo`.

### 3.4 `routes/web.php`
- Nessuna modifica: la route `mazzo.build.export` accetta già query string.

### 3.5 Documentazione e todo
- `documentation.md`: nella sezione "Componenti Livewire" / lista classi documentate aggiornare la descrizione di `DeckBuildManager` (lista generata selezionabile tra mancanti e possedute, export TXT con parametro `tipo`). Nota: il progetto non ha un `README.md`, la documentazione di riferimento per chi entra nello sviluppo è `documentation.md`.
- `todo.md`: aggiungere in "Effettiva" la voce `- [X] aggiungere toggle mancanti/possedute alla lista generata nella pagina build mazzo`.

## 4. Ordine di esecuzione

1. `DeckBuildManager.php`
2. `deck-build-manager.blade.php`
3. `DecksController::exportBuildTxt()`
4. `documentation.md`
5. `todo.md`
6. Verifica statica incrociata (nessun riferimento residuo a `missingTxt`, `missingTxtContent`, `copyMissingTxtToClipboard`).
7. Rinominare questo file in `implementationPlan-V-ToggleListaBuildMancantiPossedute.md`.

## 5. Verifica (manuale, da fare dopo il deploy)

- [ ] Aprire la pagina Build di un mazzo con carte parzialmente possedute: di default il toggle è su "Mancanti" e la lista è identica a prima.
- [ ] Premere "Possedute": textarea, titolo e label del pulsante in intestazione cambiano; le righe hanno quantità = `min(mazzo, collezione)`.
- [ ] Somma delle quantità mancanti + possedute = "Totale Carte Mazzo" nei KPI.
- [ ] "Esporta TXT" / "Scarica" con toggle su Possedute scarica `*_carte_possedute.txt` con lo stesso contenuto della textarea; con toggle su Mancanti scarica `*_carte_mancanti.txt`.
- [ ] Aggiungere/rimuovere copie in collezione dai pulsanti +/- della tabella: la lista sotto toggle si aggiorna nella modalità corrente.
- [ ] Mazzo completamente posseduto: Mancanti → messaggio "Complimenti!"; Possedute → lista completa.
- [ ] Mazzo senza nessuna carta posseduta: Possedute → messaggio informativo.
- [ ] Il filtro della tabella (Tutte / Solo Mancanti / Solo Possedute) funziona come prima e non cambia il toggle della lista.
- [ ] URL export senza `tipo` (vecchi link/segnalibri) → lista mancanti come prima.

## 6. Rollback

Modifiche contenute in: `app/Livewire/DeckBuildManager.php`, `resources/views/livewire/deck-build-manager.blade.php`, `app/Http/Controllers/DecksController.php` (solo `exportBuildTxt`), `documentation.md`, `todo.md`. Ripristinabili da git.
