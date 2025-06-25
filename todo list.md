# todo list
## Effettiva
- ~~sistemare l'intestazione dell'immagine~~
- ~~sistemare icona unica~~
- ~~sistemare webscraping segnalini~~
- ~~sistemare l'accesso a mazzi~~
- ~~sistemare i popup~~
- ~~sistemare webscraping descrizione leader~~
- ~~sistemare visualizzazione descrizione leader~~
- ~~sistemare la posizion del footer~~
- ~~sistemare la posizione dei messaggi di errore~~
- ~~scrivere i termini di servizio~~
- ~~scrivere le traduzioni~~
- ~~sistemare bordi aggiunta/rimozione carte~~
- ~~sistemare la barra di ricerca nella navbar~~
- ~~sistemare la generazione dell'uscita della carta~~
- ~~aggiustare il tasto di aggiunta carte~~
- ~~impostare i thread per la gestione dell'inserimento carte~~
- ~~migliorare i thread per la gestione dell'inserimento carte~~
- ~~sistemare il footer (link Mandich Riccardo)~~
- ~~refreshare tutte le carte~~
- ~~far funzionare le mail~~
- ~~sistemare il testo delle mail di aggiunta nuove carte~~
- ~~sistemare l'esistenza dei link next/back nelle carte~~
- ~~creazione di mail alias `info@unlimiteddb.net`~~
- ~~filtri di ricerca~~
    - ~~fare in modo che se io apro i filtri avanzati e modifico qualcosa nel caricamento non venga richiusa in automatico la sezione filtri avanzati~~
- ~~aggiustare il popup di aggiunta carte~~
- ~~trasformazione delle analisi delle carte in grafici~~
- ~~gestione della collezione~~
- ~~analisi delle statistiche delle carte nei mazzi~~
    - ~~numero carte x costo e per tipo~~
    - ~~tratti~~
        - ~~divisi~~
        - ~~non divisi~~
    - ~~hp/potenza media~~
    - ~~sistemare la larghezza dei card su mobile~~
- ~~Implementazione di cache per migliorare le performance~~
- ~~ottimizzare il popup di aggiunta carte~~
- ~~Funzionalità di esportazione mazzi in formato TXT e JSON~~
- ~~Funzionalità di importazione mazzi da URL o file~~
    - ~~import da url rotto~~
