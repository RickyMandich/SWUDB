# Implementation Plan — Ricostruzione UnlimitedDB (esercizio guidato)

> Questo documento è una guida passo-passo pensata per essere eseguita manualmente da te. Le fasi già completate sono riassunte in breve (per non perdere lo storico delle decisioni prese); le fasi/step ancora da fare restano nel dettaglio completo.
>
> Ambiente di riferimento: progetto in `C:\Users\RickyMandich\PROJECT\unlimiteddb`, Laravel 12.12, PHP 8.2.29 (via cmd.exe, dove sono installati Composer/Laravel), MariaDB, Pest, Laravel Breeze per l'auth, deploy Docker+Traefik su VM Oracle (stesso schema degli altri siti `*.mandich.dev`).
>
> **Convenzione di codice**: ogni metodo non ovvio va documentato con PHPDoc bilingue (descrizione tecnica in inglese + descrizione discorsiva in italiano), come già fatto in SWUDB. Esempio:
> ```php
> /**
>  * English technical description of the method
>  * Descrizione italiana "alla buona" del metodo
>  *
>  * @param Type $parameter Description of parameter
>  * @return ReturnType Description of return value
>  */
> ```

---

## ✅ Fase 0 — Setup progetto (completata)

- Breeze e `spatie/laravel-permission` installati
- `.env` configurato (MariaDB, `QUEUE_CONNECTION=database`)
- Tabelle `jobs`/`failed_jobs` e tabelle Spatie migrate
- `.env-overrides` agganciato in `bootstrap/app.php` prima di `Application::configure()` (pattern SWUDB: variabili non sensibili come `APP_VERSION_*` tracciate in Git, a differenza di `.env`)

---

## ✅ Fase 0bis — Ambiente Docker locale (completata)

- `.dockerignore` creato, incluso `bootstrap/cache/*.php` (evita di portare nella build cache stale generate in locale con dev-dependency come Breeze — causa un errore `Class ... ServiceProvider not found` durante `composer dump-autoload --no-dev` se non escluso)
- `.gitignore` aggiornato con `/bootstrap/cache/*.php` / `!bootstrap/cache/.gitkeep`, per lo stesso motivo (evitare che la cache stale finisca committata e riproduca lo stesso errore nel deploy reale via `new-site.sh`)
- `Dockerfile`, `docker/entrypoint.sh`, `docker/nginx/default.conf`, `docker/mysql/init.sql`, `docker-compose.dev.yml` creati, identici (a parte rete/porta) a quanto genererà `new-site.sh` in produzione — `php:8.2-fpm-alpine`, fix MIME-type via `/opt/build-seed` + volume `build_assets`, utente DB reale ristretto per IP via `docker/mysql/init.sql`
- Verificato funzionante su `http://localhost:66`

**Promemoria per la Fase 7**: `new-site.sh` rigenera comunque da zero questi file sul server e li committa — quanto fatto qui serve a testare in locale con lo stesso comportamento, non è la versione che finirà in produzione.

---

## Fase 1 — Autenticazione e permessi

### ✅ Fatto
- Breeze (variante Blade) installato
- Trait `HasRoles` aggiunto a `User`
- `PermissionSeeder` creato e registrato in `DatabaseSeeder`, con permessi: `cards.import`, `cards.manage`, `decks.manage-any`, `collections.manage-any`, `users.manage`, `bot.notifications.receive`, ruolo `admin` con tutti i permessi
- Verificato nel codice il 13/09: tutto corretto

### 🔧 Da fare

