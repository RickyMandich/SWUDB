# Guida Avanzata

## Funzionalità Avanzate per Utenti Esperti

Questa guida è dedicata agli utenti che vogliono sfruttare al massimo le funzionalità avanzate di **UnlimitedDB.net**.

### 1. Ricerca Avanzata delle Carte

#### Filtri Combinati
- **Filtri multipli**: Combina più filtri per ricerche precise
- **Operatori logici**: Utilizza AND/OR impliciti tra i filtri
- **Range numerici**: Specifica intervalli per costo, vita, potenza
- **Ricerca testuale**: Cerca nel nome, testo e tratti delle carte

#### Sintassi di Ricerca Speciale
- **Ricerca per tratti**: Usa il filtro tratti per cercare combinazioni specifiche
- **Ricerca per tipo**: Filtra per Unità, Eventi, Aggiornamenti, Basi, Leader
- **Ricerca per aspetto**: Combina aspetti primari e secondari
- **Ricerca per espansione**: Filtra per set specifici

### 2. Gestione Avanzata dei Mazzi

#### Statistiche Dettagliate
- **Curve di costo**: Analizza la distribuzione del costo delle carte
- **Distribuzione tratti**: Visualizza i tratti più comuni nel mazzo
- **Statistiche vita/potenza**: Media e distribuzione per costo
- **Grafici interattivi**: Visualizzazioni dinamiche con Chart.js

#### Esportazione e Condivisione
- **Formato ufficiale**: Esporta nel formato standard di Star Wars Unlimited
- **Mazzi pubblici/privati**: Controlla la visibilità dei tuoi mazzi
- **Link diretti**: Condividi mazzi tramite URL specifici
- **API integration**: Accedi ai dati tramite endpoint REST

### 3. Collezione Personale

#### Gestione Avanzata
- **Tracciamento quantità**: Tieni conto di quante copie possiedi
- **Filtri collezione**: Usa gli stessi filtri delle carte per la collezione
- **Statistiche collezione**: Analizza la tua collezione personale
- **Mazzo speciale**: La collezione è gestita come un mazzo privato speciale

### 4. API e Integrazione

#### Endpoints Disponibili
```
GET /api/carta/{espansione}/{numero}     # Dettagli carta singola
GET /api/carte/{espansione}              # Carte per espansione  
GET /api/mazzi/{user}/{nome}/{public}    # Dettagli mazzo
```

#### Utilizzo API
- **Formato JSON**: Tutte le risposte sono in formato JSON strutturato
- **CORS abilitato**: Supporto per applicazioni frontend
- **Rate limiting**: Limiti per prevenire abusi
- **Ricerca fuzzy**: I nomi dei mazzi supportano ricerca parziale (LIKE)

### 5. Funzionalità per Amministratori

#### Pannello di Controllo
- **Dashboard completa**: Panoramica del sistema e statistiche
- **Gestione utenti**: Visualizzazione, modifica e controllo privilegi
- **Query database**: Accesso diretto al database per operazioni avanzate
- **Gestione errori**: Interfaccia dedicata per monitoraggio errori

#### Sistema di Aggiornamento
- **Aggiornamento automatico**: Scansione periodica dell'API ufficiale
- **Rilevamento nuove carte**: Identificazione automatica di nuovi contenuti
- **Validazione dati**: Controllo integrità prima dell'inserimento
- **Logging dettagliato**: File di log per tutte le operazioni

### 6. Scorciatoie da Tastiera

#### Navigazione Rapida
- **Ctrl + Enter**: Esegui query nella pagina query database
- **ESC**: Chiudi popup e modali aperti
- **Tab**: Navigazione tra filtri e campi

#### Ricerca
- **Focus automatico**: La barra di ricerca è sempre accessibile
- **Ricerca istantanea**: Risultati in tempo reale durante la digitazione

### 7. Ottimizzazioni e Performance

#### Caricamento Intelligente
- **Lazy loading**: Caricamento progressivo delle immagini
- **Cache intelligente**: Ottimizzazione delle query frequenti
- **Compressione**: Riduzione dei tempi di caricamento

#### Responsive Design
- **Mobile first**: Ottimizzato per dispositivi mobili
- **Adattamento automatico**: Layout che si adatta a ogni schermo
- **Touch friendly**: Interfaccia ottimizzata per touch screen

### 8. Suggerimenti e Trucchi

#### Ricerca Efficace
- **Usa filtri specifici**: Più filtri usi, più precisi sono i risultati
- **Combina testo e filtri**: Unisci ricerca testuale con filtri numerici
- **Salva ricerche frequenti**: Crea mazzi per salvare combinazioni di carte

#### Gestione Mazzi
- **Nomi descrittivi**: Usa nomi chiari per i tuoi mazzi
- **Descrizioni dettagliate**: Aggiungi note strategiche nei mazzi
- **Backup regolari**: Esporta i mazzi importanti

#### Collezione
- **Aggiornamento costante**: Mantieni aggiornata la tua collezione
- **Usa le statistiche**: Analizza cosa ti manca per completare set
- **Pianifica acquisti**: Usa i filtri per identificare carte prioritarie

### 9. Risoluzione Problemi

#### Problemi Comuni
- **Carte non trovate**: Verifica l'ortografia e usa filtri alternativi
- **Mazzi non salvati**: Controlla la connessione e riprova
- **Immagini non caricate**: Aggiorna la pagina o svuota la cache

#### Supporto
- **Segnalazione bug**: Usa i canali ufficiali per segnalare problemi
- **Richieste funzionalità**: Proponi miglioramenti tramite i canali dedicati
- **Community**: Partecipa alle discussioni della community

### 10. Aggiornamenti e Novità

#### Changelog
- **Versioning**: Il sistema usa versioning semantico
- **Note di rilascio**: Ogni aggiornamento include note dettagliate
- **Backward compatibility**: Mantenimento compatibilità con versioni precedenti

#### Roadmap
- **Funzionalità future**: Consulta la roadmap per le prossime implementazioni
- **Feedback utenti**: Le richieste degli utenti influenzano lo sviluppo
- **Aggiornamenti regolari**: Rilasci frequenti con miglioramenti e correzioni