- ~~aggiornare l'index dei mazzi~~
- ~~limitare le notifiche telegram durante l'invio dei batch~~
- ~~miglioramento pagina utenti per la gestione di admin~~
- ~~Migrazione del sistema di webscraping da Java a Laravel (integrazione nella pagina update) (il progetto java si trova nella cartella WebScrapingStarWars, che è un clone del repository https://github.com/RickyMandich/WebScrapingStarWars.git)~~
- ~~Sistema di versionamento per i mazzi~~
- gestire l'attributo get "name" nella pagina carte per impostare già il filtro, attenzione che si refresha con un oninput o simile, quindi non basta un value="{{ $_GET['nome'] }} nel capo input del filtro del nome
- ~~riscrivi completamente l'aggiornamento del DB con nuove carte prendendole direttamente dall'api del sito ufficiale (esempio di api singola carta: "https://admin.starwarsunlimited.com/api/card/" + cid + "?locale=it", puoi trovare un esempio di json nella cartella example, esempio di api elenco carte: "https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][$null]=true&pagination[page]=" + page + "&pagination[pageSize]=10", puoi trovare un esempio di json nella cartella example), cerca di semplificare e ottimizzare il più possibile il processo mantenendo un sistema di notifiche tramite telegram per comunicare lo stato delle operazioni, gestisci tutto con un'unico messaggio che viene modificato nel tempo e gestisci anche il fatto che venga effettuata una verifica che la carta non sia già presente, manda una mail a tutti gli utenti con le carte aggiunte e agli admin con le carte che hanno lanciato errori/eccezioni o che erano già presenti, inserisci anche il motivo specifico per cui non sono state inserite~~
- ~~creare un pagina admin "errori" che mostra tutti gli errori che si sono verificati e che permette di segnarli come completati, devono venire salvati quando si creano (oltre alla già presente gestione con visualizzazione dettagliata per gli admin e notifica tramite telegram e tramite mail), visto che stiamo creando una nuova pagina bisogna anche aggiungere il relativo pulsante (direi che basta nel pannello admin), decidi tu in base a cosa ti sembra più opportuno se creare una nuova entità nel DB o se gestirlo tramite file json~~
- aggiornare la documentazione
- generazione di una guida avanzata e comprensione completa del progetto


## Funzionalità Future
- Funzionalità di condivisione social
- Sistema di tag personalizzati per i mazzi (etichette come "Aggro", "Control", "Budget", "Meta")
- Modalità offline/PWA per consultazione carte (Progressive Web App)
- Sistema di wishlist per carte desiderate
- Funzionalità di deck-building guidato per principianti
- aggiungere alle statistiche del mazzo qual è la percentuale che una carte trovi una carta che soddisfi i requisiti per giocare o pescare carte

# Documentazione

## Panoramica del Progetto

**UnlimitedDB.net** è un database non ufficiale per il gioco di carte collezionabili **Star Wars: Unlimited** sviluppato da Fantasy Flight Games. Il sito è hostato tramite Altervista.org e offre una piattaforma completa per la ricerca di carte, la gestione di mazzi e collezioni, con funzionalità avanzate di analisi e statistiche.

### Caratteristiche Principali

- **Database completo** di tutte le carte di Star Wars: Unlimited
- **Sistema di ricerca avanzato** con filtri multipli
- **Gestione mazzi** con statistiche dettagliate e grafici
- **Collezione personale** per tracciare le carte possedute
- **Aggiornamento automatico** del database tramite webscraping
- **API RESTful** per sviluppatori terzi
- **Sistema multilingue** (Italiano/Inglese)
- **Interfaccia responsive** ottimizzata per mobile e desktop

## Architettura e Tecnologie

### Stack Tecnologico

- **Backend**: Laravel 12 (PHP 8.1+)
- **Frontend**: Blade Templates + Livewire 3
- **Database**: MySQL
- **Hosting**: Altervista.org
- **Styling**: Bootstrap 5.3
- **JavaScript**: Vanilla JS + Chart.js per grafici
- **Build Tools**: Vite + NPM
- **Deployment**: FTP automatizzato

### Struttura del Database

#### Tabelle Principali

- **`cards`**: Informazioni complete delle carte
- **`decks`**: Mazzi degli utenti
- **`compositions`**: Relazione carte-mazzi con quantità
- **`users`**: Utenti registrati
- **`failed_jobs`**: Gestione errori job asincroni

#### Modelli Eloquent

- `Card`: Gestione carte con attributi completi
- `Deck`: Mazzi pubblici e privati
- `Composition`: Composizione mazzi
- `User`: Utenti con ruoli admin

## Funzionalità Principali

### 1. Ricerca e Filtri Carte

Il sistema di ricerca utilizza un componente Livewire avanzato (`SearchFilter`) che offre:

- **Filtri multipli**: Nome, titolo, espansione, tipo, aspetti, rarità
- **Range numerici**: Costo, potenza, vita con valori min/max dinamici
- **Filtri testuali**: Tratti, arena, artista
- **Filtri booleani**: Carta unica
- **Ricerca in tempo reale** con debounce
- **Risultati istantanei** senza ricaricamento pagina

### 2. Gestione Mazzi

#### Creazione e Modifica
- **Deck builder interattivo** con popup di aggiunta carte
- **Validazione automatica** delle regole del gioco
- **Salvataggio in tempo reale** delle modifiche
- **Mazzi pubblici e privati**

#### Statistiche Avanzate
- **Distribuzione per costo** con grafici a barre
- **Analisi tratti** (divisi e completi)
- **Curve di costo** e statistiche vita/potenza
- **Distribuzione per tipo e aspetto**
- **Grafici interattivi** con Chart.js

### 3. Collezione Personale

- **Mazzo speciale "Collezione"** auto-creato per ogni utente
- **Tracciamento quantità** carte possedute
- **Stessi filtri** utilizzati nel resto del sito
- **Statistiche collezione** con valori di mercato (futuro)

### 4. Sistema di Aggiornamento

#### Webscraping Automatizzato
- **Import batch** da fonte esterna (attualmente https://github.com/RickyMandich/WebScrapingStarWars.git)
- **Progetto di integrazione** del webscraping direttamente nel sito
- **Processing asincrono** con job Laravel
- **Notifiche email** per nuove carte
- **Gestione errori** e retry automatici

#### Processo di Aggiornamento
1. Controllo nuove carte da API esterna
2. Confronto con database locale
3. Import batch con processing parallelo
4. Invio notifiche agli utenti registrati
5. Aggiornamento cache e indici

### 5. API RESTful

#### Endpoints Disponibili

```
GET /api/carta/{espansione}/{numero}     # Dettagli carta singola
GET /api/carte/{espansione}              # Carte per espansione
GET /api/mazzi/{user}/{nome}/{public}    # Dettagli mazzo (ricerca con LIKE, nomi parziali supportati)
```

#### Formato Risposte
- **JSON strutturato** con metadati completi
- **CORS abilitato** per sviluppo frontend
- **Rate limiting** per prevenire abusi
- **Documentazione OpenAPI** (da implementare)

## Installazione e Configurazione

### Requisiti di Sistema

- PHP 8.1 o superiore
- Composer 2.x
- Node.js 18+ e NPM
- MySQL 8.0+
- Estensioni PHP: mbstring, openssl, PDO, Tokenizer, XML, cURL, zip

### Setup Locale

```bash
# Clone del repository
git clone https://github.com/RickyMandich/SWUDB.git
cd SWUDB

# Installazione dipendenze PHP
composer install

# Installazione dipendenze JavaScript
npm install

# Configurazione ambiente
cp .env.example .env
php artisan key:generate

# Setup database
php artisan migrate
php artisan db:seed

# Build assets
npm run build

# Avvio server di sviluppo
php artisan serve
```

### Configurazione Ambiente

#### File `.env` Essenziale

```env
APP_NAME=UnlimitedDB
APP_DOMAIN=unlimiteddb.net
APP_ENV=production
APP_DEBUG=false
APP_URL=https://unlimiteddb.net

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swudb
DB_USERNAME=username
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password

JOB_TOKEN=your-secure-token
RESEND_API_KEY=your-resend-key
```

### Configurazione Produzione

#### Ottimizzazioni Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

#### Setup Cron Job
```cron
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Deployment

### Processo Automatizzato

Il progetto include diversi script bash per il deployment e la gestione del codice:

```bash
# Commit, push su GitHub e upload/cancellazione di tutti i file modificati/eliminati sul server
bash/all.sh

# Solo commit e push su GitHub
bash/cmt.sh

# Upload di tutti i file della cartella corrente sul server
bash/ftp.sh

# Upload/cancellazione sul server solo dei file inclusi nell'ultimo commit
bash/onlyFtpOfLastCmt.sh

# Pull da GitHub e mostra il nome dell'ultimo commit
bash/pull.sh
```

### Configurazione FTP

Gli script utilizzano credenziali FTP configurate per Altervista:
- **Host**: ftp.swudb.altervista.org
- **Porta**: 21
- **Modalità**: PASV con creazione directory automatica

### Checklist Pre-Deploy

- [ ] Test locali completati
- [ ] Database migrato
- [ ] Assets compilati (`npm run build`)
- [ ] Cache Laravel pulita
- [ ] Backup database effettuato
- [ ] Variabili ambiente configurate
- [ ] SSL certificato valido

## Componenti Livewire

### SearchFilter
**Percorso**: `app/Livewire/SearchFilter.php`

Componente principale per la ricerca e filtro carte con:
- Filtri multipli in tempo reale
- Validazione input
- Gestione stato filtri
- Integrazione con popup e pagine

### DeckManager
**Percorso**: `app/Livewire/DeckManager.php`

Gestione completa mazzi con:
- Aggiunta/rimozione carte
- Calcolo statistiche in tempo reale
- Validazione regole gioco
- Integrazione grafici JavaScript

### AddCardPopUp
**Percorso**: `app/Livewire/AddCardPopUp.php`

Popup per aggiunta carte ai mazzi con:
- Ricerca integrata
- Selezione quantità
- Validazione limiti carte
- UX ottimizzata mobile

## Sicurezza

### Autenticazione e Autorizzazione

- **Laravel Sanctum** per API authentication
- **Middleware personalizzato** per controllo admin
- **CSRF protection** su tutte le form
- **Rate limiting** su API endpoints

### Validazione Input

- **Form Request** per validazione complessa
- **Sanitizzazione** input utente
- **Escape output** per prevenire XSS
- **SQL injection protection** via Eloquent ORM

### Best Practices Implementate

- Password hashing con bcrypt
- Session security configurata
- Headers di sicurezza HTTP
- Validazione file upload
- Logging errori e accessi

## Performance

### Ottimizzazioni Database

- **Indici ottimizzati** su colonne di ricerca frequente
- **Query eager loading** per ridurre N+1 queries
- **Database connection pooling**
- **Query caching** per dati statici

### Frontend Performance

- **Asset minification** con Vite
- **Lazy loading** immagini carte
- **Debounce** su input di ricerca
- **Component caching** Livewire

### Monitoring

- **Laravel Telescope** per debug (dev)
- **Error logging** con stack traces
- **Performance metrics** custom
- **Database query monitoring**

## Contribuire al Progetto

### Workflow di Sviluppo

1. **Fork** del repository
2. **Branch feature** per nuove funzionalità
3. **Commit** con messaggi descrittivi
4. **Pull Request** con descrizione dettagliata
5. **Code review** e testing
6. **Merge** dopo approvazione

### Standard di Codice

- **PSR-12** per PHP
- **ESLint** per JavaScript
- **Blade formatting** consistente
- **Commenti** per logica complessa
- **Test coverage** per nuove feature

### Testing

```bash
# Test PHP
php artisan test

# Test JavaScript
npm run test

# Test E2E
php artisan dusk
```

## Supporto e Community

### Contatti

- **Email**: info@unlimiteddb.net
- **Sviluppatore**: Riccardo Mandich (ricky.mandich@gmail.com)
- **Repository**: https://github.com/RickyMandich/SWUDB

### Segnalazione Bug

Utilizzare il sistema di Issues GitHub con:
- Descrizione dettagliata del problema
- Steps per riprodurre
- Screenshot se applicabile
- Informazioni browser/dispositivo

### Richieste Feature

Le nuove funzionalità possono essere richieste tramite:
- GitHub Issues con label "enhancement"
- Email con proposta dettagliata
- Pull Request per implementazioni dirette

---

**Disclaimer**: UnlimitedDB.net è un sito fan-made non ufficiale. Star Wars: Unlimited è un marchio di Fantasy Flight Games e Lucasfilm Ltd. Tutti i diritti sui contenuti originali appartengono ai rispettivi proprietari.