**Step 1.6 — Abilita la verifica email nativa**
A differenza della vecchia versione (token custom a 60 caratteri, metodi ad-hoc), usa il meccanismo nativo di Laravel/Breeze:
```php
// app/Models/User.php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    // ...
}
```
Proteggi le rotte che richiedono email verificata con il middleware `verified`:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    // rotte che richiedono email confermata
});
```
Breeze genera già le viste/route di verifica (`verify-email`, notifica automatica alla registrazione) — non serve altro codice custom.
Riferimento: https://laravel.com/docs/12.x/verification

☐ Fase 1 completata

---

## Fase 2 — Catalogo carte e import via queue

### ✅ Fatto
- Modelli `Expansion`, `Card` creati; migration `expansions`/`cards` create (nomi già corretti: `expansions` invece di `sets`, per evitare la keyword SQL `SET`)
- `ImportCardsFromSwuApiJob` creato (`ShouldQueue`, `$tries = 3`, `$backoff = 60`)
- Comando `cards:scan` creato, dispaccia il job
- Chiamata `Schedule::command('cards:scan')->weeklyOn(1, '00:00')` aggiunta in `routes/console.php`
- Worker testato in locale con `php artisan queue:work`

### 🔧 Da fare (verificato nel codice il 13/09 — nessuno di questi è ancora stato applicato)

1. **Bug bloccante — `routes/console.php`**: manca `use Illuminate\Support\Facades\Schedule;` in cima al file. Così com'è, `Schedule::command(...)` dà errore "Class Schedule not found" (il file non ha namespace, quindi PHP cerca `\Schedule` nel namespace globale).
2. **Aggiungi `cards.release_date`** (data, quando *quella carta* è uscita al pubblico) e **rinomina `expansions.releaseDate` in `legal_date`** (da quando le carte dell'espansione sono legali in torneo) — sono due concetti diversi, non un'unica data come nella vecchia versione. Entrambe come vero tipo `date`, non stringa libera.
3. **Aggiungi gli aspetti come tabella dedicata**, non colonna `json`: `aspects` (id, name, color, slug, `order` per l'ordinamento in UI) + pivot `card_aspect` (cid, aspect_id). Permette di filtrare per aspetto con una join indicizzata invece che con query su JSON.
4. **Convenzione pivot**: da qui in avanti (`card_aspect`, `deck_cards`, `collection_cards`, Fase 3/4) usa `cards.cid` (già univoco) come riferimento alla carta, non la coppia composita `(expansion, number)`.
5. **Naming colonne in camelCase** (`frontArt`, `backArt`, `maxCopies`): rinomina in snake_case (`front_art`, `back_art`, `max_copies`), coerente con `email_verified_at`/`created_at` già presenti altrove. (`releaseDate` è gestita al punto 2, `mainExpansion` al punto 6 qui sotto — per quest'ultima la rinomina va di pari passo con un cambio di design, non è solo cosmetica.)
6. **`mainExpansion` — ridisegna come FK auto-referenziata, non sentinelle stringa**: mi avevi spiegato che serve distinguere tre stati (dato propedeutico per la futura apertura digitale dei booster pack), quindi una FK nullable semplice con `null = standalone` da sola non basta — perderebbe la distinzione tra "sono io l'espansione principale del gruppo" e "dipendo da un'altra". Soluzione che mantiene tutti e tre gli stati con una vera FK (integrità referenziale reale, niente più stringhe magiche `'-1'`/`'0'`):
   - rinomina in `group_main_expansion` (string, nullable, FK verso `expansions.expansion`, self-referenziabile)
   - `NULL` = espansione standalone (nessun gruppo)
   - valore = il proprio stesso codice espansione (self-reference) = questa espansione **è** la principale del gruppo
   - valore = codice di un'altra espansione = questa espansione dipende da quella (che sarà a sua volta auto-referenziata)

   Così ogni valore non nullo è verificabile con un vincolo FK reale (compreso il self-reference), e resta possibile risalire al gruppo di un'espansione con una query semplice (`WHERE group_main_expansion = (SELECT COALESCE(group_main_expansion, expansion) FROM expansions WHERE expansion = ?)`), utile proprio per capire quale pool di carte considerare quando implementerai l'apertura dei booster.
7. **`rotation` resta stringa** (ritiro la mia proposta precedente di convertirla in booleano — non lo è): indica a quale finestra di rotazione appartengono le carte (in Premier sono giocabili solo le ultime due rotazioni). Per i set principali è uniforme su tutta l'espansione; per alcuni set standalone/promo (con art alternative) può variare carta per carta. Per ora l'assegnazione resta manuale ad opera di un admin a livello di espansione (non serve ancora un override per singola carta), verificata tramite `confirmed` (indica se l'admin ha già controllato/corretto i dati). Se in futuro servirà granularità per singola carta nei set promo, si potrà aggiungere una colonna `rotation` opzionale anche su `cards` che sovrascrive quella dell'espansione solo dove necessario — non serve implementarlo ora.
8. **Aggiungi la FK** tra `cards.expansion` e `expansions.expansion` (`$table->foreign('expansion')->references('expansion')->on('expansions')`).
9. **Implementa `ImportCardsFromSwuApiJob::handle()`** (attualmente solo commenti-placeholder). Endpoint ufficiali SWU (confermati da `documentation.md` della vecchia versione):
```
GET https://admin.starwarsunlimited.com/api/card/{cid}?locale=it
    # dettaglio di una singola carta
