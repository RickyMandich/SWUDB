# Implementation Plan — Ricostruzione UnlimitedDB (esercizio guidato)

> Guida passo-passo da eseguire manualmente. Le fasi già completate sono riassunte in breve; le fasi/step da fare restano nel dettaglio completo: modelli, migration (con ogni colonna e il motivo), pagine, job/service.
>
> Ambiente: `C:\Users\RickyMandich\PROJECT\unlimiteddb`, Laravel 12.12, PHP 8.2.29 (cmd.exe), MariaDB, Pest, Breeze, deploy Docker+Traefik su VM Oracle (`*.mandich.dev`).
>
> **Convenzione di codice**: PHPDoc bilingue (EN tecnico + IT descrittivo) su ogni metodo non ovvio:
> ```php
> /**
>  * English technical description of the method
>  * Descrizione italiana "alla buona" del metodo
>  *
>  * @param Type $parameter Description of parameter
>  * @return ReturnType Description of return value
>  */
> ```
>

---

## ✅ Fase 1 — Setup progetto (completata)
Breeze e `spatie/laravel-permission` installati; `.env` configurato (MariaDB, `QUEUE_CONNECTION=database`); tabelle `jobs`/`failed_jobs` e tabelle Spatie migrate; `.env-overrides` agganciato in `bootstrap/app.php`.

## ✅ Fase 2 — Ambiente Docker locale (completata)
`.dockerignore`/`.gitignore` con esclusione `bootstrap/cache/*.php`; `Dockerfile`, `entrypoint.sh`, nginx conf, `init.sql`, `docker-compose.dev.yml` allineati a quanto genera `new-site.sh` in produzione; verificato su `http://localhost:66`.

---

## Schema del database (riferimento per tutte le fasi seguenti)

> Questa sezione raccoglie **tutte** le tabelle applicative decise finora, con ogni colonna e il perché. Le fasi sotto rimandano qui invece di ripetere lo schema.

### `expansions`
| Colonna | Tipo | Note |
|---|---|---|
| `expansion` | string, **PK** | Codice naturale (es. `SOR`, `SHD`). Chiave primaria naturale, non un id surrogato: è già la chiave con cui gioco/community/API riconoscono l'espansione — un id numerico sarebbe una duplicazione senza vantaggi. |
| `legal_date` | date, nullable | Da quando le carte dell'espansione sono legali in torneo (diverso da `cards.release_date`, vedi sotto). |
| `rotation` | string | Etichetta della finestra di rotazione (Premier ammette solo le ultime due). Stringa perché è un'etichetta, non un booleano. |
| `confirmed` | boolean, default `false` | Un admin ha verificato/corretto i dati di questa espansione (vedi Fase 6). |
| `group_main_expansion` | string, nullable, **self-FK** su `expansions.expansion` | `null` = standalone; valore uguale al proprio codice = è lei la principale del gruppo; altro codice = dipende da quella. Propedeutico alla futura apertura digitale dei booster. |
| `created_at`/`updated_at` | timestamp | |

**Nota token**: nel vecchio sistema i segnalini (token) di un'espansione vivono sotto un codice con prefisso `T` (es. token di `SOR` → espansione `TSOR`), non sotto il codice dell'espansione originale. Vanno trattate come righe `expansions` a sé stanti — tienilo a mente quando scrivi l'import (Fase 4) e i filtri (Step 10.1), altrimenti "espansione" e "token di quell'espansione" si confondono nelle liste.

