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
// Algoritmo complesso per ordinamento carte con 9 criteri gerarchici
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