GET https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][$null]=true&pagination[page]={page}&pagination[pageSize]=10
    # lista carte paginata (il filtro variantOf esclude le varianti, solo carte "base")
```
Struttura:
```php
public function handle(): void
{
    // 1. Http::get('https://admin.starwarsunlimited.com/api/card-list', [...]), gestendo la paginazione
    // 2. per ogni carta ricevuta, updateOrCreate su Card usando cid come chiave
    // 3. eventuali errori (riga malformata, campo mancante) -> registrali in system_errors (Fase 2bis) invece di interrompere l'intero scan
    // 4. dispaccia NotifyAdminJob con il riepilogo (nuove carte trovate, eventuali errori)
}
```
10. Pulizia minore: `ScanCards::$description` è ancora il testo di default di Artisan ("Command description").
11. **Copertura Pest per il job di import**: scrivi test che mockano la risposta HTTP dell'API SWU (`Http::fake()`) e verificano che `ImportCardsFromSwuApiJob` crei/aggiorni le carte correttamente, gestisca la paginazione e registri un `SystemError` sui dati malformati — questo è il modo corretto di verificare la logica di import (vedi anche Fase 2bis, dove si spiega perché non serve più una tabella `test_results`/`system_checks` separata).

Riferimento: https://laravel.com/docs/12.x/queues#creating-jobs

---

## Fase 2bis — Log errori scan (`system_errors`)

> Nella vecchia versione: `system_errors` registrava gli errori dello scan per poterli risolvere con calma; `test_results` salvava l'esito di controlli di integrità eseguiti ad ogni scan. **`test_results` non viene riportata**: ora che il progetto ha una suite Pest vera, la correttezza della logica di import va verificata lì (Step 2 punto 11), contro un database di test — non con controlli post-hoc sui dati di produzione, che i test Pest non toccano comunque. `system_errors` invece resta: serve per problemi reali durante uno scan reale (API down, dati inattesi), cosa che nessun test scritto in anticipo può coprire del tutto.

**Step 2bis.1 — Migration `system_errors`**
```
php artisan make:model SystemError -m
```
Colonne: `source` (string, es. nome della classe/job che ha generato l'errore), `message` (text), `context` (json, per dati aggiuntivi come il cid della carta che ha causato il problema), `resolved` (bool, default false), `resolved_at` (nullable timestamp), timestamps.

**Step 2bis.2 — Aggiungi il permesso**
Estendi `PermissionSeeder` (Fase 1) con `system.manage-errors`, così l'accesso alla pagina admin resta granulare come il resto del sistema permessi.

**Step 2bis.3 — Pagina admin**
Lista `system_errors` filtrabile per `resolved`, con azione per marcare come risolto. Protetta dal permesso dello step precedente (`Route::middleware(['auth', 'permission:system.manage-errors'])`).

**Step 2bis.4 — Integrazione con `ImportCardsFromSwuApiJob`**
Invece di lasciare che un'eccezione interrompa l'intero scan, cattura gli errori riga per riga e registra un `SystemError`, permettendo allo scan di continuare con le carte successive.

☐ Fase 2bis completata

---

## Fase 3 — Gestione mazzi multi-formato

**Step 3.1 — Enum formato mazzo**
Crea a mano `app/Enums/DeckFormat.php`:
```php
enum DeckFormat: string
{
    case Premier = 'premier';
    case Eternal = 'eternal';
    case TwinSuns = 'twin_suns';
}
```

**Step 3.2 — Migration `decks` e `deck_cards`**
```
php artisan make:model Deck -m
php artisan make:migration create_deck_cards_table
```
`decks`: `user_id`, `name`, `format` (string, castato a `DeckFormat`), `is_public` (bool), **`assembled`** (bool, default false — vedi Step 4.3), `version` (int, default 1), `previous_version_id` (nullable, self-FK su `decks.id`).

**Niente `leader_cid`/`base_cid` su `decks`**: cardinalità e vincoli di leader/base dipendono dal formato (Eternal/Premier: 1 leader + 1 base; Twin Suns: 2 leader + 1 base, con vincolo che i due leader non possono essere uno "bianco" e uno "nero" — regola di formato, non di schema), quindi due colonne fisse non reggono Twin Suns. Il ruolo della carta nel mazzo va invece in `deck_cards`:

`deck_cards`: `deck_id`, `cid` (FK verso `cards.cid`), `quantity`, **`role`** (string/enum: `leader`, `base`, `card`, default `card`).

Così un mazzo Eternal/Premier ha esattamente una riga con `role = leader` e una con `role = base`; un mazzo Twin Suns ne ha due con `role = leader` e una con `role = base` — la cardinalità e il vincolo sull'allineamento dei due leader li verifica il validator del formato (Step 3.3), non lo schema.

Sul versionamento: ogni volta che l'utente salva una nuova versione di un mazzo, crea una **nuova riga** in `decks` con `version` incrementato e `previous_version_id` che punta alla riga precedente — la catena delle versioni è così una relazione reale (self-FK), non un'inferenza basata sul nome del mazzo come nella vecchia versione (dove la collezione stessa era modellata come un mazzo speciale, distinto solo controllando se il nome conteneva la stringa "collezione" per decidere se applicare i limiti di formato — pattern fragile da non riportare). Per recuperare velocemente "l'ultima versione" di un mazzo, puoi aggiungere un indice/query che segue la catena `previous_version_id`, oppure un flag `is_current` da aggiornare quando crei una nuova versione (più comodo per le query, leggero da mantenere).

Nel modello `Deck`:
```php
protected $casts = [
    'format' => DeckFormat::class,
];
```
Riferimento: https://laravel.com/docs/12.x/eloquent-mutators#enum-casting

**Step 3.3 — Interfaccia e classi di validazione per formato**
```php
interface DeckFormatValidator
{
    public function validate(Deck $deck): array; // ritorna array di errori, vuoto se valido
}
```
Crea `PremierFormatValidator`, `EternalFormatValidator`, `TwinSunsFormatValidator` in `app/Services/DeckValidation/`, ciascuna con le proprie regole. Da controllare tramite `deck_cards` filtrando per `role`: numero di leader ammessi (1 per Eternal/Premier, 2 per Twin Suns), esattamente 1 base, e per Twin Suns il vincolo che i due leader condividano lo stesso allineamento (non uno "bianco" e uno "nero") — oltre alle regole generali di formato (limiti di copie per carta, ecc. — da definire in base al regolamento ufficiale).

**Step 3.4 — Factory per scegliere il validator giusto**
```php
class DeckFormatValidatorFactory
{
    public static function make(DeckFormat $format): DeckFormatValidator
    {
        return match ($format) {
            DeckFormat::Premier => new PremierFormatValidator(),
            DeckFormat::Eternal => new EternalFormatValidator(),
            DeckFormat::TwinSuns => new TwinSunsFormatValidator(),
        };
    }
}
```
Così per aggiungere un quarto formato in futuro aggiungi solo un case all'enum + una classe, senza toccare il resto.

**Step 3.5 — Policy per l'autorizzazione**
```
php artisan make:policy DeckPolicy --model=Deck
```
```php
public function update(User $user, Deck $deck): bool
{
    return $user->id === $deck->user_id || $user->can('decks.manage-any');
}
```
Riferimento: https://laravel.com/docs/12.x/authorization#creating-policies

**Step 3.6 — Export/Import mazzi**
Funzionalità della vecchia versione da riportare:
- **Export**: genera un file `.txt` (formato ufficiale SWU, compatibile con gli altri programmi/siti del gioco) e un `.json` (formato proprio, più semplice da re-importare qui) a partire da `deck_cards`
- **Import**: da file caricato (`.txt`/`.json`) o da URL esterno (altro sito SWUDB/UnlimitedDB) — valida il formato, verifica che ogni carta citata esista in `cards` (per espansione + numero), e riporta all'utente eventuali carte non trovate invece di fallire silenziosamente
- Vale la pena incapsulare export e import in classi dedicate (`DeckExporter`, `DeckImporter` in `app/Services/`) invece che nel controller, così restano testabili indipendentemente dalla request HTTP

☐ Fase 3 completata

---

## Fase 4 — Gestione collezione

**Step 4.1 — Migration `collection_cards`**
```
php artisan make:migration create_collection_cards_table
```
Colonne: `user_id`, `cid` (FK verso `cards.cid`), `variant` (string/enum: `normal`, `foil`, `hyper`, `prestige`), `quantity`. Il foil e le altre varianti di stampa vivono **solo qui**, non nei mazzi (nella vecchia versione la tabella `compositions` tracciava foil per riga di mazzo, ma era un effetto collaterale del fatto che la collezione fosse modellata come un mazzo speciale — vedi Step 3.2). Chiave univoca composita `(user_id, cid, variant)` così ogni combinazione utente/carta/variante ha una sola riga con la quantità posseduta.

**Step 4.2 — UI di gestione**
Pagina con ricerca carte (riusa i filtri della Fase 6) + bottone incrementa/decrementa quantità posseduta, salvato via una piccola interazione Alpine.js senza reload pagina.

**Step 4.3 — Funzione "carte mancanti per un mazzo"**
Quando l'utente vuole montare un mazzo, servono tre informazioni distinte:
1. **carte possedute** sufficienti (da `collection_cards`)
2. **carte mancanti** del tutto (non in collezione, o non in quantità sufficiente)
3. se le possedute non bastano: quante sono **possedute ma già impegnate in altri mazzi attualmente montati** (`decks.assembled = true`)

Logica di query, per un dato mazzo target:
```php
$required = $deck->deckCards; // cid => quantity richiesta
$owned = CollectionCard::where('user_id', $userId)->pluck('quantity', 'cid'); // cid => quantità posseduta totale
$reservedByOtherAssembledDecks = DeckCard::whereHas('deck', fn ($q) => $q->where('user_id', $userId)->where('assembled', true)->where('id', '!=', $deck->id))
    ->selectRaw('cid, SUM(quantity) as qty')
    ->groupBy('cid')
    ->pluck('qty', 'cid');

