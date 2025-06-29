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

#### Integrazione API Ufficiale
- **API diretta**: Connessione all'API ufficiale di Star Wars Unlimited
  - Endpoint carte singole: `https://admin.starwarsunlimited.com/api/card/{cid}?locale=it`
  - Endpoint lista carte: `https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][$null]=true&pagination[page]={page}&pagination[pageSize]=10`
- **Scansione intelligente**: Rilevamento automatico nuove carte
- **Validazione dati**: Controllo integrità prima dell'inserimento
- **Gestione duplicati**: Prevenzione inserimenti multipli con controllo CID

#### Sistema di Notifiche Avanzato
- **Telegram integrato**: Messaggi di stato in tempo reale con aggiornamento progressivo
- **Email automatiche**:
  - Notifiche a tutti gli utenti per nuove carte aggiunte
  - Notifiche agli admin per carte con errori o già presenti
  - Template dedicati con snippet carte e link diretti
- **Logging dettagliato**: File di log timestampati (formato: `scansione ANNO MESE GIORNO ORE:MINUTI.log`)

#### Gestione Errori Avanzata
- **Pagina admin errori**: Interfaccia dedicata per visualizzare e gestire errori
- **Categorizzazione errori**: Classificazione per tipo e gravità
- **Risoluzione tracking**: Possibilità di segnare errori come risolti
- **Notifiche multiple**: Telegram, email e logging per ogni errore

#### Performance e Affidabilità
- **Job asincroni**: Operazioni lunghe gestite in background con JobController
- **Thread management**: Gestione processi paralleli per evitare timeout
- **Checkpoint system**: Ripristino automatico in caso di interruzioni
- **Rate limiting**: Rispetto limiti API ufficiale

#### Processo di Aggiornamento
1. **Scansione API**: Controllo nuove carte dall'API ufficiale
2. **Validazione**: Verifica integrità dati e controllo duplicati
3. **Processing asincrono**: Import batch con gestione parallela
4. **Notifiche**: Invio messaggi Telegram e email agli utenti
5. **Logging**: Registrazione dettagliata di tutte le operazioni
6. **Cleanup**: Aggiornamento cache e pulizia file temporanei

### 5. Funzionalità Avanzate Mazzi

#### Export/Import Mazzi
- **Esportazione multipla**: Formati TXT e JSON supportati
- **Formato ufficiale**: Compatibile con tutti i programmi Star Wars Unlimited
- **Importazione da file**: Upload di file locali TXT/JSON
- **Importazione da URL**: Import diretto da link esterni o altri siti SWUDB
- **Validazione automatica**: Controllo formato e carte esistenti durante l'import

#### Sistema di Versionamento
- **Cronologia completa**: Tracciamento di tutte le modifiche al mazzo
- **Versioni automatiche**: Salvataggio automatico ad ogni modifica significativa
- **Ripristino versioni**: Possibilità di tornare a qualsiasi versione precedente
- **Confronto versioni**: Visualizzazione differenze tra versioni (futuro)
- **Metadati versioni**: Timestamp e descrizione modifiche

#### Gestione Avanzata
- **Toggle visibilità**: Cambio pubblico/privato con un click
- **Rinomina rapida**: Modifica nome direttamente dalla lista mazzi
- **Eliminazione sicura**: Conferma prima della cancellazione definitiva
- **Link permanenti**: URL diretti per condivisione mazzi pubblici

#### Statistiche in Tempo Reale
- **Aggiornamento automatico**: Statistiche che si aggiornano durante la modifica
- **Grafici interattivi**: Visualizzazioni Chart.js per analisi avanzate
- **Curve di costo**: Distribuzione carte per costo con grafici a barre
- **Analisi aspetti**: Distribuzione con colori reali degli aspetti Star Wars
- **Tratti separati**: Analisi tratti divisi e completi in tabelle separate
- **Esclusione automatica**: Basi e leader esclusi dai calcoli statistici

### 6. Gestione Utenti e Admin

#### Pannello Amministrazione
- **Gestione utenti**: Visualizzazione, modifica e eliminazione utenti
- **Controlli admin**: Promozione/retrocessione privilegi amministratore
- **Pagina errori**: Interfaccia dedicata per gestione errori di sistema
- **Query database**: Accesso diretto al database per operazioni avanzate

#### Sistema di Notifiche
- **Email automatiche**: Template dedicati per diverse tipologie di notifiche
- **Telegram integrato**: Bot per notifiche in tempo reale agli admin
- **Logging avanzato**: File di log dettagliati per tutte le operazioni

### 7. API RESTful

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

# Opzioni di versionamento per all.sh:
bash/all.sh -v    # Incrementa APP_VERSION_PRIMARY e azzera APP_VERSION_TERTIARY
bash/all.sh -p    # Incrementa APP_VERSION_SECONDARY e azzera APP_VERSION_TERTIARY

