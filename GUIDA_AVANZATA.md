# UnlimitedDB - Documentazione Tecnica del Progetto di Maturità

## Indice della Documentazione

1. [Panoramica del Progetto](#panoramica-del-progetto)
2. [Architettura del Sistema](#architettura-del-sistema)
3. [Stack Tecnologico](#stack-tecnologico)
4. [Progettazione del Database](#progettazione-del-database)
5. [Componenti Livewire](#componenti-livewire)
6. [Algoritmi di Ordinamento](#algoritmi-di-ordinamento)
7. [Gestione degli Errori](#gestione-degli-errori)
8. [Integrazione API](#integrazione-api)
9. [Ottimizzazione delle Prestazioni](#ottimizzazione-delle-prestazioni)
10. [Sicurezza e Autenticazione](#sicurezza-e-autenticazione)
11. [Processo di Deployment](#processo-di-deployment)
12. [Testing e Controllo Qualità](#testing-e-controllo-qualità)

---

## Panoramica del Progetto

**UnlimitedDB** è l'applicazione web che ho sviluppato inizialmente per una necessità personale: catalogare e gestire tutte le carte del gioco **Star Wars: Unlimited** della Fantasy Flight Games. Successivamente ho deciso di portare questo progetto come tesina per l'esame di maturità.

Il progetto è nato dalla mancanza di un database completo e ben organizzato per questo gioco relativamente nuovo. Inizialmente l'ho sviluppato per uso personale, ma quando ho visto la complessità tecnica raggiunta e le funzionalità implementate, ho deciso di presentarlo come progetto di maturità. Durante lo sviluppo ho dovuto affrontare diverse sfide tecniche interessanti, dalla progettazione del database alla creazione di algoritmi di ordinamento personalizzati. Il risultato è un sistema completo che gestisce:

### Genesi del Progetto

Quando è uscito Star Wars Unlimited, mi sono reso conto che mancavano strumenti digitali adeguati per gestire le carte e costruire mazzi. I pochi siti esistenti erano incompleti o poco funzionali. Ho quindi iniziato a sviluppare UnlimitedDB come soluzione personale, ma man mano che aggiungevo funzionalità e risolvevo problemi tecnici complessi, il progetto è cresciuto fino a diventare una piattaforma completa che meritava di essere presentata all'esame di stato.

### Funzionalità che ho implementato

**1. Catalogazione delle Carte:**
- Un database che contiene tutte le carte con i loro dati completi
- Sistema di ricerca che ho reso molto flessibile con filtri multipli
- Un algoritmo di ordinamento che ho dovuto creare da zero per rispettare le regole del gioco

**2. Costruzione Mazzi:**
- Ho implementato un sistema per creare mazzi che controlla automaticamente se rispettano le regole
- Gli utenti possono tenere traccia delle carte che possiedono realmente
- È possibile importare ed esportare mazzi nei formati che usano gli altri programmi del settore

**3. Statistiche e Analisi:**
- Il sistema calcola in automatico statistiche dettagliate sui mazzi (cosa che inizialmente non avevo previsto ma si è rivelata molto utile)
- Ho aggiunto dei grafici interattivi per rendere i dati più comprensibili
- Si possono vedere le tendenze su quali carte vengono usate di più

**4. Aspetti Social:**
- Gli utenti possono condividere i loro mazzi o tenerli privati
- Ho implementato un sistema di notifiche per quando escono carte nuove
- Ogni utente ha una sua dashboard personalizzata

### Aspetti Tecnici Interessanti

**1. Struttura dell'Applicazione:**
- Ho usato Laravel come base per gestire la logica e i dati
- La parte visibile è reattiva grazie ai componenti Livewire
- L'integrazione tra server e client funziona senza problemi

**2. Algoritmi Sviluppati ad Hoc:**
- Ho dovuto scrivere un merge sort specifico per ordinare le carte secondo 7 criteri diversi
- L'importazione dei dati avviene in modo asincrono per non bloccare l'interfaccia
- Ho creato delle routine per riconoscere automaticamente le carte duplicate

**3. Controllo degli Errori:**
- Tutti gli errori vengono tracciati automaticamente e mi arrivano notifiche
- C'è una sezione amministrativa dedicata per monitorare i problemi
- Ogni operazione viene registrata con il suo contesto per facilitare il debug

---

## Architettura del Sistema

### Organizzazione del Codice

**1. Struttura MVC Estesa:**

Ho seguito il pattern Model-View-Controller di Laravel, aggiungendo però un layer Livewire per rendere tutto più interattivo:

- **Models**: Card, Deck, User, SystemError - Si occupano dei dati e delle regole di business
- **Controllers**: CardsController, DecksController, AdminController, JobController - Coordinano le varie operazioni
- **Views**: Template Blade che generano le pagine HTML
- **Componenti Livewire**: SearchFilter, DeckManager, AddCardSection, CollezioneManager - Parti dell'interfaccia che si aggiornano dinamicamente

---

## Stack Tecnologico

### Tecnologie Lato Server

**Laravel 12:**
- Il framework PHP che ho scelto come base dell'applicazione
- Utilizza il pattern MVC insieme all'ORM Eloquent
- Ha un sistema di routing molto flessibile e middleware per la sicurezza
- Le code di lavoro permettono di elaborare operazioni pesanti in background

**MySQL 8.0+:**
- Il database relazionale dove vengono salvati tutti i dati
- Ho dovuto ottimizzare diverse query per gestire grandi quantità di carte
- Gli indici composti migliorano notevolmente le prestazioni nelle ricerche

**Eloquent ORM:**
- Permette di lavorare con il database usando oggetti PHP invece di SQL puro
- Per le relazioni più complesse ho dovuto usare il package Compoships
- Il query builder rende molto più semplice costruire query ottimizzate

### Tecnologie Lato Client

**Livewire 3:**
- Un framework che mi permette di creare componenti interattivi senza scrivere molto JavaScript
- La comunicazione tra browser e server avviene automaticamente
- Lo stato viene gestito sul server ma l'interfaccia si aggiorna in tempo reale

**Bootstrap 5.3:**
- La libreria CSS che uso per l'aspetto grafico e la responsività
- Ho implementato il tema scuro per non affaticare gli occhi durante l'uso prolungato
- I componenti predefiniti garantiscono coerenza visiva in tutto il sito

**Alpine.js:**
- Un framework JavaScript molto leggero per le interazioni più semplici
- Si integra perfettamente con Livewire per le funzionalità più avanzate

### Infrastruttura e Deployment

**Altervista.org:**
- Hosting web gratuito con supporto PHP e MySQL
- Configurazione ottimizzata per applicazioni Laravel

**GitHub:**
- Sistema di controllo versione distribuito
- Repository centrale per il codice sorgente

**FTP Automatizzato:**
- Script di deployment automatico per sincronizzazione file
- Gestione incrementale degli aggiornamenti

**2. Architettura Orientata agli Eventi:**

```php
// Esempio di comunicazione event-driven tra componenti
class SearchFilter extends Component
{
    public function applyFilters()
    {
        // Elaborazione filtri complessa...
        $results = $this->buildFilteredQuery()->get();

        // Applicazione algoritmo di ordinamento personalizzato
        if (!$results->isEmpty()) {
            $results = CardsController::mergeSort($results);
        }

        // Dispatch evento per aggiornamento UI
        $this->dispatch('cardsFiltered', $results->toArray());
    }
}

// Listener nel componente parent
class DeckManager extends Component
{
    protected $listeners = [
        'cardsFiltered' => 'updateAvailableCards',
        'cardAdded' => 'addCardToDeck'
    ];
}
```

**3. Repository Pattern (Implicito):**
- Eloquent Models fungono da repository
- Query scopes per logiche di business riutilizzabili
- Caching layer per performance ottimizzate

**4. Observer Pattern:**

```php
// Event/Listener system per notifiche
class MessageCreated
{
    use Dispatchable;

    public function __construct(public $message) {}
}

class SendMessage
{
    public function handle(MessageCreated $event): void
    {
        // Invio notifica Telegram
        JobController::fireAndForgetGet(route("job.sendMessage"), [
            "message" => $event->message,
            "token" => env("JOB_TOKEN")
        ]);
    }
}
```

---

## Stack Tecnologico Dettagliato

### Backend Framework: Laravel 12

**Caratteristiche Utilizzate:**

**1. Eloquent ORM Avanzato:**
```php
// Relazioni complesse con pivot personalizzati
class Deck extends Model
{
    public function cards()
    {
        return $this->belongsToMany(Card::class, 'compositions')
                    ->withPivot('copie')
                    ->withTimestamps();
    }

    // Query scopes per logiche di business
    public function scopePublic($query)
    {
        return $query->where('public', 1);
    }

    public function scopeByUser($query, $username)
    {
        return $query->where('codUtente', $username);
    }
}
```

**2. Middleware Stack Personalizzato:**
```php
// Middleware per autenticazione admin
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/errors', [AdminController::class, 'errors']);
    Route::post('/admin/scan', [AdminController::class, 'scanAPI']);
});
```

**3. Job Queue per Operazioni Asincrone:**
```php
// Gestione import carte con job asincroni
public function startImport()
{
    // Preparazione dati...
    JobController::fireAndForgetGet(route('carte.insertCards'), [
        "token" => env('JOB_TOKEN')
    ]);
}
```

### Frontend Reattivo: Livewire 3

**Vantaggi Architetturali:**

**1. Componenti Stateful:**
```php
class DeckManager extends Component
{
    // State management automatico
    public $mazzo = [];
    public $statistichePerCosto = [];
    public $modalitaRinomina = false;

    // Reattività automatica
    public function updatedMazzo()
    {
        $this->calcolaStatistiche();
    }
}
```

**2. Binding Bidirezionale:**
```blade
{{-- Filtri con aggiornamento real-time --}}
<input type="text" wire:model.live="nome" class="form-control"
       placeholder="Nome carta...">

{{-- Automaticamente triggera updatedNome() --}}
```

**3. Event System Avanzato:**
```php
// Comunicazione tra componenti
$this->dispatch('cardAdded', [
    'cardId' => $this->selectedCardId,
    'copies' => $this->copiesAmount
]);
```

### Database: MySQL 8.0+ con Ottimizzazioni

**Schema Ottimizzato:**
```sql
-- Indici composti per query complesse
CREATE INDEX idx_cards_search ON cards (tipo, aspettoPrimario, costo, rarita);
CREATE INDEX idx_compositions_deck ON compositions (deck_id, card_id);

-- Indici per ordinamento personalizzato
CREATE INDEX idx_cards_sort ON cards (espansione, uscita, numero);
```

### Styling: Bootstrap 5.3 con Tema Dark

**Approccio CSS:**
- Utilizzo esclusivo di classi Bootstrap
- Tema dark nativo con variabili CSS personalizzate
- Componenti responsive per mobile-first design
- Eccezioni controllate per classi custom (bg-custom-light, rarity classes)

---

## Database Design e Ottimizzazioni

### Schema Relazionale Avanzato

**Tabelle Principali:**

```sql
-- Carte del gioco con metadati completi
CREATE TABLE cards (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    cid VARCHAR(255) UNIQUE,           -- ID unico da API
    nome VARCHAR(255) NOT NULL,        -- Nome carta
    titolo VARCHAR(255),               -- Titolo/sottotitolo
    espansione VARCHAR(100) NOT NULL,  -- Set di appartenenza
    numero INT NOT NULL,               -- Numero nel set
    tipo ENUM('Leader', 'Base', 'Unità', 'Miglioria', 'Evento') NOT NULL,
    aspettoPrimario ENUM('Blu', 'Verde', 'Rosso', 'Giallo', 'Nero', 'Bianco'),
    aspettoSecondario ENUM('Nero', 'Bianco'),
    costo INT DEFAULT 0,               -- Costo in risorse
    potenza INT,                       -- Potenza di attacco
    vita INT,                          -- Punti vita
    tratti TEXT,                       -- Tratti separati da ' * '
    arena ENUM('Ground', 'Space'),     -- Arena di gioco
    unica BOOLEAN DEFAULT 0,           -- Carta unica
    rarita ENUM('Common', 'Uncommon', 'Rare', 'Legendary', 'Special') NOT NULL,
    artista VARCHAR(255),              -- Nome artista
    uscita DATE NOT NULL,              -- Data rilascio
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indici ottimizzati per performance
    UNIQUE KEY unique_espansione_numero (espansione, numero),
    INDEX idx_tipo_aspetto (tipo, aspettoPrimario),
    INDEX idx_costo_rarita (costo, rarita),
    INDEX idx_search_nome (nome),
    INDEX idx_uscita (uscita),
    FULLTEXT idx_fulltext_search (nome, titolo, tratti)
);

-- Mazzi degli utenti con metadati
CREATE TABLE decks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,        -- Nome del mazzo
    codUtente VARCHAR(255) NOT NULL,   -- Username proprietario
    public BOOLEAN DEFAULT 0,          -- Visibilità pubblica
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_user_deck (codUtente, nome),
    INDEX idx_public_user (public, codUtente),
    INDEX idx_created_at (created_at)
);

-- Composizione mazzi (relazione many-to-many ottimizzata)
CREATE TABLE compositions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    deck_id BIGINT NOT NULL,           -- FK verso decks
    card_id BIGINT NOT NULL,           -- FK verso cards
    copie INT DEFAULT 1,               -- Numero copie
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (deck_id) REFERENCES decks(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
    UNIQUE KEY unique_deck_card (deck_id, card_id),
    INDEX idx_deck_compositions (deck_id),
    INDEX idx_card_usage (card_id)
);

-- Gestione errori di sistema
CREATE TABLE system_errors (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    exception_class VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(500),
    line INT,
    trace LONGTEXT,
    request_url TEXT,
    request_method VARCHAR(10),
    user_agent TEXT,
    user_id BIGINT,
    status ENUM('new', 'investigating', 'resolved', 'ignored') DEFAULT 'new',
    admin_notes TEXT,
    resolved_by BIGINT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status_created (status, created_at),
    INDEX idx_exception_class (exception_class),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Relazioni Eloquent Avanzate

```php
// Model Card con relazioni ottimizzate
class Card extends Model
{
    protected $fillable = [
        'cid', 'nome', 'titolo', 'espansione', 'numero', 'tipo',
        'aspettoPrimario', 'aspettoSecondario', 'costo', 'potenza', 'vita',
        'tratti', 'arena', 'unica', 'rarita', 'artista', 'uscita'
    ];

    protected $casts = [
        'uscita' => 'date',
        'unica' => 'boolean',
        'costo' => 'integer',
        'potenza' => 'integer',
        'vita' => 'integer',
        'numero' => 'integer'
    ];

    // Relazione con composizioni mazzi
    public function compositions()
    {
        return $this->hasMany(Composition::class);
    }

    // Relazione many-to-many con mazzi
    public function decks()
    {
        return $this->belongsToMany(Deck::class, 'compositions')
                    ->withPivot('copie')
                    ->withTimestamps();
    }

    // Scope per filtri avanzati
    public function scopeByType($query, $type)
    {
        return $query->where('tipo', $type);
    }

    public function scopeByAspect($query, $aspect)
    {
        return $query->where('aspettoPrimario', $aspect)
                    ->orWhere('aspettoSecondario', $aspect);
    }

    public function scopeByCost($query, $min, $max)
    {
        return $query->whereBetween('costo', [$min, $max]);
    }
}
```

---

## Componenti Livewire Personalizzati

### SearchFilter Component - Filtri Avanzati

**Architettura del Componente:**

Il componente `SearchFilter` rappresenta uno dei pezzi più complessi del sistema, gestendo filtri multipli con caching intelligente e comunicazione event-driven.

```php
class SearchFilter extends Component
{
    // Properties per filtri base
    public $nome = '';
    public $titolo = '';
    public $espansione = '';
    public $tipo = '';
    public $aspettoPrimario = '';
    public $aspettoSecondario = '';
    public $rarita = '';

    // Properties per filtri numerici
    public $costoMin = null;
    public $costoMax = null;
    public $potenzaMin = null;
    public $potenzaMax = null;
    public $vitaMin = null;
    public $vitaMax = null;

    // Properties per filtri avanzati
    public $tratti = '';
    public $arena = '';
    public $unica = null;
    public $artista = '';

    // Valori massimi dinamici dal database
    public $maxCostoDb = 999;
    public $maxPotenzaDb = 999;
    public $maxVitaDb = 999;

    // Opzioni per select dinamiche
    public $espansioni = [];
    public $tipi = [];
    public $aspettiPrimari = [];
    public $aspettiSecondari = [];
    public $rarita_options = [];
    public $arene = [];
    public $artisti = [];

    // Risultati e stato
    public $filteredCards = [];
    public $totalResults = 0;
    public $mode = 'page'; // 'page', 'popup', 'collezione'
    public $advancedFiltersOpen = false;

    // Event listeners per comunicazione inter-componente
    protected $listeners = [
        'resetFilters' => 'resetAllFilters',
        'applyFiltersForPopup' => 'getFilteredCardsForPopup',
        'loadAllCards' => 'loadAllCards'
    ];
}
```

**Funzionalità Chiave:**

**1. Caching Intelligente delle Opzioni:**
```php
public function loadFilterOptions()
{
    // Cache delle espansioni ordinate per data di uscita
    $this->espansioni = Cache::remember('cards_filter_espansioni', 3600, function () {
        return Card::select('espansione')
            ->selectRaw('MIN(uscita) as prima_uscita')
            ->groupBy('espansione')
            ->orderBy('prima_uscita')
            ->pluck('espansione');
    });

    // Cache dei valori massimi per filtri numerici
    $maxValues = Cache::remember('cards_max_values', 3600, function () {
        return Card::selectRaw('MAX(costo) as max_costo, MAX(potenza) as max_potenza, MAX(vita) as max_vita')
                   ->first();
    });

    $this->maxCostoDb = $maxValues->max_costo ?? 999;
    $this->maxPotenzaDb = $maxValues->max_potenza ?? 999;
    $this->maxVitaDb = $maxValues->max_vita ?? 999;
}
```

**2. Query Building Dinamico:**
```php
public function applyFilters()
{
    $query = Card::query();

    // Filtri testuali con LIKE
    if (!empty($this->nome)) {
        $query->where('nome', 'like', '%' . $this->nome . '%');
    }

    // Filtri esatti
    if (!empty($this->espansione)) {
        $query->where('espansione', $this->espansione);
    }

    // Filtri numerici con range
    if ($this->costoMin !== null && $this->costoMin !== '') {
        $query->where('costo', '>=', $this->costoMin);
    }
    if ($this->costoMax !== null && $this->costoMax !== '') {
        $query->where('costo', '<=', $this->costoMax);
    }

    // Filtri booleani con gestione null
    if ($this->unica !== null) {
        $query->where('unica', $this->unica);
    }

    $results = $query->get();

    // Applicazione algoritmo di ordinamento personalizzato
    if (!$results->isEmpty()) {
        $results = CardsController::mergeSort($results);
    }

    $this->filteredCards = $results;
    $this->totalResults = $this->filteredCards->count();

    // Event dispatch per aggiornamento UI
    $this->dispatch('cardsFiltered', $this->filteredCards->toArray());
}
```

**3. Reattività Real-time:**
```php
// Metodi automatici per aggiornamento filtri
public function updatedNome() { $this->applyFilters(); }
public function updatedTitolo() { $this->applyFilters(); }
public function updatedEspansione() { $this->applyFilters(); }
public function updatedTipo() { $this->applyFilters(); }
// ... altri metodi updated per ogni property
```

### DeckManager Component - Gestione Mazzi Completa

**Responsabilità del Componente:**

Il `DeckManager` gestisce l'intero ciclo di vita di un mazzo, dalle modifiche alle statistiche in tempo reale.

```php
class DeckManager extends Component
{
    // Dati del mazzo
    public $nome;
    public $user;
    public $deck;
    public $deckObject;
    public $size;
    public $proprietario;
    public $carte;
    public $mazzo = [];

    // Gestione rinominazione
    public $modalitaRinomina = false;
    public $nuovoNome = '';

    // Statistiche calcolate in tempo reale
    public $trattiPrincipali = [];
    public $trattiCompleti = [];
    public $statistichePerCosto = [];
    public $distribuzionePerTipo = [];
    public $distribuzionePerCosto = [];
    public $distribuzionePerAspetto = [];
    public $totaleCarteStatistiche = 0;

    protected $listeners = [
        'cardAdded' => 'addCard',
        'refreshDeck' => '$refresh'
    ];
}
```

**Calcolo Statistiche Avanzate:**
```php
public function calcolaStatistiche()
{
    if (empty($this->mazzo)) {
        $this->resetStatistiche();
        return;
    }

    // Esclusione di Leader e Basi dalle statistiche
    $cartePerStatistiche = array_filter($this->mazzo, function($carta) {
        return !in_array($carta['tipo'], ['Leader', 'Base']);
    });

    // Calcolo tratti divisi e completi
    $trattiDivisi = [];
    $trattiCompleti = [];

    foreach ($cartePerStatistiche as $carta) {
        if (!empty($carta['tratti'])) {
            $tratti = explode(' * ', $carta['tratti']);
            foreach ($tratti as $tratto) {
                $tratto = trim($tratto);
                if (!empty($tratto)) {
                    // Tratti divisi (ogni tratto separatamente)
                    $trattiDivisi[$tratto] = ($trattiDivisi[$tratto] ?? 0) + $carta['copie'];
                }
            }
            // Tratti completi (stringa intera)
            $trattiCompleti[$carta['tratti']] = ($trattiCompleti[$carta['tratti']] ?? 0) + $carta['copie'];
        }
    }

    // Statistiche per costo con medie
    $statistichePerCosto = [];
    foreach ($cartePerStatistiche as $carta) {
        $costo = $carta['costo'];
        if (!isset($statistichePerCosto[$costo])) {
            $statistichePerCosto[$costo] = [
                'count' => 0,
                'vita_totale' => 0,
                'potenza_totale' => 0,
                'vita_media' => 0,
                'potenza_media' => 0
            ];
        }

        $statistichePerCosto[$costo]['count'] += $carta['copie'];
        $statistichePerCosto[$costo]['vita_totale'] += ($carta['vita'] ?? 0) * $carta['copie'];
        $statistichePerCosto[$costo]['potenza_totale'] += ($carta['potenza'] ?? 0) * $carta['copie'];
    }

    // Calcolo medie
    foreach ($statistichePerCosto as $costo => &$stats) {
        if ($stats['count'] > 0) {
            $stats['vita_media'] = round($stats['vita_totale'] / $stats['count'], 1);
            $stats['potenza_media'] = round($stats['potenza_totale'] / $stats['count'], 1);
        }
    }

    $this->trattiPrincipali = $trattiDivisi;
    $this->trattiCompleti = $trattiCompleti;
    $this->statistichePerCosto = $statistichePerCosto;
    $this->totaleCarteStatistiche = array_sum(array_column($cartePerStatistiche, 'copie'));
}
```

---

## Algoritmi di Ordinamento

### Il Merge Sort Personalizzato che ho Sviluppato

**La Sfida Tecnica:**

Ordinare le carte di Star Wars Unlimited non è banale come sembra. Le regole del gioco richiedono un ordinamento molto specifico che non si può ottenere con un semplice `ORDER BY` del database. Ho dovuto quindi implementare un algoritmo merge sort personalizzato che applica 7 criteri diversi in sequenza.

**I 7 Criteri che ho Implementato:**

1. **Tipo Generico** - Leader e Basi vengono sempre per primi
2. **Aspetto Primario** - Blu, Verde, Rosso, Giallo, Nero, Bianco (in quest'ordine)
3. **Aspetto Secondario** - Nero, Bianco, poi lo stesso del primario, infine gli altri
4. **Tipo Specifico** - Unità, Miglioria, Evento
5. **Costo** - Dal più basso al più alto (i Leader sono esclusi da questo criterio)
6. **Data di Uscita** - Per separare le diverse espansioni
7. **Numero Carta** - L'ultimo criterio per risolvere i casi di parità

**Implementazione dell'Algoritmo:**

```php
/**
 * Funzione di confronto per l'ordinamento gerarchico delle carte
 * Implementa 7 criteri di ordinamento in sequenza
 */
public static function compareElements(&$el1, &$el2, $verbose = false)
{
    if($verbose){
        echo "Confronto tra ".$el1["nome"]." e ".$el2["nome"]."<br>";
    }

    // Definizione ordine tipi generici (priorità massima)
    $genericTipoOrder = ['Leader', 'Base'];

    // Definizione ordine aspetti primari
    $primaryAspectOrder = ['Blu', 'Verde', 'Rosso', 'Giallo', "Nero", "Bianco"];

    // Definizione ordine tipi specifici
    $specificTipoOrder = ['Unità', 'Miglioria', 'Evento'];

    // 1. Confronto per tipo generico (Leader/Base vs altri)
    $getGenericTypeWeight = function($el) {
        if ($el["tipo"] == "Leader") return 0;
        if ($el["tipo"] == "Base") return 1;
        return 2; // Unità, Miglioria, Evento
    };

    $genericWeight1 = $getGenericTypeWeight($el1);
    $genericWeight2 = $getGenericTypeWeight($el2);

    if ($genericWeight1 < $genericWeight2) return -1;
    if ($genericWeight1 > $genericWeight2) return 1;

    // 2. Confronto per aspetto primario
    $getPrimaryAspectWeight = function($el) {
        switch($el["aspettoPrimario"]) {
            case "Blu": return 0;
            case "Verde": return 1;
            case "Rosso": return 2;
            case "Giallo": return 3;
            case "Nero": return 4;
            case "Bianco": return 5;
            default: return 6;
        }
    };

    $primaryAspectWeight1 = $getPrimaryAspectWeight($el1);
    $primaryAspectWeight2 = $getPrimaryAspectWeight($el2);

    if ($primaryAspectWeight1 < $primaryAspectWeight2) return -1;
    if ($primaryAspectWeight1 > $primaryAspectWeight2) return 1;

    // 3. Confronto per aspetto secondario
    $getSecondaryAspectWeight = function($el) {
        if ($el["aspettoSecondario"] == "Nero") return 0;
        if ($el["aspettoSecondario"] == "Bianco") return 1;
        if ($el["aspettoSecondario"] == $el["aspettoPrimario"]) return 2;
        return 3;
    };

    $secondaryAspectWeight1 = $getSecondaryAspectWeight($el1);
    $secondaryAspectWeight2 = $getSecondaryAspectWeight($el2);

    if ($secondaryAspectWeight1 < $secondaryAspectWeight2) return -1;
    if ($secondaryAspectWeight1 > $secondaryAspectWeight2) return 1;

    // 4. Confronto per tipo specifico
    $getSpecificTypeWeight = function($el) {
        switch($el["tipo"]) {
            case "Unità": return 0;
            case "Miglioria": return 1;
            case "Evento": return 2;
            default: return 3;
        }
    };

    $specificTypeWeight1 = $getSpecificTypeWeight($el1);
    $specificTypeWeight2 = $getSpecificTypeWeight($el2);

    if ($specificTypeWeight1 < $specificTypeWeight2) return -1;
    if ($specificTypeWeight1 > $specificTypeWeight2) return 1;

    // 5. Confronto per costo (eccetto Leader)
    if ($el1["tipo"] != "Leader" && $el2["tipo"] != "Leader") {
        if ($el1["costo"] < $el2["costo"]) return -1;
        if ($el1["costo"] > $el2["costo"]) return 1;
    }

    // 6. Confronto per data uscita (espansioni diverse)
    if($el1["espansione"] != $el2["espansione"]){
        $compareDate = strcmp($el1['uscita'], $el2['uscita']);
        if ($compareDate < 0) return -1;
        if ($compareDate > 0) return 1;
    }

    // 7. Confronto finale per numero carta
    if ($el1["numero"] < $el2["numero"]) return -1;
    if ($el1["numero"] > $el2["numero"]) return 1;

    return 0; // Elementi identici
}
```

**Implementazione Merge Sort:**

```php
public static function mergeSort(&$data, $verbose = false)
{
    // Gestione sia Collection che array
    $isCollection = $data instanceof \Illuminate\Support\Collection;

    if ($isCollection) {
        // Caso base per Collection
        if ($data->count() <= 1) {
            return $data;
        }

        // Divisione in due metà
        $mid = floor($data->count() / 2);
        $left = $data->take($mid);
        $right = $data->skip($mid);

        // Ricorsione
        $left = CardsController::mergeSort($left, $verbose);
        $right = CardsController::mergeSort($right, $verbose);

        // Merge delle due metà ordinate
        $result = collect();
        $leftIndex = 0;
        $rightIndex = 0;
        $leftArray = $left->values()->all();
        $rightArray = $right->values()->all();

        while ($leftIndex < count($leftArray) && $rightIndex < count($rightArray)) {
            if (CardsController::compareElements($leftArray[$leftIndex], $rightArray[$rightIndex], $verbose) <= 0) {
                $result->push($leftArray[$leftIndex]);
                $leftIndex++;
            } else {
                $result->push($rightArray[$rightIndex]);
                $rightIndex++;
            }
        }

        // Aggiunta elementi rimanenti
        while ($leftIndex < count($leftArray)) {
            $result->push($leftArray[$leftIndex]);
            $leftIndex++;
        }

        while ($rightIndex < count($rightArray)) {
            $result->push($rightArray[$rightIndex]);
            $rightIndex++;
        }

        return $result;
    } else {
        // Gestione array tradizionali
        if (count($data) <= 1) {
            return $data;
        }

        $mid = floor(count($data) / 2);
        $left = array_slice($data, 0, $mid);
        $right = array_slice($data, $mid);

        $left = CardsController::mergeSort($left, $verbose);
        $right = CardsController::mergeSort($right, $verbose);

        // Merge per array
        $result = [];
        $leftIndex = 0;
        $rightIndex = 0;

        while ($leftIndex < count($left) && $rightIndex < count($right)) {
            if (CardsController::compareElements($left[$leftIndex], $right[$rightIndex], $verbose) <= 0) {
                $result[] = $left[$leftIndex];
                $leftIndex++;
            } else {
                $result[] = $right[$rightIndex];
                $rightIndex++;
            }
        }

        // Aggiunta elementi rimanenti
        while ($leftIndex < count($left)) {
            $result[] = $left[$leftIndex];
            $leftIndex++;
        }

        while ($rightIndex < count($right)) {
            $result[] = $right[$rightIndex];
            $rightIndex++;
        }

        return $result;
    }
}
```

**Vantaggi dell'Implementazione:**

1. **Complessità O(n log n)** - Efficiente anche per grandi dataset
2. **Stabilità** - Mantiene l'ordine relativo di elementi uguali
3. **Flessibilità** - Gestisce sia Collection Laravel che array PHP
4. **Debugging** - Modalità verbose per analisi del comportamento
5. **Riutilizzabilità** - Utilizzato in tutto il sistema per consistenza

---

## Sistema di Gestione Errori Personalizzato

### Architettura del Sistema

Il sistema di gestione errori di UnlimitedDB è completamente personalizzato e rappresenta una delle implementazioni più avanzate del progetto. Gestisce automaticamente tutti gli errori dell'applicazione con notifiche multi-canale e dashboard amministrativa.

**Componenti del Sistema:**

1. **Exception Handler Personalizzato** (bootstrap/app.php)
2. **Model SystemError** per persistenza
3. **Email Notifications** con template dedicati
4. **Notifiche Telegram** in tempo reale
5. **Dashboard Admin** per gestione errori

### Exception Handler Avanzato

**Configurazione in bootstrap/app.php:**

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->reportable(function (Throwable $e) {
        // 1. Salvataggio errore nel database
        $systemError = null;
        try {
            $systemError = SystemError::create([
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_url' => request()->fullUrl() ?? null,
                'request_method' => request()->method() ?? null,
                'user_agent' => request()->userAgent() ?? null,
                'user_id' => Auth::id(),
                'status' => 'new',
            ]);
        } catch (\Exception $dbException) {
            // Fallback logging se database non disponibile
            \Log::error("Failed to save error to database: " . $dbException->getMessage());
        }

        // 2. Notifica Telegram immediata
        MessageCreated::dispatch("Errore: " . $e->getMessage());

        // 3. Email a tutti gli admin
        try {
            $admins = User::getAdmins();
            if ($admins->isNotEmpty()) {
                $requestUrl = request()->fullUrl() ?? null;
                $requestMethod = request()->method() ?? null;
                $userAgent = request()->userAgent() ?? null;

                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(
                        new ErrorNotificationEmail($e, $requestUrl, $requestMethod, $userAgent, $systemError)
                    );
                }
            }
        } catch (\Exception $mailException) {
            // Fallback Telegram se email fallisce
            MessageCreated::dispatch("Errore invio email admin: " . $mailException->getMessage());
        }
    });

    // Rendering personalizzato basato su ruolo utente
    $exceptions->renderable(function (Throwable $e, $request) {
        if (Auth::admin()) {
            // Admin vedono dettagli completi con layout app
            config(['app.debug' => true]);

            return response()->view('errors.admin-debug', [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'request' => $request
            ], 500);
        }

        // Utenti normali vedono pagine errore standard
        config(['app.debug' => false]);
        return null; // Usa view personalizzate
    });
})
```

### Model SystemError

**Struttura del Model:**

```php
class SystemError extends Model
{
    protected $fillable = [
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'request_url',
        'request_method',
        'user_agent',
        'user_id',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relazioni
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes per dashboard admin
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['new', 'investigating']);
    }

    public function scopeByClass($query, $class)
    {
        return $query->where('exception_class', $class);
    }

    // Metodi di utilità
    public function getShortTraceAttribute()
    {
        return Str::limit($this->trace, 500);
    }

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('d/m/Y H:i:s');
    }
}
```

### Email Notification System

**Template Email Personalizzato:**

```php
class ErrorNotificationEmail extends Mailable
{
    public function __construct(
        private Throwable $exception,
        private ?string $requestUrl = null,
        private ?string $requestMethod = null,
        private ?string $userAgent = null,
        private $systemError = null
    ) {
        $this->errorMessage = $exception->getMessage();
        $this->errorFile = $exception->getFile();
        $this->errorLine = $exception->getLine();
        $this->timestamp = now()->format('d/m/Y H:i:s');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🚨 Errore Sistema UnlimitedDB - ' . Str::limit($this->errorMessage, 50),
            from: new Address(env('MAIL_FROM_ADDRESS'), 'UnlimitedDB Error System')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.error-notification',
            with: [
                'errorMessage' => $this->errorMessage,
                'errorFile' => $this->errorFile,
                'errorLine' => $this->errorLine,
                'requestUrl' => $this->requestUrl,
                'requestMethod' => $this->requestMethod,
                'userAgent' => $this->userAgent,
                'timestamp' => $this->timestamp,
                'exceptionClass' => get_class($this->exception),
                'systemError' => $this->systemError,
            ]
        );
    }
}
```

### Sistema di Notifiche Telegram

**Event/Listener Pattern:**

```php
// Event per messaggi
class MessageCreated
{
    use Dispatchable;

    public function __construct(public $message) {}
}

// Listener per invio Telegram
class SendMessage
{
    public function handle(MessageCreated $event): void
    {
        // Fire-and-forget per evitare blocking
        JobController::fireAndForgetGet(route("job.sendMessage"), [
            "message" => $event->message,
            "token" => env("JOB_TOKEN")
        ]);
    }
}

// JobController per invio effettivo
public function sendMessage(Request $request)
{
    $message = $request->input('message');
    $botToken = env('TELEGRAM_BOT_TOKEN');
    $chatId = env('TELEGRAM_CHAT_ID');

    try {
        Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message
        ]);
    } catch (\Exception $e) {
        \Log::error("Errore Telegram: " . $e->getMessage());
    }
}
```

### Dashboard Admin per Gestione Errori

**Funzionalità della Dashboard:**

1. **Lista errori con filtri** (nuovo, in corso, risolto)
2. **Dettagli completi** con stack trace
3. **Gestione stato** e note admin
4. **Statistiche** per tipo di errore
5. **Azioni bulk** per gestione multipla

**Vantaggi del Sistema:**

1. **Notifiche Immediate** - Telegram + Email in tempo reale
2. **Context Awareness** - Cattura URL, user agent, utente
3. **Persistenza** - Tutti gli errori salvati per analisi
4. **Gestione Centralizzata** - Dashboard admin completa
5. **Fallback Robusto** - Gestione errori anche in caso di problemi sistema
6. **Differenziazione Utenti** - Admin vedono dettagli, utenti pagine pulite

---

## Integrazione API e Web Scraping

### Sistema di Import Automatico

**Architettura del Sistema:**

Il sistema di import di UnlimitedDB integra l'API ufficiale di Star Wars Unlimited con fallback su web scraping per garantire aggiornamenti completi e affidabili.

**Flusso di Import:**

1. **Scansione API** per nuove carte
2. **Confronto** con database esistente
3. **Recupero dettagli** via API
4. **Fallback JSON** se API non disponibile
5. **Inserimento batch** asincrono
6. **Notifiche** agli utenti

```php
public function startImport()
{
    Log::info("Starting card import process with API integration");

    // 1. Generazione thread ID per tracking
    $threadId = 'import_' . time() . '_' . rand(1000, 9999);
    $logFile = $this->createScanLog();

    // 2. Scansione API per ottenere lista carte
    $this->writeScanLog("Inizio scansione API Star Wars Unlimited", $logFile);

    try {
        $apiCards = $this->scanAPIForCards($threadId, $logFile);
        $this->writeScanLog("API scansionata: " . count($apiCards) . " carte trovate", $logFile);
    } catch (\Exception $e) {
        $this->writeScanLog("Errore scansione API: " . $e->getMessage(), $logFile);
        ThreadMessageCreated::dispatch($threadId, "Errore scansione API: " . $e->getMessage(), true);
        return view("carte.update", ["output" => "Errore durante la scansione API"]);
    }

    // 3. Confronto con database esistente
    $existingCards = Card::pluck('cid')->toArray();
    $newCards = array_filter($apiCards, function($card) use ($existingCards) {
        return !in_array($card['cid'], $existingCards);
    });

    if (empty($newCards)) {
        $this->writeScanLog("Nessuna nuova carta trovata", $logFile);
        ThreadMessageCreated::dispatch($threadId, "Scansione completata - Nessuna nuova carta", true);
        return view("carte.update", ["output" => "Nessuna nuova carta da importare"]);
    }

    $this->writeScanLog("Trovate " . count($newCards) . " nuove carte da importare", $logFile);

    // 4. Recupero dettagli per nuove carte
    $detailedCards = [];
    foreach ($newCards as $card) {
        try {
            $details = $this->getCardDetailsFromAPI($card['cid']);
            if ($details) {
                $detailedCards[] = $details;
            }
        } catch (\Exception $e) {
            $this->writeScanLog("Errore recupero dettagli carta {$card['cid']}: " . $e->getMessage(), $logFile);
        }
    }

    // 5. Preparazione per inserimento asincrono
    $this->prepareAsyncInsertion($detailedCards, $threadId, $logFile);

    return view("carte.update", ["output" => "Import avviato per " . count($detailedCards) . " carte"]);
}
```

**Gestione Batch Asincrona:**

```php
public function insertCards(Request $request)
{
    $threadId = $request->input('threadId');

    // Caricamento dati da file temporaneo
    $filePath = storage_path("app/cards_to_insert.json");
    if (!file_exists($filePath)) {
        ThreadMessageCreated::dispatch($threadId, "File dati non trovato", true);
        return;
    }

    $data = json_decode(file_get_contents($filePath), true);
    $cards = $data['cards'];
    $logFile = $data['logFile'];
    $totalCards = count($cards);

    ThreadMessageCreated::dispatch($threadId, "Inizio inserimento batch: {$totalCards} carte");

    $batchSize = 50; // Batch ottimizzato per performance
    $insertedCount = 0;

    for ($i = 0; $i < $totalCards; $i += $batchSize) {
        $batch = array_slice($cards, $i, $batchSize);

        try {
            // Inserimento batch con transazione
            DB::transaction(function() use ($batch, &$insertedCount) {
                foreach ($batch as $cardData) {
                    // Gestione caso speciale per carta ID '7499995534'
                    if ($cardData['cid'] === '7499995534') {
                        $cardData['numero'] = 100;
                    }

                    Card::create($cardData);
                    $insertedCount++;
                }
            });

            $progress = round(($insertedCount / $totalCards) * 100, 1);
            ThreadMessageCreated::dispatch($threadId, "Progresso: {$insertedCount}/{$totalCards} carte ({$progress}%)");

        } catch (\Exception $e) {
            $this->writeScanLog("Errore inserimento batch: " . $e->getMessage(), $logFile);

            // Restart automatico del processo
            if ($i + $batchSize < $totalCards) {
                $remainingCards = array_slice($cards, $i + $batchSize);
                $insertData = [
                    'cards' => $remainingCards,
                    'threadId' => $threadId,
                    'logFile' => $logFile,
                    'timestamp' => time()
                ];
                file_put_contents($filePath, json_encode($insertData));

                JobController::fireAndForgetGet(route('carte.insertCards', ['threadId' => $threadId]), [
                    "token" => env('JOB_TOKEN')
                ]);

                ThreadMessageCreated::dispatch($threadId, "Processo riavviato automaticamente - Inserite {$insertedCount}/{$totalCards} carte");
                return;
            }
        }

        // Delay tra batch per evitare sovraccarico
        usleep(100000); // 100ms
    }

    // Completamento processo
    ThreadMessageCreated::dispatch($threadId, "Import completato: {$insertedCount} carte inserite", true);
    $this->writeScanLog("Import completato con successo: {$insertedCount} carte", $logFile);

    // Cleanup file temporaneo
    unlink($filePath);

    // Invio email notifiche agli utenti
    $this->sendNewCardsNotification($insertedCount);
}
```

### Sistema di Logging Avanzato

**Logging Timestampato:**

```php
private function createScanLog()
{
    $timestamp = now()->format('Y_m_d_H_i');
    $filename = "{$timestamp}.log";
    $logPath = storage_path("logs/scansione/{$filename}");

    $this->writeScanLog("=== INIZIO SCANSIONE UnlimitedDB ===", $logPath);
    $this->writeScanLog("Timestamp: " . now()->format('d/m/Y H:i:s'), $logPath);
    $this->writeScanLog("Versione: " . env('APP_VERSION_PRIMARY', '1') . '.' . env('APP_VERSION_SECONDARY', '0') . '.' . env('APP_VERSION_TERTIARY', '0'), $logPath);

    return $logPath;
}

private function writeScanLog($message, $logFile)
{
    $timestamp = now()->format('H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";

    // Ensure logs/scansione directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents(
        $logFile,
        $logMessage,
        FILE_APPEND | LOCK_EX
    );

    // Anche nel log Laravel per backup
    Log::info("SCAN: {$message}");
}
```

---

## Performance e Caching

### Strategie di Caching Implementate

**1. Query Caching per Filtri:**

```php
// Cache opzioni filtri per 1 ora
$this->espansioni = Cache::remember('cards_filter_espansioni', 3600, function () {
    return Card::select('espansione')
        ->selectRaw('MIN(uscita) as prima_uscita')
        ->groupBy('espansione')
        ->orderBy('prima_uscita')
        ->pluck('espansione');
});

// Cache valori massimi per filtri numerici
$maxValues = Cache::remember('cards_max_values', 3600, function () {
    return Card::selectRaw('MAX(costo) as max_costo, MAX(potenza) as max_potenza, MAX(vita) as max_vita')
               ->first();
});
```

**2. Ottimizzazioni Database:**

```sql
-- Indici composti per query complesse
CREATE INDEX idx_cards_filter ON cards (tipo, aspettoPrimario, costo, rarita);
CREATE INDEX idx_cards_search ON cards (espansione, numero);
CREATE INDEX idx_compositions_deck ON compositions (deck_id, card_id);

-- Indice full-text per ricerca testuale
CREATE FULLTEXT INDEX idx_fulltext_search ON cards (nome, titolo, tratti);
```

**3. Lazy Loading e Eager Loading:**

```php
// Eager loading per evitare N+1 queries
$decks = Deck::with(['cards' => function($query) {
    $query->select('cards.id', 'nome', 'tipo', 'costo', 'aspettoPrimario');
}])->where('public', 1)->get();

// Lazy loading condizionale
if ($includeStats) {
    $deck->load('cards.compositions');
}
```

**4. Chunking per Grandi Dataset:**

```php
// Elaborazione batch per import grandi
Card::chunk(1000, function($cards) {
    foreach ($cards as $card) {
        // Elaborazione singola carta
        $this->processCard($card);
    }
});
```

### Ottimizzazioni Frontend

**1. Asset Optimization:**

```javascript
// Vite configuration per bundling ottimizzato
export default defineConfig({
    plugins: [laravel({
        input: ['resources/css/app.css', 'resources/js/app.js'],
        refresh: true,
    })],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['bootstrap'],
                    charts: ['chart.js']
                }
            }
        }
    }
});
```

**2. Livewire Optimization:**

```php
// Debouncing per filtri real-time
<input type="text" wire:model.live.debounce.300ms="nome"
       class="form-control" placeholder="Nome carta...">

// Lazy loading per componenti pesanti
<div wire:init="loadHeavyData">
    @if($dataLoaded)
        {{-- Contenuto pesante --}}
    @else
        <div class="spinner-border"></div>
    @endif
</div>
```

---

## Sicurezza e Autenticazione

### Sistema di Autenticazione Personalizzato

**Middleware di Autorizzazione:**

```php
// Middleware personalizzato per admin
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/errors', [AdminController::class, 'errors']);
    Route::get('/admin/query', [AdminController::class, 'query']);
    Route::post('/admin/scan', [AdminController::class, 'scanAPI']);
});

// Helper per controllo admin
if (Auth::admin()) {
    // Funzionalità admin
}
```

**Protezione CSRF e Validazione:**

```php
// Validazione input con regole personalizzate
public function rules()
{
    return [
        'nome' => 'required|string|max:255|unique:decks,nome,NULL,id,codUtente,' . Auth::user()->name,
        'public' => 'boolean',
        'cards' => 'array',
        'cards.*.id' => 'required|exists:cards,id',
        'cards.*.copie' => 'required|integer|min:1|max:3'
    ];
}

// Protezione CSRF automatica per Livewire
class DeckManager extends Component
{
    protected $rules = [
        'nuovoNome' => 'required|string|max:255',
        'copiesAmount' => 'integer|min:1|max:3'
    ];
}
```

**Sanitizzazione Input:**

```php
// Sanitizzazione filename per export
private function sanitizeFilename($filename)
{
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    $filename = preg_replace('/_{2,}/', '_', $filename);
    return trim($filename, '_');
}

// Escape output per prevenire XSS
{{ Str::limit(e($card->nome), 50) }}
```

### Gestione Sessioni e Rate Limiting

**Rate Limiting per API:**

```php
// Throttling per route API
Route::middleware(['throttle:api'])->group(function () {
    Route::get('/api/carta/{espansione}/{numero}', [CardsController::class, 'apiCard']);
    Route::get('/api/carte/{espansione}', [CardsController::class, 'apiCards']);
});

// Rate limiting personalizzato per import
Route::middleware(['throttle:import,1,60'])->group(function () {
    Route::post('/carte/import', [CardsController::class, 'startImport']);
});
```

---

## Deployment e DevOps

### Sistema di Deployment Automatizzato

**Script di Deployment (bash/all.sh):**

```bash
#!/bin/bash

# Gestione versioning automatico
if [[ "$1" == "-v" ]]; then
    # Incrementa versione primaria
    current_primary=$(grep APP_VERSION_PRIMARY .env | cut -d '=' -f2)
    new_primary=$((current_primary + 1))
    sed -i "s/APP_VERSION_PRIMARY=$current_primary/APP_VERSION_PRIMARY=$new_primary/" .env
    sed -i "s/APP_VERSION_SECONDARY=.*/APP_VERSION_SECONDARY=0/" .env
    sed -i "s/APP_VERSION_TERTIARY=.*/APP_VERSION_TERTIARY=0/" .env
elif [[ "$1" == "-p" ]]; then
    # Incrementa versione secondaria
    current_secondary=$(grep APP_VERSION_SECONDARY .env | cut -d '=' -f2)
    new_secondary=$((current_secondary + 1))
    sed -i "s/APP_VERSION_SECONDARY=$current_secondary/APP_VERSION_SECONDARY=$new_secondary/" .env
    sed -i "s/APP_VERSION_TERTIARY=.*/APP_VERSION_TERTIARY=0/" .env
fi

# Build assets
npm run build

# Commit e push
./bash/cmt.sh "Deploy automatico $(date '+%Y-%m-%d %H:%M:%S')"

# Upload via FTP
./bash/ftp.sh

echo "Deployment completato!"
```

**Script di Commit con Versioning:**

```bash
#!/bin/bash
# bash/cmt.sh

# Ottieni versione corrente
VERSION_PRIMARY=$(grep APP_VERSION_PRIMARY .env | cut -d '=' -f2)
VERSION_SECONDARY=$(grep APP_VERSION_SECONDARY .env | cut -d '=' -f2)
VERSION_TERTIARY=$(grep APP_VERSION_TERTIARY .env | cut -d '=' -f2)
VERSION="v${VERSION_PRIMARY}.${VERSION_SECONDARY}.${VERSION_TERTIARY}"

# Commit con messaggio che include versione
git add .
git commit -m "$1 - $VERSION"
git push origin main

echo "Commit e push completati per versione $VERSION"
```

### Monitoring e Health Checks

**Health Check Endpoint:**

```php
// Route automatica Laravel
Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => env('APP_VERSION_PRIMARY', '1') . '.' .
                    env('APP_VERSION_SECONDARY', '0') . '.' .
                    env('APP_VERSION_TERTIARY', '0'),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::store()->getStore() ? 'available' : 'unavailable'
    ]);
});
```

**Logging Strutturato:**

```php
// Log con context per debugging
Log::info('Card import started', [
    'user_id' => Auth::id(),
    'thread_id' => $threadId,
    'cards_count' => count($cards),
    'memory_usage' => memory_get_usage(true),
    'execution_time' => microtime(true) - $startTime
]);
```

---

## Testing e Quality Assurance

### Strategia di Testing

**1. Testing in Produzione:**
- Utilizzo di unlimiteddb.net come ambiente di test
- Deployment tramite bash/all.sh per test immediati
- Monitoring errori in tempo reale via Telegram

**2. Unit Testing per Algoritmi:**

```php
// Test per merge sort
class CardsSortingTest extends TestCase
{
    public function test_merge_sort_orders_cards_correctly()
    {
        $cards = collect([
            ['nome' => 'Carta B', 'tipo' => 'Unità', 'costo' => 2],
            ['nome' => 'Carta A', 'tipo' => 'Unità', 'costo' => 1],
            ['nome' => 'Leader', 'tipo' => 'Leader', 'costo' => 0]
        ]);

        $sorted = CardsController::mergeSort($cards);

        $this->assertEquals('Leader', $sorted->first()['nome']);
        $this->assertEquals('Carta A', $sorted->skip(1)->first()['nome']);
    }
}
```

**3. Integration Testing per Livewire:**

```php
class SearchFilterTest extends TestCase
{
    public function test_search_filter_applies_correctly()
    {
        Livewire::test(SearchFilter::class)
            ->set('nome', 'Luke')
            ->assertSee('Luke Skywalker')
            ->assertDontSee('Darth Vader');
    }
}
```

### Quality Assurance

**1. Code Standards:**
- PSR-12 compliance per PHP
- Documentazione completa per metodi complessi
- Type hints e return types consistenti

**2. Performance Monitoring:**
- Query logging per identificare N+1 problems
- Memory usage tracking per import grandi
- Response time monitoring per endpoint API

**3. Error Tracking:**
- Sistema di gestione errori personalizzato
- Notifiche immediate per errori critici
- Dashboard admin per analisi trend errori

---

## Conclusioni e Considerazioni Finali

### Punti di Forza del Progetto

**1. Architettura Scalabile:**
- Separazione chiara delle responsabilità
- Pattern architetturali consolidati
- Estendibilità per future funzionalità

**2. Algoritmi Personalizzati:**
- Merge sort ottimizzato per dominio specifico
- Sistema di import robusto e fault-tolerant
- Gestione errori avanzata e proattiva

**3. User Experience Moderna:**
- Interfaccia reattiva con Livewire
- Filtri real-time senza page reload
- Design responsive e accessibile

**4. DevOps e Deployment:**
- Automazione completa del deployment
- Versioning automatico
- Monitoring e alerting integrati

### Tecnologie Apprese e Implementate

**Backend:**
- Laravel 12 con tutte le funzionalità avanzate
- Eloquent ORM con relazioni complesse
- Event/Listener system per architettura event-driven
- Job queues per operazioni asincrone

**Frontend:**
- Livewire 3 per reattività senza JavaScript complesso
- Bootstrap 5.3 con tema dark personalizzato
- Chart.js per visualizzazioni dati
- Responsive design mobile-first

**Database:**
- MySQL con ottimizzazioni avanzate
- Indici composti per performance
- Transazioni per consistenza dati
- Full-text search per ricerca testuale

**DevOps:**
- Bash scripting per automazione
- Git workflow con versioning automatico
- FTP deployment per hosting condiviso
- Monitoring e logging strutturato

### Sfide Tecniche Risolte

**1. Ordinamento Complesso:**
- Implementazione merge sort per 9 criteri gerarchici
- Gestione sia Collection Laravel che array PHP
- Modalità debug per analisi comportamento

**2. Import Asincrono:**
- Gestione timeout con chunking intelligente
- Restart automatico in caso di errori
- Progress tracking con notifiche real-time

**3. Gestione Errori:**
- Sistema multi-canale (email + Telegram)
- Persistenza errori per analisi
- Differenziazione admin/utenti

**4. Performance:**
- Caching strategico per query frequenti
- Lazy loading per componenti pesanti
- Ottimizzazioni database con indici mirati

### Valore Educativo del Progetto

Questo progetto dimostra la capacità di:

1. **Progettare** architetture software complesse
2. **Implementare** algoritmi personalizzati per esigenze specifiche
3. **Integrare** multiple tecnologie in modo coerente
4. **Gestire** la complessità attraverso pattern consolidati
5. **Ottimizzare** performance e user experience
6. **Automatizzare** processi di deployment e monitoring
7. **Risolvere** problemi reali con soluzioni innovative

UnlimitedDB rappresenta un esempio completo di applicazione web moderna che combina best practice consolidate con soluzioni innovative per creare una piattaforma robusta, scalabile e user-friendly per la community di Star Wars Unlimited.

---

*Autore: Mandich Riccardo*
*Progetto: UnlimitedDB - Star Wars Unlimited Database*
*Anno Scolastico: 2024/2025*
*Esame di Stato - Istituto Tecnico Informatico*
```
```