// per ogni cid richiesto:
// disponibile_libera = owned[cid] - reservedByOtherAssembledDecks[cid]
// mancante_del_tutto = max(0, required[cid] - owned[cid])
// posseduta_ma_impegnata = max(0, min(required[cid], owned[cid]) - disponibile_libera) quando disponibile_libera < required[cid]
```
Così l'utente vede subito se gli conviene comprare carte mancanti oppure smontare un altro mazzo per liberarle.

☐ Fase 4 completata

---

## Fase 5 — Bot Telegram

**Step 5.1 — Libreria per l'API Telegram**
Usa direttamente la facade `Http` nativa di Laravel (https://laravel.com/docs/12.x/http-client) per chiamare l'API Telegram, senza aggiungere una libreria esterna dedicata. La vecchia versione (SWUDB) usava il pacchetto `telegram-bot/api`, ma l'ecosistema dei wrapper PHP per Telegram di quella fascia è in gran parte poco mantenuto o esplicitamente abbandonato (es. `vjik/telegram-bot-api`, deprecato dallo stesso autore) — per un bot con poche funzioni (scan, ricerca, notifica admin) non c'è un vero vantaggio nell'aggiungere quella dipendenza, mentre con `Http` hai pieno controllo e zero rischio di dover rimpiazzare un pacchetto abbandonato in futuro.

**Step 5.1bis — Crea `TelegramService`**
Incapsula tutte le chiamate all'API Telegram in `app/Services/TelegramService.php`, invece di sparpagliare `Http::post(...)` nei vari punti che parlano col bot (webhook, notifiche admin, ricerca) — un unico posto da aggiornare se cambia qualcosa nell'API, e ogni metodo restituisce un risultato tipizzato invece di un array grezzo. Esempio di struttura:
```php
final readonly class TelegramActionResult
{
    public function __construct(
        public bool $successful,
        public ?int $messageId = null,
        public ?string $errorDescription = null,
        public array $raw = [],
    ) {}
}

