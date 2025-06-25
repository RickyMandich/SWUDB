# Guida Avanzata SWUDB - Comprensione Completa del Progetto

## Indice

1. [Panoramica Architetturale](#panoramica-architetturale)
2. [Flussi di Dati Principali](#flussi-di-dati-principali)
3. [Componenti Chiave](#componenti-chiave)
4. [Algoritmi e Logiche Complesse](#algoritmi-e-logiche-complesse)
5. [Integrazione Sistemi Esterni](#integrazione-sistemi-esterni)
6. [Gestione Stati e Performance](#gestione-stati-e-performance)
7. [Sicurezza e Validazione](#sicurezza-e-validazione)
8. [Deployment e DevOps](#deployment-e-devops)
9. [Troubleshooting Avanzato](#troubleshooting-avanzato)
10. [Roadmap e Sviluppi Futuri](#roadmap-e-sviluppi-futuri)

---

## Panoramica Architetturale

### Stack Tecnologico Completo

**Backend Core:**
- **Laravel 12**: Framework PHP con architettura MVC
- **PHP 8.1+**: Linguaggio con tipizzazione forte e performance ottimizzate
- **MySQL 8.0+**: Database relazionale con supporto JSON e full-text search
- **Composer**: Dependency manager per PHP

**Frontend e UI:**
- **Blade Templates**: Template engine nativo Laravel
- **Livewire 3**: Framework full-stack per componenti reattivi
- **Bootstrap 5.3**: Framework CSS responsive
- **Chart.js**: Libreria per grafici interattivi
- **FontAwesome**: Icone vettoriali

**Build e Asset Management:**
- **Vite**: Build tool moderno per asset bundling
- **NPM**: Package manager per dipendenze JavaScript
- **Sass**: Preprocessore CSS per styling avanzato

**Hosting e Deployment:**
- **Altervista.org**: Hosting condiviso con supporto PHP/MySQL
- **FTP Deployment**: Upload automatizzato tramite script bash
- **GitHub**: Version control e repository centrale

### Architettura a Livelli

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                       │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │   Blade Views   │  │ Livewire Comps  │  │  Bootstrap  │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                     BUSINESS LAYER                          │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │   Controllers   │  │      Jobs       │  │   Events    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                      DATA LAYER                             │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │   Eloquent ORM  │  │     Models      │  │   MySQL     │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│                   EXTERNAL SERVICES                         │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  SW:U API       │  │    Telegram     │  │    Email    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

---

## Flussi di Dati Principali

### 1. Flusso di Ricerca Carte

```mermaid
graph TD
    A[User Input] --> B[SearchFilter Component]
    B --> C[Livewire Processing]
    C --> D[Query Builder]
    D --> E[Database Query]
    E --> F[Results Collection]
    F --> G[mergeSort Algorithm]
    G --> H[Cached Results]
    H --> I[Frontend Display]

    J[URL Parameters] --> B
    K[Filter Cache] --> B
```

**Dettaglio Tecnico:**
1. **Input Utente**: Digitazione con debounce 300ms
2. **Livewire Processing**: Validazione e sanitizzazione input
3. **Query Building**: Costruzione query dinamica con filtri multipli
4. **Database Execution**: Esecuzione query ottimizzata con indici
5. **Result Processing**: Applicazione algoritmo di ordinamento personalizzato
6. **Caching**: Salvataggio risultati per performance
7. **Frontend Update**: Aggiornamento DOM senza refresh pagina

### 2. Flusso di Aggiornamento Database

```mermaid
graph TD
    A[Admin Trigger] --> B[API Scan Job]
    B --> C[SW:U API Call]
    C --> D[Data Validation]
    D --> E[Duplicate Check]
    E --> F[Database Insert]
    F --> G[Email Notifications]
    G --> H[Telegram Updates]
    H --> I[Log Generation]

    J[Error Handling] --> K[Admin Notification]
    J --> L[Error Page Update]
```

**Componenti Coinvolti:**
- **CardsController::scanAPI()**: Orchestrazione processo
- **JobController**: Gestione job asincroni
- **ThreadManager**: Gestione processi paralleli
- **Email Templates**: Notifiche strutturate
- **Telegram Bot**: Messaggi real-time

### 3. Flusso di Gestione Mazzi

```mermaid
graph TD
    A[User Action] --> B[Deck Controller]
    B --> C[Validation Layer]
    C --> D[Database Transaction]
    D --> E[Version Creation]
    E --> F[Statistics Update]
    F --> G[Cache Invalidation]
    G --> H[Frontend Refresh]

    I[Export Request] --> J[Format Selection]
    J --> K[Data Serialization]
    K --> L[File Generation]
```

---

## Componenti Chiave

### SearchFilter Component (Livewire)

**Responsabilità:**
- Gestione filtri multipli con validazione
- Caching intelligente delle opzioni
- Comunicazione event-driven con parent components
- Gestione URL parameters per deep linking

**Architettura Interna:**
```php
class SearchFilter extends Component
{
    // Properties per filtri
    public $nome = '';
    public $espansione = '';
    // ... altri filtri

    // Cache e performance
    public $filteredCards = [];
    public $totalResults = 0;

    // Modalità operative
    public $mode = 'page'; // 'page', 'popup', 'collezione'

    protected $listeners = [
        'resetFilters' => 'resetAllFilters',
        'applyFiltersForPopup' => 'getFilteredCardsForPopup'
    ];
}
```

**Metodi Chiave:**
- `mount()`: Inizializzazione con parametri URL
- `applyFilters()`: Costruzione query dinamica
- `loadFilterOptions()`: Caricamento opzioni con cache
- `resetAllFilters()`: Reset stato componente

### CardsController

**Algoritmo mergeSort:**
```php
public static function mergeSort($cards) {
    // Implementazione merge sort ottimizzata per Laravel Collections
    // Gestisce sia Collection che array nativi
    // Utilizza compareElements per logica di confronto
}

private static function compareElements($a, $b) {
    // 9 criteri di confronto gerarchici:
    // 1. Espansione (per data uscita)
    // 2. Tipo (Leader > Base > Unità > Upgrade > Evento)
    // 3. Aspetto primario
    // 4. Costo
    // 5. Nome
    // 6. Aspetto secondario
    // 7. Rarità
    // 8. Numero carta
    // 9. CID (fallback)
}
```

### JobController e Sistema Asincrono

**Gestione Job Lunghi:**
```php
class JobController extends Controller
{
    public function addCard(Request $request) {
        // Validazione token sicurezza
        // Dispatch job asincrono
        // Gestione timeout con thread management
        // Logging operazioni
    }
}
```

**Thread Management:**
```php
class ThreadManager
{
    public static function isThreadComplete($threadId) {
        // Controllo stato thread tramite file system
        // Gestione timeout automatici
        // Cleanup risorse
    }
}
```

---

## Algoritmi e Logiche Complesse

### Algoritmo di Ordinamento Carte

L'algoritmo di ordinamento è il cuore del sistema di visualizzazione carte:

**Criteri di Ordinamento (in ordine di priorità):**

1. **Espansione**: Ordinamento per data di uscita
   ```php
   $espansioniOrdinate = [
       'SOR' => 1, 'SHD' => 2, 'TWI' => 3, // ... ordine cronologico
   ];
   ```

2. **Tipo Carta**: Gerarchia strategica
   ```php
   $tipiOrdine = [
       'Leader' => 1, 'Base' => 2, 'Unità' => 3,
       'Upgrade' => 4, 'Evento' => 5
   ];
   ```

3. **Aspetto Primario**: Ordinamento per colore
4. **Costo**: Crescente (0-9+)
5. **Nome**: Alfabetico
6. **Aspetto Secondario**: Per carte multi-aspetto
7. **Rarità**: Common < Uncommon < Rare < Legendary < Special
8. **Numero**: Numerico crescente
9. **CID**: Fallback per garantire ordinamento deterministico

**Implementazione Merge Sort:**
```php
private static function merge($left, $right) {
    $result = [];
    $i = $j = 0;

    while ($i < count($left) && $j < count($right)) {
        if (self::compareElements($left[$i], $right[$j]) <= 0) {
            $result[] = $left[$i++];
        } else {
            $result[] = $right[$j++];
        }
    }

    // Merge rimanenti elementi
    return array_merge($result, array_slice($left, $i), array_slice($right, $j));
}
```

### Sistema di Validazione Mazzi

**Regole di Validazione:**
```php
class DeckValidator
{
    public function validate($cards) {
        $errors = [];

        // 1. Controllo numero carte (50 esatte)
        if (count($cards) !== 50) {
            $errors[] = "Il mazzo deve contenere esattamente 50 carte";
        }

        // 2. Controllo Leader unico
        $leaders = array_filter($cards, fn($c) => $c->tipo === 'Leader');
        if (count($leaders) !== 1) {
            $errors[] = "Il mazzo deve contenere esattamente 1 Leader";
        }

        // 3. Controllo Base unica
        $bases = array_filter($cards, fn($c) => $c->tipo === 'Base');
        if (count($bases) !== 1) {
            $errors[] = "Il mazzo deve contenere esattamente 1 Base";
        }

        // 4. Controllo aspetti compatibili
        $this->validateAspects($cards, $errors);

        // 5. Controllo copie multiple (max 3 per carta non-unica)
        $this->validateCopies($cards, $errors);

        return $errors;
    }
}
```

### Sistema di Statistiche Real-time

**Calcolo Statistiche Mazzi:**
```php
class DeckStatistics
{
    public function calculateStats($cards) {
        $stats = [
            'distribuzione_costo' => $this->calculateCostDistribution($cards),
            'distribuzione_aspetti' => $this->calculateAspectDistribution($cards),
            'analisi_tratti' => $this->analyzeTraits($cards),
            'medie_hp_potenza' => $this->calculateAverages($cards)
        ];

        return $stats;
    }

    private function calculateCostDistribution($cards) {
        // Esclude Leader e Base dai calcoli
        $playableCards = array_filter($cards,
            fn($c) => !in_array($c->tipo, ['Leader', 'Base']));

        $distribution = [];
        foreach ($playableCards as $card) {
            $cost = $card->costo ?? 0;
            $distribution[$cost] = ($distribution[$cost] ?? 0) + 1;
        }

        return $distribution;
    }
}
```

---

## Integrazione Sistemi Esterni

### API Star Wars Unlimited

**Endpoint Principali:**
```php
class SWUApiClient
{
    private const BASE_URL = 'https://admin.starwarsunlimited.com/api';

    public function getCard($cid) {
        $url = self::BASE_URL . "/card/{$cid}?locale=it";
        return $this->makeRequest($url);
    }

    public function getCardList($page = 1, $pageSize = 10) {
        $url = self::BASE_URL . "/card-list";
        $params = [
            'locale' => 'it',
            'filters[variantOf][id][$null]' => 'true',
            'pagination[page]' => $page,
            'pagination[pageSize]' => $pageSize
        ];

        return $this->makeRequest($url . '?' . http_build_query($params));
    }
}
```

**Gestione Rate Limiting:**
```php
class ApiRateLimiter
{
    private static $lastRequest = 0;
    private const MIN_INTERVAL = 100; // ms tra richieste

    public static function throttle() {
        $now = microtime(true) * 1000;
        $elapsed = $now - self::$lastRequest;

        if ($elapsed < self::MIN_INTERVAL) {
            usleep((self::MIN_INTERVAL - $elapsed) * 1000);
        }

        self::$lastRequest = microtime(true) * 1000;
    }
}
```

### Sistema di Notifiche

**Telegram Bot Integration:**
```php
class TelegramNotifier
{
    public function sendProgressUpdate($chatId, $messageId, $progress) {
        $text = "🔄 Scansione in corso...\n";
        $text .= "📊 Progresso: {$progress['current']}/{$progress['total']}\n";
        $text .= "✅ Aggiunte: {$progress['added']}\n";
        $text .= "⚠️ Errori: {$progress['errors']}\n";
        $text .= "⏱️ Tempo: {$progress['elapsed']}s";

        return $this->editMessage($chatId, $messageId, $text);
    }
}
```

**Email Templates:**
```php
class NewCardsEmail extends Mailable
{
    public function build() {
        return $this->subject('Nuove carte aggiunte a SWUDB')
                   ->view('emails.new-cards')
                   ->with([
                       'cards' => $this->cards,
                       'totalAdded' => count($this->cards),
                       'scanDate' => now()->format('d/m/Y H:i')
                   ]);
    }
}
```

---

## Gestione Stati e Performance

### Caching Strategy

**Livelli di Cache:**
1. **Application Cache**: Laravel Cache per dati frequenti
2. **Query Cache**: Cache risultati database
3. **Component Cache**: Livewire component caching
4. **Browser Cache**: Asset caching con versioning

```php
class CacheManager
{
    public static function getFilterOptions() {
        return Cache::remember('filter_options', 3600, function() {
            return [
                'espansioni' => Espansione::orderBy('data_uscita')->get(),
                'tipi' => Card::distinct()->pluck('tipo')->filter(),
                'aspetti' => Card::distinct()->pluck('aspetto_primario')->filter(),
                'rarita' => Card::distinct()->pluck('rarita')->filter()
            ];
        });
    }

    public static function invalidateCardCache() {
        Cache::forget('filter_options');
        Cache::forget('cards_max_costo');
        Cache::forget('cards_max_vita');
        Cache::forget('cards_max_potenza');
    }
}
```

### Database Optimization

**Indici Strategici:**
```sql
-- Indici per ricerca rapida
CREATE INDEX idx_cards_search ON cards(nome, espansione, tipo);
CREATE INDEX idx_cards_filters ON cards(aspetto_primario, aspetto_secondario, rarita);
CREATE INDEX idx_cards_stats ON cards(costo, vita, potenza);

-- Indici per ordinamento
CREATE INDEX idx_cards_sort ON cards(espansione, tipo, aspetto_primario, costo, nome);

-- Indici per mazzi
CREATE INDEX idx_deck_cards ON deck_cards(deck_id, card_id);
CREATE INDEX idx_decks_user ON decks(user_id, public, created_at);
```

**Query Optimization:**
```php
// Eager loading per evitare N+1 queries
$decks = Deck::with(['user', 'cards.card'])
             ->where('public', true)
             ->orderBy('created_at', 'desc')
             ->paginate(20);

// Query ottimizzata per statistiche
$stats = DB::table('cards')
           ->select(
               'costo',
               DB::raw('COUNT(*) as count'),
               DB::raw('AVG(vita) as avg_vita'),
               DB::raw('AVG(potenza) as avg_potenza')
           )
           ->whereNotIn('tipo', ['Leader', 'Base'])
           ->groupBy('costo')
           ->get();
```

---

## Sicurezza e Validazione

### Autenticazione e Autorizzazione

**Middleware Stack:**
```php
// routes/web.php
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/errors', [AdminController::class, 'errors']);
    Route::post('/admin/scan', [AdminController::class, 'scanAPI']);
});
```

**Admin Check Helper:**
```php
// Helper per controlli admin
if (Auth::admin()) {
    // Mostra informazioni debug
    // Accesso a funzionalità avanzate
}
```

### Validazione Input

**Form Validation:**
```php
class DeckRequest extends FormRequest
{
    public function rules() {
        return [
            'nome' => 'required|string|max:255',
            'descrizione' => 'nullable|string|max:1000',
            'public' => 'boolean',
            'cards' => 'required|array|size:50',
            'cards.*.id' => 'required|exists:cards,id',
            'cards.*.quantity' => 'required|integer|min:1|max:3'
        ];
    }

    public function withValidator($validator) {
        $validator->after(function ($validator) {
            // Validazione regole mazzo personalizzate
            $this->validateDeckRules($validator);
        });
    }
}
```

**XSS Protection:**
```php
// Sanitizzazione automatica input
class SanitizeInput
{
    public static function clean($input) {
        if (is_string($input)) {
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }

        if (is_array($input)) {
            return array_map([self::class, 'clean'], $input);
        }

        return $input;
    }
}
```

---

## Deployment e DevOps

### Script di Deployment

**all.sh - Deployment Completo:**
```bash
#!/bin/bash

# Gestione opzioni versioning
while getopts "vp" opt; do
  case $opt in
    v) # Incrementa versione primaria
      increment_primary_version
      ;;
    p) # Incrementa versione secondaria
      increment_secondary_version
      ;;
  esac
done

# Processo deployment
git add .
git commit -m "Deploy $(get_current_version)"
git push origin main

# Upload FTP con gestione errori
upload_to_ftp() {
    lftp -c "
    set ftp:ssl-allow no;
    set ftp:passive-mode on;
    open ftp://username:password@ftp.swudb.altervista.org;
    mirror -R --delete --verbose . /;
    quit
    "
}
```

**Monitoring e Logging:**
```php
// Log personalizzato per operazioni critiche
Log::channel('operations')->info('Scan API started', [
    'user_id' => Auth::id(),
    'timestamp' => now(),
    'memory_usage' => memory_get_usage(true)
]);

// Error tracking
class ErrorTracker
{
    public static function logError($exception, $context = []) {
        Log::error($exception->getMessage(), [
            'exception' => $exception,
            'context' => $context,
            'user_id' => Auth::id(),
            'url' => request()->fullUrl(),
            'user_agent' => request()->userAgent()
        ]);

        // Notifica admin via Telegram
        TelegramNotifier::sendError($exception, $context);
    }
}
```

---

## Troubleshooting Avanzato

### Problemi Comuni e Soluzioni

**1. Timeout durante Scan API:**
```php
// Soluzione: Job asincroni con chunking
public function scanAPIChunked() {
    $pages = $this->getTotalPages();

    for ($page = 1; $page <= $pages; $page++) {
        dispatch(new ProcessAPIPage($page))
            ->delay(now()->addSeconds($page * 2)); // Stagger requests
    }
}
```

**2. Memory Limit su Import Grandi:**
```php
// Soluzione: Streaming e batch processing
public function importLargeDataset($data) {
    $chunks = array_chunk($data, 100);

    foreach ($chunks as $chunk) {
        DB::transaction(function() use ($chunk) {
            foreach ($chunk as $item) {
                $this->processItem($item);
            }
        });

        // Libera memoria
        gc_collect_cycles();
    }
}
```

**3. Livewire Component Non Responsive:**
```php
// Debug Livewire
public function render() {
    logger('SearchFilter render called', [
        'filters' => $this->getFilters(),
        'results_count' => count($this->filteredCards)
    ]);

    return view('livewire.search-filter');
}
```

### Monitoring Performance

**Query Slow Log:**
```php
// Monitoring query lente
DB::listen(function ($query) {
    if ($query->time > 1000) { // > 1 secondo
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time
        ]);
    }
});
```

---

## Roadmap e Sviluppi Futuri

### Funzionalità in Sviluppo

1. **PWA (Progressive Web App)**
   - Modalità offline per consultazione carte
   - Cache intelligente delle immagini
   - Installazione come app nativa

2. **API GraphQL**
   - Query flessibili per frontend avanzati
   - Subscription per aggiornamenti real-time
   - Schema introspection

3. **Machine Learning**
   - Raccomandazioni mazzi basate su meta
   - Analisi win-rate automatica
   - Predizione carte meta

4. **Integrazione Social**
   - Condivisione mazzi su social network
   - Sistema di rating e recensioni
   - Tornei online integrati

### Miglioramenti Tecnici

1. **Microservizi**
   - Separazione API da frontend
   - Scalabilità orizzontale
   - Deploy indipendenti

2. **Real-time Features**
   - WebSocket per aggiornamenti live
   - Collaborative deck building
   - Chat integrata

3. **Advanced Analytics**
   - Dashboard admin avanzato
   - Metriche utente dettagliate
   - A/B testing framework

---

## Conclusioni

SWUDB rappresenta un esempio completo di applicazione Laravel moderna con:

- **Architettura scalabile** e ben strutturata
- **Performance ottimizzate** attraverso caching e query optimization
- **UX avanzata** con componenti Livewire reattivi
- **Integrazione robusta** con sistemi esterni
- **Deployment automatizzato** e monitoring completo

La combinazione di tecnologie moderne, algoritmi ottimizzati e best practice di sviluppo rende SWUDB una piattaforma solida e facilmente estendibile per la community di Star Wars Unlimited.

---

*Guida creata il: $(date)*
*Versione progetto: $(cat .env | grep APP_VERSION)*
*Autore: Augment Agent*
```
```