### `cards`
| Colonna | Tipo | Note |
|---|---|---|
| `expansion` | string, FK → `expansions.expansion`, **PK composita** con `number` | Riflette come le carte sono identificate nel gioco stesso (numero all'interno del set). |
| `number` | unsigned integer, **PK composita** | |
| `cid` | string, **unique** | Id naturale della carta secondo l'API ufficiale — usalo come riferimento nelle tabelle pivot (`card_aspect`, `deck_cards`, `collection_cards`) invece della coppia composita, molto più semplice nelle join. |
| `unique_card` | boolean, default `false` | Rinominata da `unica` (evita ambiguità col termine "unique" usato anche per il vincolo SQL sulla colonna `cid`). Indica la regola "Unica" del gioco (una sola copia in gioco nello stesso momento). |
| `name` | string | Nome della carta. |
| `title` | string, nullable | Sottotitolo carta. |
| `type` | string | Tipo di carta: "unita" o "evento". |
| `rarity` | string | Rarity della carta: "comune", "non comune", "rara", "leggendaria". |
| `cost` | unsigned tinyint, nullable | Costo totale della carta in risorse per essere giocata. |
| `health` | unsigned tinyint, nullable | Punti ferita della carta, presente solo se è un'unità. |
| `power` | unsigned tinyint, nullable | Forza della carta, presente solo se è un'unità. |
| `text` | text | Testo delle abilità della carta. |
| `traits` | string, nullable | Se in futuro ti serve filtrare per singolo tratto, valuta di normalizzarla come per gli aspetti (tabella + pivot) — per ora stringa libera, non è stato chiesto. |
| `arena` | string, nullable | Se è un'unità, l'arena in cui viene giocata. |
| `artist` | string, nullable | Artista che ha realizzato l'illustrazione della carta. |
| `front_art_path`, `back_art_path` | string, nullable | Path **relativo** nel disk `public` di Laravel (fisicamente `storage/app/public/...`), es. `cards/{expansion}/{number}-front.{ext}` — non l'URL diretto dell'API ufficiale: le immagini vengono scaricate in locale durante l'import (Step 4.6), così il sito non dipende dalla disponibilità del CDN ufficiale a runtime. L'estensione `{ext}` si determina al momento del download (content-type), non è detto sia sempre `.png`. |
| `max_copies` | unsigned tinyint, nullable, default `null` | Quante copie di questa carta il giocatore può avere in un deck (valorizzato solo se non è il valore standard). |
| `release_date` | date, nullable | Quando **questa carta** è uscita al pubblico — diverso da `expansions.legal_date` (uscita di una carta vs legalità di un'intera espansione), serve per la pagina "Nuove uscite" (Step 10.4). |
| `created_at`/`updated_at` | timestamp | `updated_at` utile per capire quando una carta è stata corretta dall'ultimo scan. |

### `aspects` + `card_aspect` (pivot)
`aspects`: `id`, `name`, `color`, `slug`, `order` (per l'ordinamento in UI), timestamps.
`card_aspect`: `cid` (FK `cards.cid`), `aspect_id` (FK `aspects.id`).
Tabella dedicata invece di una colonna `json` su `cards`: permette di filtrare per aspetto con una join indicizzata e centralizza colore/slug/ordine per la UI in un unico posto.

### `decks`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint, PK auto-increment | Qui un id surrogato ha senso: un mazzo non ha un codice naturale stabile come le carte/espansioni. |
| `user_id` | FK `users.id` | |
| `name` | string | |
| `format` | string, cast a `DeckFormat` enum | `premier`/`eternal`/`twin_suns`. |
| `is_public` | boolean, default `false` | |
| `assembled` | boolean, default `false` | Se il mazzo è "montato" fisicamente ora — serve al calcolo delle carte impegnate altrove (Step 8.3). |
| `version` | unsigned integer, default `1` | |
| `previous_version_id` | nullable, self-FK su `decks.id` | Catena reale delle versioni (self-FK), non un'inferenza sul nome come nella vecchia versione. |
| `created_at`/`updated_at` | timestamp | |

**Niente `leader_cid`/`base_cid` qui**: la cardinalità di leader/base dipende dal formato (Eternal/Premier: 1+1; Twin Suns: 2 leader+1 base, con vincolo di allineamento tra i due leader) — vedi `deck_cards.role` sotto.

### `deck_cards`
`deck_id` (FK `decks.id`), `cid` (FK `cards.cid`), `quantity` (unsigned tinyint), `role` (string: `leader`/`base`/`card`, default `card`).
Un mazzo Eternal/Premier ha una riga `role=leader` e una `role=base`; Twin Suns ne ha due `role=leader` e una `role=base`. La cardinalità e il vincolo sull'allineamento li verifica il `DeckFormatValidator` del formato (Step 7.3), non lo schema.

### `collection_cards`
`user_id` (FK `users.id`), `cid` (FK `cards.cid`), `variant` (enum: `normal`, `foil`, `hyper`, `prestige`, `hyper_foil`, default `normal`), `quantity` (unsigned smallint). Chiave univoca composita `(user_id, cid, variant)`. Le varianti di stampa vivono **solo qui**, non nei mazzi (vedi discussione sulla vecchia `compositions`).

### `system_errors`
| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint, PK | |
| `source` | string | Classe/job che ha generato l'errore. |
| `message` | text | Motivo specifico (es. "carta {cid} già presente", "campo `cost` mancante nella risposta API") — nella vecchia versione questo dettaglio finiva anche nella mail agli admin, mantienilo. |
| `stack_trace` | text, nullable | Stack trace completo dell'errore. |
| `context` | json, nullable | Dati aggiuntivi (es. payload della carta che ha causato il problema). |
| `status` | string: `open`/`resolved`/`ignored`, default `open` | **Tre stati, non un booleano**: la vecchia versione aveva sia "segna come risolto" che "segna come ignorato" (`todo.md`) — un semplice `resolved` booleano perderebbe la distinzione tra "sistemato" e "non è un problema, ignoralo". |
| `resolved_at` | nullable timestamp | |
| `created_at`/`updated_at` | timestamp | |

---

## Fase 3 — Autenticazione e permessi

### ✅ Fatto
Breeze (Blade) installato; `HasRoles` su `User`; `PermissionSeeder` con `cards.import`, `cards.manage`, `decks.manage-any`, `collections.manage-any`, `users.manage`, `bot.notifications.receive`, ruolo `admin`.

### 🔧 Da fare

**Step 3.1 — Verifica email nativa**
```php
// app/Models/User.php
use Illuminate\Contracts\Auth\MustVerifyEmail;
class User extends Authenticatable implements MustVerifyEmail {}
```
```php
Route::middleware(['auth', 'verified'])->group(function () { /* rotte che richiedono email confermata */ });
```
Breeze genera già viste/rotte di verifica. Riferimento: https://laravel.com/docs/12.x/verification

**Step 3.2 — Pagina admin gestione utenti**
Lista utenti con permesso `users.manage`: assegna/revoca permessi e ruoli (usa i metodi di Spatie `assignRole`/`givePermissionTo`/`revokePermissionTo`), coerente con "miglioramento pagina utenti per la gestione di admin" della vecchia versione.

☐ Fase 3 completata

---

## Fase 3.5 — Configurazione email transazionale (Resend)

> **Contesto** (da `mandich-dev-infra`): Resend scelto come provider per tutti i siti `*.mandich.dev` al posto di un setup self-hosted, perché il piano gratuito Oracle Cloud blocca la porta 25 in uscita (sblocco riservato ai piani a pagamento). **Obiettivo di questa fase**: un solo dominio verificato su Resend (`mandich.dev`, non un sottodominio per sito) condiviso da tutti i siti, per restare nel piano gratuito Resend senza dover creare un account/dominio separato per ognuno. Questa fase precede l'uso delle mail già previsto in Fase 4 (Step 4.5): senza un mailer configurato, `NewCardsEmail`/`AdminScanReportEmail` finirebbero solo nei log (`MAIL_MAILER=log` attuale).

**Step 3.5.1 — Verifica dominio `mandich.dev` su Resend (una tantum, condivisa tra tutti i siti)**
- Su [resend.com/domains](https://resend.com/domains) aggiungi il dominio **`mandich.dev`** (l'apice, non `unlimiteddb.mandich.dev`) — se è già stato verificato per un altro sito della VM, salta questo step: la verifica vale per l'intero dominio, ogni sito potrà inviare da qualsiasi indirizzo `@mandich.dev` senza registrarsi di nuovo.
- Resend genera i record DNS da aggiungere (tipicamente: 1 TXT per SPF, 2-3 CNAME/TXT per DKIM, opzionale TXT per DMARC) — vanno creati su Cloudflare, dove è già gestito il DNS di `mandich.dev` (stesso posto del record wildcard usato da Traefik).
- Attendi la verifica (di norma minuti, fino a 72h): la dashboard segna il dominio come "Verified" prima di poter inviare.

**Step 3.5.2 — API Key Resend**
- Se non esiste già una key riutilizzabile per i siti `*.mandich.dev`, creane una in [resend.com/api-keys](https://resend.com/api-keys) con permesso **"Sending access"** (non serve full access), eventualmente ristretta al dominio `mandich.dev`.
- Salvala solo nel gestore password / negli `.env` dei singoli ambienti — non versionarla mai (né in `.env-overrides`, che è tracciato in Git).

**Step 3.5.3 — Pacchetto Resend per Laravel**
```
composer require resend/resend-php
```
`config/mail.php` (mailer `resend` con `'transport' => 'resend'`) e `config/services.php` (`'resend' => ['key' => env('RESEND_API_KEY')]`) sono già presenti nello scaffold Laravel 12 di questo progetto — nessuna modifica di codice necessaria oltre all'installazione del pacchetto.

**Step 3.5.4 — Variabili d'ambiente (locale e produzione)**
In locale (`.env`) e sul server (`.env` del sito su `~/sites/SWUDB/.env`, dato che `new-site.sh` legge/crea il `.env` direttamente sulla VM e non lo committa):
```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxx
MAIL_FROM_ADDRESS="unlimiteddb@mandich.dev"
MAIL_FROM_NAME="UnlimitedDB"
```
Scegli un indirizzo `MAIL_FROM_ADDRESS` specifico per il sito, così chi riceve la mail capisce subito il mittente (es. `unlimiteddb@mandich.dev`, non un indirizzo generico condiviso tipo `noreply@mandich.dev`) — con il dominio verificato basta questo, non serve ulteriore configurazione Resend per usare indirizzi diversi da sito a sito. Le variabili `MAIL_HOST`/`MAIL_PORT`/`MAIL_USERNAME`/`MAIL_PASSWORD`/`MAIL_SCHEME` restano in `.env.example` come riferimento per lo sviluppo locale (mailpit/log), ma non servono più con `resend`. Dopo aver aggiornato il `.env` sul server, riavvia il container app (`SWUDB_app`) perché rilegga le variabili.

**Step 3.5.5 — Verifica invio**
```
php artisan tinker
>>> Mail::raw('Test invio da UnlimitedDB', fn ($m) => $m->to('tuamail@esempio.com')->subject('Test Resend'));
```
Controlla l'esito sia nella dashboard Resend ([resend.com/emails](https://resend.com/emails), log di invio con stato delivered/bounced) sia nella casella di posta di destinazione.

☐ Fase 3.5 completata

---

## Fase 4 — Catalogo carte e import via queue

### ✅ Fatto
Modelli `Expansion`/`Card`; migration `expansions`/`cards`; `ImportCardsFromSwuApiJob` (scheletro); comando `cards:scan`; `Schedule::command(...)` in `routes/console.php`; worker testato in locale.

### 🔧 Da fare

**Step 4.1 — Bug bloccante**
Manca `use Illuminate\Support\Facades\Schedule;` in `routes/console.php`.

**Step 4.2 — Applica lo schema `cards`/`expansions`**
Come definito sopra: rinomina camelCase→snake_case, `release_date`/`legal_date` distinti, `group_main_expansion`, FK `cards.expansion → expansions.expansion`.

**Step 4.3 — Aspetti**
Crea `Aspect` (`php artisan make:model Aspect -m`) e la pivot `card_aspect` (`php artisan make:migration create_card_aspect_table`).

**Step 4.4 — Relazioni nei modelli**
`Card::aspects()` (belongsToMany), `Card::expansionModel()` (belongsTo, o rinomina la relazione per non confliggere con la colonna `expansion`), `Expansion::cards()` (hasMany), `Expansion::groupMainExpansion()`/`dependentExpansions()` (self-relations su `group_main_expansion`).

**Step 4.5 — `ImportCardsFromSwuApiJob`**
Endpoint ufficiali (da `documentation.md`/`todo.md` della vecchia versione):
```
GET https://admin.starwarsunlimited.com/api/card/{cid}?locale=it
GET https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][$null]=true&pagination[page]={page}&pagination[pageSize]=10
```
Requisiti raccolti da `todo.md` (vecchia versione, da riportare):
- **un solo messaggio Telegram per scan**, aggiornato nel tempo con `TelegramService::editMessage()` (Fase 9) invece di spammare un messaggio per evento — crea il messaggio a inizio scan (`sendMessage`, salva il `messageId`), aggiornalo con `editMessage` ad ogni fase/pagina processata, chiudilo con il riepilogo finale
- **verifica che la carta non sia già presente** prima di considerarla "nuova" (per l'email agli utenti, punto sotto)
- a fine scan: **email a tutti gli utenti** con le carte aggiunte in questo scan (Mailable `NewCardsEmail`, coda `ShouldQueue` per non bloccare il job); **email agli admin** con le carte che hanno lanciato errori o erano già presenti, motivo specifico incluso (usa `system_errors`, Fase 5)
- ogni riga fallita → `SystemError::create([...])` invece di interrompere l'intero scan (fail-soft)

```php
public function handle(TelegramService $telegram): void
{
    $progressMessage = $telegram->sendMessage($adminChatId, 'Scan avviato...');
    // per ogni pagina dell'API:
    //   Http::get(...) -> per ogni carta: updateOrCreate su Card per cid, traccia se era nuova
    //   in caso di errore riga per riga: SystemError::create(...), continua
    //   $telegram->editMessage($adminChatId, $progressMessage->messageId, "Pagina X/Y...")
    // a fine job: Mail::to(User::all())->queue(new NewCardsEmail($nuoveCarte));
    //             Mail::to($admins)->queue(new AdminScanReportEmail($errori));
    //             $telegram->editMessage($adminChatId, $progressMessage->messageId, "Scan completato: riepilogo...");
}
```
Riferimento: https://laravel.com/docs/12.x/queues#creating-jobs, https://laravel.com/docs/12.x/mail

**Step 4.6 — Download locale delle immagini carta**
Invece di salvare l'URL dell'API in `front_art_path`/`back_art_path`, scarica l'immagine e salva il path locale:

1. `php artisan storage:link` (una tantum) — crea il symlink `public/storage` verso `storage/app/public`, necessario per rendere le immagini raggiungibili via browser.
2. Crea `app/Services/CardImageDownloader.php`:
```php
class CardImageDownloader
{
    /**
     * Downloads a card image from the given URL and stores it on the public disk
     * Scarica l'immagine di una carta dall'URL indicato e la salva sul disk pubblico
     *
     * @param string $sourceUrl URL originale dell'immagine (dall'API SWU)
     * @param string $expansion Codice espansione, per il path di destinazione
     * @param int $number Numero carta, per il path di destinazione
     * @param string $side 'front' o 'back', per differenziare il nome file
     * @return string|null Path relativo salvato (es. "cards/SOR/001-front.webp"), null se il download fallisce
     */
    public function download(string $sourceUrl, string $expansion, int $number, string $side): ?string
    {
        // 1. Http::get($sourceUrl) -> se fallisce, ritorna null (il chiamante logga il SystemError)
        // 2. determina l'estensione dal Content-Type della risposta (es. image/webp -> .webp), non dall'URL
        // 3. $path = "cards/{$expansion}/{$number}-{$side}.{$ext}"
        // 4. Storage::disk('public')->put($path, $response->body())
        // 5. return $path
    }
}
```
3. Nel job di import (Step 4.5), per ogni carta: se non esiste già un file a quel path (evita ri-download inutili ad ogni scan settimanale, le immagini di una carta pubblicata non cambiano), chiama `CardImageDownloader::download()` per front e back; se il download fallisce, registra un `SystemError` (`source: CardImageDownloader`) e lascia il campo `null`/il valore precedente invece di far fallire l'intera riga.
4. Per mostrare l'immagine in una view: `Storage::disk('public')->url($card->front_art_path)` (o l'helper `asset('storage/'.$card->front_art_path)`).
5. Aggiungi a `.gitignore`: `/storage/app/public/cards` — sono file scaricabili di nuovo da un nuovo scan, non ha senso versionarli (e sarebbero comunque tanti file binari).

Riferimento: https://laravel.com/docs/12.x/filesystem

**Step 4.7 — Copertura Pest**
`Http::fake()` per mockare le risposte API; verifica creazione/aggiornamento carte, gestione paginazione, registrazione `SystemError` su dati malformati, invio delle due email. Questa è la verifica di correttezza della logica di import, contro dati controllati — non un controllo post-hoc sulla produzione.

☐ Fase 4 completata

---

## Fase 5 — Log errori scan (`system_errors`)

**Step 5.1 — Migration e modello**
Schema in cima al documento. `php artisan make:model SystemError -m`.

**Step 5.2 — Permesso**
Estendi `PermissionSeeder`: `system.manage-errors`.

**Step 5.3 — Pagina admin `/admin/errori`**
- lista filtrabile per `status` (`open`/`resolved`/`ignored`)
- pulsanti riga-per-riga "segna come risolto" / "segna come ignorato"
- **selezione multipla** con azione bulk per assegnare uno stato a più errori insieme (richiesto esplicitamente in `todo.md`)
- vista di dettaglio per singolo errore (mostra `context` formattato)
- link diretto a un errore dalla mail di notifica agli admin (Step 4.5) — serve una rotta tipo `/admin/errori/{systemError}` a cui puntare

Protetta da `Route::middleware(['auth', 'permission:system.manage-errors'])`.

☐ Fase 5 completata

---

## Fase 6 — Admin: gestione espansioni e rotazioni

> Dalla vecchia versione (`todo.md`): "creare pagina admin di gestione espansioni e rotazioni". Necessaria perché `rotation`/`legal_date`/`confirmed`/`group_main_expansion` sono dati a cura manuale dell'admin (schema `expansions`).

**Step 6.1 — Permesso**
Estendi `PermissionSeeder`: `expansions.manage`.

**Step 6.2 — Pagina admin `/admin/espansioni`**
Lista tutte le `expansions` (comprese quelle token `T*`, vedi nota nello schema) con form di modifica per `legal_date`, `rotation`, `group_main_expansion`, e checkbox `confirmed` per marcare i dati come verificati. Protetta da `permission:expansions.manage`.

☐ Fase 6 completata

---

## Fase 7 — Gestione mazzi multi-formato

**Step 7.1 — Enum `DeckFormat`**
```php
enum DeckFormat: string
{
    case Premier = 'premier';
    case Eternal = 'eternal';
    case TwinSuns = 'twin_suns';
}
```

**Step 7.2 — Modelli e migration**
Schema `decks`/`deck_cards` in cima al documento. `php artisan make:model Deck -m` + `php artisan make:migration create_deck_cards_table`. Nel modello `Deck`: `protected $casts = ['format' => DeckFormat::class];` e relazioni `deckCards()`, `leaderCards()`/`baseCard()` (scoped su `role`), `previousVersion()`/`versions()` (self-relation). Riferimento: https://laravel.com/docs/12.x/eloquent-mutators#enum-casting

**Step 7.3 — Validator per formato**
```php
interface DeckFormatValidator
{
    public function validate(Deck $deck): array;
}
```
`PremierFormatValidator`, `EternalFormatValidator`, `TwinSunsFormatValidator` in `app/Services/DeckValidation/`: numero di leader ammessi (1 o 2 in base al formato), esattamente 1 base, per Twin Suns il vincolo di allineamento tra i due leader, limiti di copie per carta secondo il regolamento ufficiale.

**Step 7.4 — Factory**
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

**Step 7.5 — Policy**
```
php artisan make:policy DeckPolicy --model=Deck
```
`update()`: proprietario o permesso `decks.manage-any`.

**Step 7.6 — Pagine mazzi**
- `/mazzi` — lista mazzi pubblici + propri (filtro per formato)
- `/mazzi/crea` — form: nome, formato (select `DeckFormat`), poi redirect all'editor
- `/mazzi/{deck}` — editor: ricerca carte (riusa `CardSearch`, Step 10.1) + aggiunta con ruolo (`leader`/`base`/`card`), validazione live lato server ad ogni salvataggio tramite il validator di formato (Step 7.3)
- `/mazzi/{deck}/versioni` — cronologia versioni (segue `previous_version_id`)
- toggle "montato" (`assembled`) sulla pagina del mazzo

**Step 7.7 — Export/Import mazzi**
- **Export**: `.txt` (formato ufficiale SWU) e `.json` (proprio) da `deck_cards`
- **Import**: da file (`.txt`/`.json`) o URL esterno; valida il formato, segnala carte non trovate invece di fallire silenziosamente (la vecchia versione aveva un bug proprio sull'import da URL, `todo.md` — occhio ai casi limite: URL non raggiungibile, redirect, formato inatteso)
- Classi dedicate `DeckExporter`/`DeckImporter` in `app/Services/`, testabili senza passare da una request HTTP

☐ Fase 7 completata

---

## Fase 8 — Gestione collezione

**Step 8.1 — Modello e migration**
Schema `collection_cards` in cima al documento. `php artisan make:migration create_collection_cards_table`.

**Step 8.2 — Pagina `/collezione`**
Ricerca carte (riusa `CardSearch`) + per ogni risultato un controllo quantità per variante (`normal`/`foil`/`hyper`/`prestige`/`hyper_foil`), salvato via piccola interazione Alpine.js senza reload pagina.

**Step 8.3 — "Carte mancanti per un mazzo"**
Tre informazioni: possedute sufficienti, mancanti del tutto, possedute-ma-impegnate-in-altri-mazzi-montati:
```php
$required = $deck->deckCards; // cid => quantity
$owned = CollectionCard::where('user_id', $userId)->selectRaw('cid, SUM(quantity) as qty')->groupBy('cid')->pluck('qty', 'cid');
$reservedByOtherAssembledDecks = DeckCard::whereHas('deck', fn ($q) => $q->where('user_id', $userId)->where('assembled', true)->where('id', '!=', $deck->id))
    ->selectRaw('cid, SUM(quantity) as qty')->groupBy('cid')->pluck('qty', 'cid');
// disponibile_libera = owned[cid] - reservedByOtherAssembledDecks[cid]
// mancante_del_tutto = max(0, required[cid] - owned[cid])
// posseduta_ma_impegnata = max(0, min(required[cid], owned[cid]) - disponibile_libera) quando disponibile_libera < required[cid]
```
Pagina `/mazzi/{deck}/delta` mostra le tre liste.

☐ Fase 8 completata

---

## Fase 9 — Bot Telegram

**Step 9.1 — Libreria**
Facade `Http` nativa (https://laravel.com/docs/12.x/http-client), niente SDK esterno — la vecchia versione usava `telegram-bot/api`, ma l'ecosistema di wrapper PHP per Telegram di quella fascia è in gran parte poco mantenuto o abbandonato (es. `vjik/telegram-bot-api`).

**Step 9.2 — `TelegramService`**
`app/Services/TelegramService.php`, un solo posto per parlare con l'API Telegram:
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
    public function sendMessage(int|string $chatId, string $text, array $options = []): TelegramActionResult {}
    public function sendPhoto(int|string $chatId, string $photoUrl, string $caption = '', array $options = []): TelegramActionResult {}
    public function editMessage(int|string $chatId, int $messageId, string $text): TelegramActionResult {}
    public function deleteMessage(int|string $chatId, int $messageId): TelegramActionResult {}
}
```
Ogni metodo traduce la risposta `{"ok": true/false, "result": {...}, "description": "..."}` di Telegram in un `TelegramActionResult`. Aggiungi altri metodi (`pinMessage`, `answerCallbackQuery`, ecc.) man mano che servono, stessa struttura. `editMessage` è quello usato dal progress-message unico dello scan (Step 4.5).

**Step 9.3 — Webhook**
```
php artisan make:controller TelegramController
```
Instrada `/scan`, `/search <query>` usando `TelegramService`.

**Step 9.4 — `/scan`**
```php
Artisan::call('cards:scan');
```
o dispaccia direttamente il job — nessuna logica duplicata.

**Step 9.5 — `/search`**
Query su `Card` (nome IT/EN). Risposta: `TelegramService::sendPhoto()` con l'immagine — l'API Telegram richiede un URL pubblico assoluto (la scarica lei stessa), quindi passa `Storage::disk('public')->url($card->front_art_path)` reso assoluto (es. tramite `asset(...)` o prefissando `config('app.url')`), non il path relativo salvato in DB — e didascalia; se fallisce, fallback su `sendMessage()` con link alla pagina carta sul sito.

**Step 9.6 — `NotifyAdminJob`**
```php
public function handle(TelegramService $telegram): void
{
    $telegram->sendMessage(config('services.telegram.admin_chat_id'), $this->message);
}
```

☐ Fase 9 completata

---

## Fase 10 — UI/UX e funzioni comuni TCG

**Step 10.1 — Ricerca/filtri carte**
Server-side puro, filtri via `GET`: espansione, aspetto (join `card_aspect`), tipo, costo, testo libero, **`unique_card`** (checkbox "solo carte Uniche"). Incapsula la query in `app/Services/CardSearch.php` (`apply(Builder $query, array $filters): Builder`), condivisa con l'endpoint API (Fase 11).
Pagina `/carte`, il parametro GET `nome` deve popolare il campo di ricerca già valorizzato al reload (bug specifico segnalato in `todo.md` della vecchia versione — attenzione a non fissarlo solo con `value="{{ $_GET['nome'] }}"` se il campo si aggiorna via JS/`oninput`, va sincronizzato anche lato client).

**Step 10.2 — Statistiche mazzo**
Pagina `/mazzi/{deck}/statistiche`: curva costi, distribuzione per tipo, tratti (divisi/non divisi, come da vecchia versione), HP/potenza media — grafici semplici (es. Chart.js).

**Step 10.3 — Viste pubbliche/autenticate**
Pubbliche: catalogo carte, mazzi pubblici, nuove uscite. Autenticate (`auth`): creare/modificare mazzi, collezione, export/import.

**Step 10.4 — Pagina "Nuove uscite"**
`GET /nuove-uscite`, parametro opzionale `since` (`YYYY-MM-DD`) — se specificato resta un intervallo **arbitrario** a scelta dell'utente; se assente, default alla data di rilascio più recente (`Card::max('release_date')`), non a un intervallo fisso:
```php
$since = $request->query('since') ?? Card::max('release_date');
Card::where('release_date', '>=', $since)->orderByDesc('release_date')->get();
```
Filtra su `cards.release_date`, non su `expansions.legal_date` (concetti diversi). Interfaccia: `<input type="date">` in un form GET.

☐ Fase 10 completata

---

## Fase 11 — API REST pubblica

**Step 11.1 — Sanctum**
```
composer require laravel/sanctum
php artisan install:api
```
https://laravel.com/docs/12.x/sanctum

**Step 11.2 — Endpoint pubblici**
```php
Route::get('/cards/{expansion}/{number}', [Api\CardController::class, 'show']);
Route::get('/cards/search', [Api\CardController::class, 'search']); // stessi filtri di CardSearch (Step 10.1)
Route::get('/decks/{user}/{name}', [Api\DeckController::class, 'show']); // solo mazzi pubblici
```
`Api\CardController::search()` riusa `CardSearch` (Step 10.1): stessa logica, output diverso (`CardResource::collection(...)`). Principio generale: le pagine API condividono il backend delle pagine UI dove possibile, un solo posto da mantenere. Rate limiting nativo (`throttle:60,1`). https://laravel.com/docs/12.x/routing#rate-limiting

**Step 11.3 — Endpoint autenticati**
Token Sanctum per eventuali azioni future (es. sync collezione da app esterna) — non necessario al day 1.

**Step 11.4 — API Resources**
```
php artisan make:resource CardResource
```
Controlla cosa esporre (es. non esporre `id` interni se usi `cid` come chiave pubblica). https://laravel.com/docs/12.x/eloquent-resources

☐ Fase 11 completata

---

## Fase 12 — Deploy

**Step 12.1** — Lancia `~/scripts/new-site.sh` sul server (genera lui Dockerfile/compose/nginx/init.sql, dominio `unlimiteddb.mandich.dev`).
**Step 12.2** — Aggiungi tu il servizio queue worker (non generato dallo script): `php artisan queue:work --tries=3` sempre attivo.
**Step 12.3** — Aggiungi tu lo scheduler (non generato dallo script): cron reale che lancia `php artisan schedule:run` ogni minuto.
**Step 12.4** — Verifica: webhook Telegram sul dominio giusto, `failed_jobs` vuota, scan schedulato parte al lunedì.

☐ Fase 12 completata

---

## Backlog — Funzionalità future (da `todo.md`, non pianificate in dettaglio)

Elencate per non perderle, ma fuori dallo scope attuale — da riprendere quando/se deciderai di implementarle:
- Condivisione social dei mazzi
- Tag personalizzati per i mazzi (es. "Aggro", "Control", "Budget", "Meta")
- Modalità offline/PWA per consultazione carte
- Wishlist carte desiderate
- Deck-building guidato per principianti
- Statistica di probabilità (ipergeometrica) di pescare una carta che soddisfi certi requisiti, nelle statistiche mazzo (Step 10.2)
- Apertura digitale dei booster pack (per cui è già propedeutico `expansions.group_main_expansion`, vedi schema)

---

## Note di analisi (perché queste scelte)

- **Queue reali invece di fireAndForget**: niente più thread simulati via HTTP ricorsivo per aggirare l'assenza di code su Altervista. https://laravel.com/docs/12.x/queues
- **Permessi granulari (Spatie)**: permessi singoli, i ruoli sono solo scorciatoie per assegnarne un gruppo insieme. https://spatie.be/docs/laravel-permission/v6/introduction
- **Enum + Strategy per i formati mazzo**: aggiungere un formato futuro richiede solo una nuova classe.
- **Blade + Alpine.js invece di Livewire**: la lentezza percepita nella vecchia versione era un bug preciso (catalogo intero come proprietà pubblica Livewire), non un limite del framework — ma Blade+Alpine evita il rischio per design.
- **`.env-overrides`**: pattern SWUDB per tracciare `APP_VERSION` in Git.
- **Verifica email nativa**: sostituisce token custom a 60 caratteri con `MustVerifyEmail` + middleware `verified`.
- **`system_errors` con 3 stati (`open`/`resolved`/`ignored`)**: la correttezza della logica di import la verifica Pest (dati controllati), non un controllo post-hoc sulla produzione — per questo non esiste più una tabella dedicata ai risultati dei test.
- **`deck_cards.role` invece di `leader_cid`/`base_cid` su `decks`**: la cardinalità di leader/base dipende dal formato (1 vs 2 leader), colonne fisse non reggerebbero Twin Suns.
- **`decks.assembled`**: necessario per calcolare non solo "cosa manca" ma anche "cosa possiedo ma è impegnato in un altro mazzo montato".
- **Immagini scaricate in locale**: il sito non dipende a runtime dalla disponibilità del CDN ufficiale, tempi di caricamento sotto controllo.

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
| Filesystem/Storage | https://laravel.com/docs/12.x/filesystem |
| Mail | https://laravel.com/docs/12.x/mail |
| Resend (mailer Laravel) | https://laravel.com/docs/12.x/mail#resend-driver |
| Resend + Laravel (guida ufficiale) | https://resend.com/docs/send-with-laravel |
| Resend domini/DNS | https://resend.com/docs/dashboard/domains/introduction |
| Testing (Pest) | https://laravel.com/docs/12.x/testing |
| Spatie Laravel-permission | https://spatie.be/docs/laravel-permission/v6/introduction |
| Verifica email | https://laravel.com/docs/12.x/verification |
| Sanctum (API auth) | https://laravel.com/docs/12.x/sanctum |
| API Resources | https://laravel.com/docs/12.x/eloquent-resources |
| Rate limiting rotte | https://laravel.com/docs/12.x/routing#rate-limiting |