class TelegramService
{
    public function sendMessage(int|string $chatId, string $text, array $options = []): TelegramActionResult { /* ... */ }

    public function sendPhoto(int|string $chatId, string $photoUrl, string $caption = '', array $options = []): TelegramActionResult { /* ... */ }

    public function editMessage(int|string $chatId, int $messageId, string $text): TelegramActionResult { /* ... */ }

    public function deleteMessage(int|string $chatId, int $messageId): TelegramActionResult { /* ... */ }
}
```
Ogni metodo chiama l'endpoint Telegram corrispondente (`sendMessage`, `sendPhoto`, `editMessageText`, `deleteMessage`) e traduce la risposta JSON di Telegram (`{"ok": true/false, "result": {...}, "description": "..."}`) in un `TelegramActionResult` — così chi chiama il servizio non deve mai leggere l'array grezzo di Telegram per sapere se l'operazione è andata a buon fine. Aggiungi altri metodi (es. `pinMessage`, `answerCallbackQuery`) man mano che ti servono, stessa struttura.

**Step 5.2 — Webhook controller**
```
php artisan make:controller TelegramController
```
Riceve gli update di Telegram via webhook, instrada in base al comando (`/scan`, `/search <query>`), usando `TelegramService` per rispondere.

**Step 5.3 — Comando `/scan` dal bot**
Nel metodo che gestisce `/scan`, richiama la stessa logica della Fase 2:
```php
Artisan::call('cards:scan');
```
oppure dispaccia direttamente `ImportCardsFromSwuApiJob::dispatch()` — nessuna logica duplicata rispetto allo scan schedulato.

**Step 5.4 — Comando `/search`**
Query su `Card` (nome IT/EN, `LIKE` o full-text se il volume di carte lo giustifica). Risposta: prova prima `TelegramService::sendPhoto()` con l'immagine della carta (`image_url`) e didascalia (nome, espansione, testo carta); se l'invio della foto fallisce (es. URL immagine non raggiungibile, `TelegramActionResult::$successful === false`), fai fallback su `TelegramService::sendMessage()` con un messaggio di testo contenente il link alla pagina della carta sul sito.

**Step 5.5 — Job di notifica admin**
```
php artisan make:job NotifyAdminJob
```
```php
public function handle(TelegramService $telegram): void
{
    $telegram->sendMessage(config('services.telegram.admin_chat_id'), $this->message);
}
```
Richiamato da `ImportCardsFromSwuApiJob` a fine scan e da qualunque altro evento critico vorrai monitorare.

☐ Fase 5 completata

---

## Fase 6 — UI/UX e funzioni comuni TCG

**Step 6.1 — Ricerca/filtri carte**
Ricerca **server-side pura**: form con filtri (espansione, aspetto, tipo, costo, testo libero) inviati via `GET`, il controller applica i filtri con `when()` su una query Eloquent e ritorna le carte compatibili — niente ricerca live/Alpine qui, ogni ricerca è un normale caricamento di pagina con i filtri in query string. Incapsula la costruzione della query in una classe dedicata (es. `app/Services/CardSearch.php`, con un metodo tipo `apply(Builder $query, array $filters): Builder`), perché la Fase 6bis userà la stessa identica logica per l'endpoint API di ricerca — un solo posto da mantenere per i filtri disponibili.

**Step 6.2 — Statistiche mazzo**
Pagina che mostra, per un mazzo: curva dei costi (grafico semplice), distribuzione per aspetto/tipo.

**Step 6.3 — Separazione viste pubbliche/autenticate**
Definisci chiaramente nelle rotte quali sono accessibili senza login (catalogo, mazzi pubblici) e quali richiedono `auth` (creare/modificare mazzi, collezione).

**Step 6.4 — Pagina "Nuove uscite"**
Elenco delle carte uscite più di recente, con filtro data:
- rotta tipo `GET /nuove-uscite`, parametro query opzionale `since` (`YYYY-MM-DD`) — quando specificato resta un intervallo **arbitrario**, a scelta dell'utente (mostra tutte le carte con `release_date >= since`, qualunque data scelga)
- se `since` **non** è passato: il default non è un intervallo fisso arbitrario (es. "ultimi 30 giorni"), ma l'ultimo gruppo di carte pubblicate, cioè tutte le carte con `release_date` uguale alla data più recente presente in `cards` (`Card::max('release_date')`)
- query di esempio (richiede prima il fix del punto 2 della Fase 2 — `cards.release_date` come vera colonna data):
```php
$since = $request->query('since') ?? Card::max('release_date');
Card::where('release_date', '>=', $since)->orderByDesc('release_date')->get();
```
Nota: filtra direttamente su `cards.release_date`, non tramite `expansions.legal_date` — sono due date diverse e la pagina "nuove uscite" riguarda l'uscita della singola carta, non la legalità dell'espansione.
- interfaccia: un semplice `<input type="date">` in un form GET che ricarica la pagina con `?since=...` in query string — nessun bisogno di Alpine.js per questa parte, è un filtro server-side

Riferimento: https://laravel.com/docs/12.x/queries#where-clauses (filtro data) e https://laravel.com/docs/12.x/eloquent-relationships#one-to-many (relazione Card–Expansion, da definire nei modelli — al momento entrambi `Card` e `Expansion` sono ancora modelli vuoti)

☐ Fase 6 completata

---

## Fase 6bis — API REST pubblica

> Riprende l'API della vecchia versione (endpoint carta singola, carte per espansione, ricerca mazzi) per sviluppatori terzi, con autenticazione Sanctum invece che aperta. **Principio generale**: dove possibile, le rotte API condividono lo stesso backend delle pagine UI (stessi filtri, stessa classe di query, es. `CardSearch` dello Step 6.1) e cambiano solo il formato di output (view Blade vs API Resource JSON) — così eviti di dover mantenere due implementazioni parallele della stessa logica di ricerca/filtro.

**Step 6bis.1 — Installa Sanctum**
```
composer require laravel/sanctum
php artisan install:api
```
Riferimento: https://laravel.com/docs/12.x/sanctum

**Step 6bis.2 — Endpoint pubblici di sola lettura**
In `routes/api.php`:
```php
Route::get('/cards/{expansion}/{number}', [Api\CardController::class, 'show']);
Route::get('/cards/search', [Api\CardController::class, 'search']); // stessi filtri della pagina di ricerca (Step 6.1)
Route::get('/decks/{user}/{name}', [Api\DeckController::class, 'show']); // solo mazzi pubblici
```
`Api\CardController::search()` riusa la stessa classe `CardSearch` del controller web (Step 6.1): stessi parametri, stesso comportamento, solo la risposta cambia (`CardResource::collection(...)` invece di una view).
Questi endpoint non richiedono autenticazione (dati pubblici, già leggibili dal sito) — valuta comunque il rate limiting nativo di Laravel (`throttle:60,1` sul gruppo di rotte) per prevenire abusi.
Riferimento: https://laravel.com/docs/12.x/routing#rate-limiting

**Step 6bis.3 — Endpoint autenticati (se in futuro servono azioni, non solo letture)**
Usa i token Sanctum (`$user->createToken('nome-token')`) per endpoint che modificano dati (es. sincronizzare la propria collezione da un'app esterna) — non necessario al day 1 se l'API resta di sola consultazione.

**Step 6bis.4 — Risorse API (formato risposta)**
```
php artisan make:resource CardResource
```
Usa gli [API Resources](https://laravel.com/docs/12.x/eloquent-resources) di Laravel per controllare esattamente cosa esporre (es. non esporre colonne interne come `id` se usi `cid` come chiave pubblica), invece di restituire i modelli Eloquent grezzi.

☐ Fase 6bis completata

---

## Fase 7 — Deploy

**Step 7.1 — Lancia `~/scripts/new-site.sh` sul server**
Non serve scrivere a mano Dockerfile/docker-compose.yml/nginx conf di produzione: lo script li genera lui (vedi Fase 0bis) a partire dal repo che gli indichi, con dominio `unlimiteddb.mandich.dev`. Segui il flusso interattivo dello script (repo, `.env`, sottodominio, subnet assegnata in automatico, secrets GitHub, import DB opzionale).

**Step 7.2 — Servizio queue worker (non generato dallo script)**
Lo script non crea un servizio queue worker: aggiungilo tu nel `docker-compose.yml` generato (o modifica lo script per includerlo di default nei prossimi siti), con `php artisan queue:work --tries=3` in loop, oppure Supervisor nello stesso container applicativo — deve restare sempre attivo, a differenza del container web che risponde solo alle richieste HTTP.

**Step 7.3 — Scheduler (non generato dallo script)**
Allo stesso modo, assicurati che un vero cron di sistema (nel container o sull'host) lanci `php artisan schedule:run` ogni minuto — è il meccanismo con cui Laravel esegue poi `cards:scan` alla frequenza configurata nella Fase 2. Anche questo va aggiunto a mano, lo script attuale non lo prevede.

**Step 7.4 — Verifica post-deploy**
- il webhook Telegram punta al dominio giusto
- il queue worker sta effettivamente consumando i job (controlla `failed_jobs` per errori)
- lo scan schedulato parte al lunedì a mezzanotte come da requisito

☐ Fase 7 completata

---

## Note di analisi (perché queste scelte)

- **Queue reali invece di fireAndForget**: il vecchio sistema simulava thread con richieste HTTP POST ricorsive per aggirare l'assenza di code su Altervista — fragile, senza retry strutturato, errori persi nella risposta scartata. Le queue di Laravel danno retry/backoff/failed-jobs nativi. https://laravel.com/docs/12.x/queues
- **Permessi granulari (Spatie)**: permessi singoli assegnabili liberamente, i "ruoli" sono solo scorciatoie per assegnarne un gruppo insieme, non autorità hardcoded nel codice. https://spatie.be/docs/laravel-permission/v6/introduction
- **Enum + Strategy per i formati mazzo**: evita `if/else` sparsi, aggiungere un formato futuro richiede solo una nuova classe, non modifiche al codice esistente.
- **Blade + Alpine.js invece di Livewire**: nella vecchia versione la lentezza percepita era dovuta a un bug architetturale preciso (il componente `DeckManager` teneva l'intero catalogo carte come proprietà pubblica, e Livewire re-invia ogni proprietà pubblica ad ogni interazione), non a un limite del framework in sé — ma Blade+Alpine evita il rischio per design, senza dover stare attenti a questo tipo di errore.
- **`.env-overrides`**: pattern già in uso in SWUDB per tenere in Git (a differenza di `.env`) l'`APP_VERSION`, utile per riconoscere subito quale versione sia effettivamente in produzione.
- **Verifica email nativa invece di sistema custom**: la vecchia versione aveva un meccanismo fatto a mano (token 60 caratteri, metodi ad-hoc); Breeze/Laravel offrono lo stesso risultato con `MustVerifyEmail` + middleware `verified`, meno codice da mantenere.
- **`system_errors` mantenuta, `test_results` no**: la prima logga problemi reali durante uno scan reale (cosa che nessun test scritto in anticipo può coprire del tutto); la seconda verificava la correttezza della logica di import, compito che ora spetta alla suite Pest (test contro dati controllati, non contro la produzione).

## Riferimenti documentazione Laravel 12

| Argomento | Link |
|---|---|
| Queue | https://laravel.com/docs/12.x/queues |
| Scheduling | https://laravel.com/docs/12.x/scheduling |
| Autenticazione | https://laravel.com/docs/12.x/authentication |
| Autorizzazione (Gates/Policies) | https://laravel.com/docs/12.x/authorization |
| Migrations | https://laravel.com/docs/12.x/migrations |
| Eloquent relationships | https://laravel.com/docs/12.x/eloquent-relationships |
| Eloquent casting (enum) | https://laravel.com/docs/12.x/eloquent-mutators#enum-casting |
| Validazione | https://laravel.com/docs/12.x/validation |
| HTTP Client | https://laravel.com/docs/12.x/http-client |
| Testing (Pest) | https://laravel.com/docs/12.x/testing |
| Spatie Laravel-permission | https://spatie.be/docs/laravel-permission/v6/introduction |
| Verifica email | https://laravel.com/docs/12.x/verification |
| Sanctum (API auth) | https://laravel.com/docs/12.x/sanctum |
| API Resources | https://laravel.com/docs/12.x/eloquent-resources |
| Rate limiting rotte | https://laravel.com/docs/12.x/routing#rate-limiting |