# Solo commit e push su GitHub (include APP_VERSION nel messaggio di commit)
bash/cmt.sh

# Upload di tutti i file della cartella corrente sul server
bash/ftp.sh

# Upload/cancellazione sul server solo dei file inclusi nell'ultimo commit
bash/onlyFtpOfLastCmt.sh

# Pull da GitHub e mostra il nome dell'ultimo commit
bash/pull.sh
```

#### Sistema di Versionamento Automatico

Gli script di deployment includono un sistema di versionamento automatico:

- **APP_VERSION_PRIMARY**: Versione principale (incrementata con `-v`)
- **APP_VERSION_SECONDARY**: Versione secondaria (incrementata con `-p`)
- **APP_VERSION_TERTIARY**: Versione terziaria (azzerata con `-v` o `-p`)
- **Commit automatici**: Il messaggio di commit include sempre la versione corrente

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

## Documentazione del Codice

### Standard PHPDoc

Il progetto implementa una **documentazione PHPDoc completa** per tutti i metodi non nativi e non ovvi, seguendo questi principi:

#### Caratteristiche della Documentazione
- **Formato PHPDoc standard** con tag `@param`, `@return`, `@throws`
- **Commenti bilingue**: Inglese (tecnico) + Italiano (comprensibile)
- **Livello di dettaglio proporzionale** alla complessità del metodo
- **Spiegazioni contestuali** per ambienti non esclusivamente tecnici

#### Struttura Commenti
```php
/**
 * English technical description of the method
 * Descrizione italiana "alla buona" del metodo
 *
 * Detailed explanation of functionality, use cases, and important notes.
 * Complex methods include comprehensive documentation of algorithms and logic.
 *
 * @param Type $parameter Description of parameter
 * @param array $options Optional configuration array
 * @return ReturnType Description of return value
 * @throws ExceptionType When this exception occurs
 */
public function methodName($parameter, $options = [])
```

### Classi Documentate

#### Models (`app/Models/`)
- **Card.php**: Attributi personalizzati, cache management, eventi
- **Deck.php**: Gestione mazzi base (metodi nativi Laravel)
- **Composition.php**: Relazioni carte-mazzi (metodi nativi Laravel)
- **User.php**: Autenticazione utenti (metodi nativi Laravel)

#### Controllers (`app/Http/Controllers/`)
- **CardsController.php**: Algoritmi complessi di ordinamento e import
- **DecksController.php**: CRUD mazzi, export/import, statistiche
- **HomeController.php**: Dashboard utenti autenticati
- **JobController.php**: Operazioni asincrone e integrazioni esterne

#### Livewire Components (`app/Livewire/`)
- **SearchFilter.php**: Sistema filtri avanzato con cache
- **DeckManager.php**: Gestione mazzi con statistiche real-time
- **AddCardPopUp.php**: Popup selezione carte con validazione

#### Jobs, Events, Listeners (`app/Jobs/`, `app/Events/`, `app/Listeners/`)
- **ExecuteArtisanCommand.php**: Job per comandi Artisan asincroni
- **CardReceived.php**: Evento ricezione nuove carte
- **MessageCreated.php**: Evento creazione messaggi sistema
- **AddCard.php**: Listener per aggiunta carte
- **SendMessage.php**: Listener per invio messaggi Telegram

#### Mail Classes (`app/Mail/`)
- **NewCardsEmail.php**: Email notifica nuove carte
- **WelcomeEmail.php**: Email benvenuto nuovi utenti

#### Providers (`app/Providers/`)
- **AppServiceProvider.php**: Configurazione servizi applicazione

### Metodi Complessi Documentati

#### Algoritmi di Ordinamento
```php
// CardsController::compareElements()
// Algoritmo complesso per ordinamento carte con 7 criteri gerarchici
// Documentazione dettagliata di ogni fase di confronto

// CardsController::mergeSort()
// Implementazione merge sort per Laravel Collections
// Spiegazione ricorsione e integrazione con compareElements
```

#### Gestione Import Asincrono
```php
// CardsController::startImport()
// Processo completo import carte da API esterna
// Gestione batch, email notifiche, error handling

