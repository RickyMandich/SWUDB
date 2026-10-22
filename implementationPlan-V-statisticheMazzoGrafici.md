# Piano di Implementazione: Grafici Avanzati per le Statistiche del Mazzo

Questo piano definisce le modifiche richieste per potenziare la sezione delle statistiche del mazzo incorporata nelle pagine `decks/show` ed `decks/edit`.

---

## 1. Dettaglio Requisiti

1. **Rimozione quantità di carte**:
   - Rimuovere la card di riepilogo "Carte nel mazzo" dalle statistiche (è già visibile nell'intestazione del mazzo).

2. **Grafico Potenza e Vita medie per costo**:
   - Sostituire le card testuali delle medie con un grafico (line/scatter) in cui:
     - Asse X (ascisse): Costo delle unità.
     - Asse Y (ordinate): Valore medio a quel costo.
     - Linea rossa: Potenza media.
     - Linea blu: Vita media.
     - Dati pesati per copie di ciascuna unità.

3. **Curva dei costi con Toggle interattivi per tipo (Unità, Migliorie, Eventi)**:
   - Fornire i dati della curva dei costi suddivisi per tipo (`Unità`, `Miglioria`, `Evento`).
   - Nella vista, aggiungere tre toggle che utilizzano il componente `<x-badge>` (senza modificarne il codice sorgente) per selezionare qualsiasi combinazione di tipi.
   - Aggiornamento reattivo in tempo reale del grafico a barre via Alpine/Chart.js al toggle dei tipi.

4. **Grafico a torta per Aspetto (con supporto fette zebrate per carte multi-aspetto)**:
   - Raggruppare le carte per combinazione di aspetti posseduti.
   - Utilizzare i colori degli aspetti (`$aspect->color`) per ciascuna fetta.
   - Per carte con più aspetti, generare dinamicamente una fetta zebrata (pattern a strisce Canvas) contenente tutti i colori degli aspetti posseduti.

5. **Grafico a torta per Rarità**:
   - Raggruppare le carte per rarità, applicando la palette cromatica richiesta:
     - Leggendaria: `#00c8f3`
     - Rara: `#f1c000`
     - Non comune: `#d1d1d1`
     - Comune: `#964d02`
     - Speciale: `#000000`

---

## 2. Passi di Esecuzione

1. **Backend (`app/Http/Controllers/DeckController.php`)**:
   - Aggiornare `getDeckStatisticsData(Deck $deck)`:
     - Calcolare `powerHealthByCost` (medie pesate potenza e vita per costo).
     - Calcolare `costCurveByType` (curve separate per Unità, Miglioria, Evento).
     - Calcolare `aspectStats` (combinazioni aspetti con elenco colori e copie).
     - Calcolare `rarityStats` (conteggio per rarità con mappa colori specificati).
     - Mantenere le proprietà esistenti (`total`, `avgPower`, `avgHealth`, `costCurve`, `byType`, `traits`) per retrocompatibilità e test suite.

2. **Frontend Partial (`resources/views/decks/partials/statistics.blade.php`)**:
   - Rimuovere le card numeriche in cima ("Carte nel mazzo", "Potenza media", "Vita media").
   - Aggiungere il canvas per il grafico a linee/diffusione di potenza e vita medie per costo.
   - Aggiornare il grafico curva dei costi con i 3 toggle a badge interattivi (`Unità`, `Miglioria`, `Evento`) e gestione stato in Alpine.
   - Aggiungere il canvas per il grafico a torta degli aspetti con helper Canvas `createZebraPattern(colors)`.
   - Aggiungere il canvas per il grafico a torta delle rarità con i colori specificati.

3. **Verifica e Test**:
   - Eseguire i test con `vendor/bin/pest tests/Feature/Decks/DeckStatisticsTest.php`.
   - Eseguire la build degli asset `npm run build`.
   - Rinominare il piano in `implementationPlan-V-statisticheMazzoGrafici.md`.
   - Aggiornare `todo.md` e `README.md`.