// CardsController::sendBatch() / dispatchBatch()
// Sistema batch processing per evitare timeout
// Documentazione del flusso asincrono
```

#### Statistiche Mazzi Real-time
```php
// DeckManager::calcolaStatistiche()
// Calcolo statistiche complete mazzi
// Analisi tratti, costi, tipi, aspetti con esclusioni specifiche
```

#### Sistema Fire-and-Forget
```php
// JobController::fireAndForgetGet() / fireAndForgetPost()
// Richieste HTTP asincrone con socket raw
// Documentazione implementazione low-level
```

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

### Sistema di Verifica Email

Il sistema implementa un meccanismo di verifica email personalizzato per garantire la validità degli account utente:

#### Funzionalità Principali

- **Token di verifica**: Ogni utente riceve un token univoco di 60 caratteri
- **Email automatica**: Invio automatico dell'email di verifica alla registrazione
- **Middleware di protezione**: Accesso limitato per utenti non verificati
- **Gestione admin**: Gli amministratori possono gestire lo stato di verifica

#### Flusso di Verifica

1. **Registrazione**: L'utente si registra e riceve un'email di verifica
2. **Verifica**: Click sul link nell'email per attivare l'account
3. **Accesso**: Solo utenti verificati possono accedere alle funzionalità protette
4. **Reinvio**: Possibilità di richiedere un nuovo link di verifica

#### Gestione Amministrativa

Gli amministratori possono:
- Visualizzare lo stato di verifica di tutti gli utenti
- Marcare manualmente utenti come verificati/non verificati
- Reinviare email di verifica per utenti specifici
- Utilizzare il comando `php artisan users:verify-existing` per verificare utenti esistenti

#### Implementazione Tecnica

```php
// Verifica se un utente ha l'email verificata
$user->isEmailVerified()

// Marca un utente come verificato
$user->markEmailAsVerified()

// Genera un nuovo token di verifica
$user->generateEmailVerificationToken()
```

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

## Implementazioni Tecniche Avanzate

### Componente SearchFilter

Il componente `SearchFilter` rappresenta una delle implementazioni più avanzate del progetto:

#### Architettura
- **Livewire 3**: Utilizzo delle funzionalità più moderne per reattività
- **Caching intelligente**: Cache delle opzioni filtro per 1 ora
- **Debounce ottimizzato**: 300ms per input testuali, immediato per select
- **Event-driven**: Comunicazione tramite eventi Livewire

#### Caratteristiche Tecniche
```php
// Gestione dinamica valori massimi
public function loadFilterOptions() {
    $this->maxCostoDb = Cache::remember('cards_max_costo', 3600,
        fn() => Card::max('costo') ?? 999);
}

// Filtri con gestione null values
if ($this->vitaMin !== null || ($this->vitaMax !== null && $this->vitaMax < $this->maxVitaDb)) {
    $query->where(function($q) use ($minVita, $maxVita) {
        $q->whereNull('vita')->orWhereBetween('vita', [$minVita, $maxVita]);
    });
}
```

#### Integrazione URL Parameters
- **GET parameter support**: URL con `?nome=` pre-popola automaticamente il filtro
- **Livewire mount**: Gestione parametri iniziali nel metodo mount
- **Reattività mantenuta**: Il filtro rimane reattivo anche con valori pre-popolati

### Sistema di Import/Export Mazzi

#### Formati Supportati
- **TXT**: Formato standard Star Wars Unlimited compatibile con tutti i programmi
- **JSON**: Formato strutturato per API e backup
- **URL Import**: Parsing automatico da link esterni e altri siti SWUDB

#### Validazione Import
- **Controllo formato**: Validazione struttura file prima del processing
- **Verifica carte**: Controllo esistenza carte nel database
- **Gestione errori**: Report dettagliato di carte non trovate o errori formato

### Sistema API Ufficiale

#### Integrazione Diretta
- **Endpoint ufficiali**: Connessione diretta all'API di Star Wars Unlimited
- **Paginazione automatica**: Gestione automatica delle pagine API
- **Rate limiting**: Rispetto dei limiti di richiesta dell'API ufficiale
- **Retry logic**: Gestione automatica di errori temporanei

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
- **PHPDoc completo** per tutti i metodi non nativi
- **Documentazione bilingue** (EN/IT) per accessibilità
- **Test coverage** per nuove feature

### Pattern di Documentazione

#### Metodi Semplici
```php
/**
 * Simple method description
 * Descrizione semplice del metodo
 *
 * @param Type $param Parameter description
 * @return Type Return description
 */
```

#### Metodi Complessi
```php
/**
 * Complex method with detailed algorithm explanation
 * Metodo complesso con spiegazione dettagliata dell'algoritmo
 *
 * This method handles multiple complex operations:
 * 1. Data validation and preprocessing
 * 2. Algorithm execution with specific rules
 * 3. Result formatting and error handling
 *
 * @param array $data Input data with specific format requirements
 * @param bool $verbose Enable detailed output for debugging
 * @return Collection Processed results with metadata
 * @throws InvalidArgumentException When data format is invalid
 */
```

#### Componenti Livewire
```php
/**
 * Livewire component for specific functionality
 * Componente Livewire per funzionalità specifica
 *
 * This component provides comprehensive functionality including:
 * - Real-time data processing
 * - Event-driven communication
 * - State management and validation
 */
class ComponentName extends Component
```

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