# Implementation plan 12 — Import carte: un job per pagina (fix della memoria esaurita)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). **Prerequisito dello Step 13.4 del piano 13** ([`implementationPlan-13-ricostruzioneDeploy.md`](implementationPlan-13-ricostruzioneDeploy.md)): il reset pulito del database richiede un import completo che finisca senza errori. Va eseguito dopo il piano 11 (pipeline di deploy) e prima del piano 13. Gli step sono numerati da 0 a 8 e non hanno una "Fase" propria: nasce come piano trasversale e lavora sul codice già consegnato dai piani 02, 03 e 09.
> Assorbe le voci aperte 5.5.6–5.5.8 del piano 03 (vedi `todo.md`).
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice lo applichi tu a mano.

> **Stato di applicazione (verificato sul codice e su `test.log` il 2026-10-27)**: Step 1, 2, 3, 4 e 6 applicati, suite a 122 passati e nessun fallito. Step 5 chiuso il 2026-10-27: `worker.conf` e worker di sviluppo fatti, `DB_QUEUE_RETRY_AFTER=300` aggiunto a `.env.example` e a posto negli `.env` (dichiarato dall'utente). Step 7 eseguito il 2026-10-27 (log: 32 pagine, 3135 carte, 0 problemi, picco 80 MB; mail e ripresa con `--page=N` provate dall'utente). Step 8 fatto il 2026-10-27 (documentazione allineata e piano rinominato in `V-`); lo Step 0 (conferma della diagnosi) non è stato fatto, era opzionale. **Piano completato.**
> Scostamenti del codice da questo piano: (a) `PAGE_SIZE` è 100 invece di 40, **scelta voluta** (i commenti del job parlano ancora di 40 carte: con 100 carte, fino a 200 immagini, misurare il tempo nello Step 7 rispetto ai 240 s di `$timeout`); (b) il filtro `filters[variantOf][id][$null]` è di nuovo attivo (corretto dall'utente il 2026-10-27); (c) `cards:scan` risponde sempre "Cards scan queued from page N.", anche per la pagina 1; (d) il `Log::debug` per carta scrive `{$card->id}` invece di espansione e numero.

## Problema

Il 2026-10-18 l'import completo (`cards:scan` → `ImportCardsFromSwuApiJob`) si è fermato dopo circa 180 pagine da 40 carte (~7200 carte) con "Allowed memory size exhausted". In più, nel log di quel giorno comparivano un 502 dell'API alla pagina 126 e "attempted too many times".

## Diagnosi (dal codice, non da una misura: lo Step 0 la conferma)

Oggi **un solo job** fa tutto: scarica tutte le pagine, elabora tutte le carte e scarica tutte le immagini, in un unico processo `php artisan queue:work` con il `memory_limit` di default della CLI (128 MB, nel `Dockerfile` non c'è nessun `php.ini`).

1. **`$newCards` e `$existingCards`** (e `$errors`) sono `Collection` di modelli Eloquent che si riempiono per **tutto** lo scan: a ogni carta si aggiunge un `Card` completo (con `text` e `deploy_text` HTML) che non si libera mai. A ~7200 carte sono decine di MB, ed è il motivo per cui il crollo arriva a una pagina precisa. I `SystemError` in `$errors` portano anche `context.raw` (il payload intero della carta).
2. **Mai rilasciata la memoria tra una pagina e l'altra**: essendo un job solo, il garbage collector non può liberare niente di ciò che il job tiene in mano; un job per pagina fa ripartire ogni volta da un'istanza nuova, e `queue:work --memory` riavvia il worker se cresce.
3. **Nessun riavvio dalla pagina in cui si è fermato**: un 502 (pagina 126) fa `break` del ciclo e lo scan finisce "bene" ma incompleto, senza ritentare; un crash ricomincia da zero.
4. **`retry_after` = 90 s** (`config/queue.php`, `DB_QUEUE_RETRY_AFTER`) con un job che dura decine di minuti: dopo 90 secondi l'altro worker (`numprocs=2`) lo riprende, trova `attempts` 2 oltre `$tries = 1` e lo segna fallito con "has been attempted too many times", mentre il primo continua a girare. Probabile origine di quel messaggio nel log del 18/10 (si verifica dall'orario: ~90 secondi dopo l'avvio).
5. **Log enormi**: per ogni pagina si scrive l'intero payload (`Log::info("trovate pagine totali", [..., $payload])`) e per ogni carta il modello intero; su ~7200 carte sono centinaia di MB nel `laravel.log`, su una VM con il disco già al 79%.
6. **Le mail**: `NewCardsEmail` riceve la lista di **tutte** le carte nuove. Al primo import dopo un reset sono ~7200: la mail è enorme e il job in coda, deserializzando 7200 modelli, può ripetere lo stesso errore di memoria.

## Soluzione

Il job diventa una **catena di job per pagina**: `ImportCardsFromSwuApiJob($page, $runId)` elabora **una** pagina (40 carte), poi accoda sé stesso per la pagina successiva, finché non è l'ultima; l'ultima fa il lavoro di chiusura (mail, messaggio Telegram finale).

- Memoria: ogni job vive pochi minuti e rilascia tutto; non si tengono più modelli, si tiene solo un piccolo stato in cache (`started_at`, `message_id`, conteggio). **Le carte nuove e gli errori si ricavano dal database a fine scan** (`created_at >= started_at`), non da liste in memoria.
- Robustezza: ogni pagina ha 3 tentativi (`$tries`, `$backoff`) più i tentativi HTTP; se la pagina fallisce davvero, `failed()` lo segnala (Telegram + `SystemError`) con il comando per riprendere: `php artisan cards:scan --page=N`.
- Un solo scan alla volta (lock in cache), `retry_after` e `timeout` coerenti con la durata di una pagina.
- Mail con tetto: al massimo 100 carte nell'elenco ("... e altre N") e 50 errori nel report.
- Log al livello giusto: per pagina `info`, per carta `debug` e senza modelli o payload nel contesto.

Non cambiano: `TelegramController` (`ImportCardsFromSwuApiJob::dispatch()` senza argomenti resta uno scan completo), lo scheduler del lunedì, `routes/console.php`.

## Step 0 — Conferma la diagnosi (opzionale, 2 minuti)

Se hai ancora il log del 18/10 (VM, `~/sites/unlimiteddb/storage/logs/laravel.log`):
```bash
grep -n "Allowed memory size\|attempted too many times\|richiesta API fallita" storage/logs/laravel.log | head
grep -n "Scan in corso: pagina 1/" storage/logs/laravel.log | head -1
```
- "Allowed memory size of 134217728 bytes exhausted" conferma il limite di 128 MB (punto 1);
- "attempted too many times" ~90 secondi dopo il primo "Scan in corso" conferma il punto 4, che non è un problema di memoria ma lo si risolve nello stesso piano.

## Step 1 — Mailable e template con tetto sulle liste

#### 1.1 — `app/Mail/NewCardsEmail.php`
```diff
-    public function __construct(public readonly Collection $newCards) {}
+    public function __construct(public readonly Collection $newCards, public readonly ?int $total = null) {}
```
e in `content()`:
```diff
             with: [
                 'cards' => $this->newCards,
+                'total' => $this->total ?? $this->newCards->count(),
             ],
```
Aggiorna il docblock: `$newCards` sono le prime carte (al massimo `ImportCardsFromSwuApiJob::EMAIL_CARDS_LIMIT`), `$total` il numero reale.

#### 1.2 — `resources/views/emails/md-new-cards.blade.php`
Dopo il `@endforeach`, prima di `</x-mail::message>`, **senza indentazione** (il Markdown di Laravel la leggerebbe come blocco di codice) e **con una riga vuota** prima del testo (senza, il Markdown lo accoda all'ultimo punto dell'elenco invece di farne un paragrafo):
```blade
@if ($total > $cards->count())

…e altre {{ $total - $cards->count() }} carte: le trovi nella pagina delle nuove uscite.
@endif
```

#### 1.3 — `app/Mail/AdminScanReportEmail.php`
```diff
-    public function __construct(public readonly Collection $errors) {}
+    public function __construct(public readonly Collection $errors, public readonly ?int $total = null) {}
```
```diff
-            with: ['errors' => $this->errors],
+            with: ['errors' => $this->errors, 'total' => $this->total ?? $this->errors->count()],
```

#### 1.4 — `resources/views/emails/md-admin-scan-report.blade.php`
```diff
-*Ci sono stati {{ $errors->count() }} errori durante lo scan*
+*Ci sono stati {{ $total }} errori durante lo scan*
```
e dopo `</x-mail::table>`, **con una riga vuota** tra il tag e l'`@if` (la tabella è un blocco HTML: senza riga vuota il testo che segue resta dentro il blocco e il link in Markdown non viene convertito):
```blade

@if ($total > $errors->count())
Sono mostrati gli ultimi {{ $errors->count() }}: gli altri sono in [Gestione errori]({{ url('/admin/errori') }}).
@endif
```
Gli argomenti nuovi sono opzionali: l'anteprima `/render-mail/...` e i test esistenti continuano a funzionare.

## Step 2 — `cards:scan --page=N`

`app/Console/Commands/ScanCards.php`:
```php
protected $signature = 'cards:scan {--page=1 : Pagina da cui ripartire (1 = scan completo)}';

public function handle()
{
    $page = max(1, (int) $this->option('page'));
    ImportCardsFromSwuApiJob::dispatch($page);
    $this->info($page === 1 ? 'Cards scan queued.' : "Cards scan queued from page {$page}.");
}
```

Due conseguenze da tenere presenti (non sono bug, ma sorprendono):
- **Ripresa con `--page=N`**: parte un nuovo scan (nuovo `runId`), quindi il riepilogo finale e la mail contano come "nuove" solo le carte create **da quel momento**; quelle importate dalle pagine precedenti, prima dell'interruzione, non compaiono.
- **Lock rimasto appeso**: se un worker muore senza che la coda chiami `failed()` (ad esempio `docker kill` o coda svuotata a mano), il lock resta per 3 ore e `cards:scan` risponde "Scan già in corso". Per liberarlo: `php artisan cache:forget cards-import:running` (nel container `app`).

## Step 3 — Riscrittura di `ImportCardsFromSwuApiJob`

Sostituisci **tutto** `app/Jobs/ImportCardsFromSwuApiJob.php` con:

```php
<?php

namespace App\Jobs;

use App\Mail\AdminScanReportEmail;
use App\Mail\NewCardsEmail;
use App\Models\Aspect;
use App\Models\Card;
use App\Models\CardTrait;
use App\Models\Expansion;
use App\Models\SystemError;
use App\Models\User;
use App\Services\CardImageDownloader;
use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Imports ONE page of the official card list, then queues itself for the next page
 * (the last page also sends the final notifications). Memory stays flat: no job holds more than 40 cards.
 * Importa UNA pagina dell'elenco carte ufficiale e poi accoda sé stesso per la successiva
 * (l'ultima pagina manda anche le notifiche finali). La memoria resta piatta: nessun job tiene più di 40 carte.
 */
class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public const API_URL = 'https://admin.starwarsunlimited.com/api/card-list';

    public const PAGE_SIZE = 40;

    /** Chiave del lock: contiene il runId dello scan in corso. */
    public const LOCK_KEY = 'cards-import:running';

    /** Il lock scade da solo se uno scan muore senza liberarlo. */
    public const LOCK_SECONDS = 10800;

    public const EMAIL_CARDS_LIMIT = 100;

    public const EMAIL_ERRORS_LIMIT = 50;

    // Una pagina (40 carte, fino a 80 immagini) impiega una o due minuti: 240 s di timeout,
    // e retry_after (DB_QUEUE_RETRY_AFTER, Step 5) maggiore del timeout.
    public $tries = 3;

    public $backoff = [60, 180];

    public $timeout = 240;

    /** Identifica lo scan: resta uguale su tutte le pagine e su ogni tentativo. */
    public string $runId;

    /** True solo per il job che avvia lo scan (manda il messaggio "Scan avviato..."). */
    public bool $startsRun;

    public function __construct(public int $page = 1, ?string $runId = null)
    {
        $this->startsRun = $runId === null;
        $this->runId = $runId ?? (string) Str::uuid();
    }

    public function handle(TelegramService $telegram, CardImageDownloader $imageDownloader): void
    {
        $chatId = config('services.telegram.admin_chat_id');

        if (! $this->acquireLock()) {
            Log::warning("Scan {$this->runId}: un altro scan è già in corso, pagina {$this->page} non elaborata");
            if ($this->startsRun) {
                $telegram->sendMessage($chatId, 'Scan già in corso: non ne avvio un altro.');
            }

            return;
        }

        $state = Cache::get($this->stateKey());
        if ($state === null) {
            $progress = $this->startsRun ? $telegram->sendMessage($chatId, 'Scan avviato...') : null;
            $state = [
                'started_at' => now()->startOfSecond()->toIso8601String(),
                'message_id' => $progress?->messageId,
                'processed' => 0,
            ];
            Cache::put($this->stateKey(), $state, now()->addDay());
            if (! $this->startsRun) {
                Log::warning("Scan {$this->runId}: stato perso (cache svuotata?), le carte nuove delle pagine precedenti non saranno contate");
            }
        }

        $response = Http::timeout(30)->retry(3, 2000, throw: false)->get(self::API_URL, [
            'locale' => 'it',
            'filters[variantOf][id][$null]' => 'true',
            'fields' => [
                'cardUid',
                'cardNumber',
                'title',
                'subtitle',
                'unique',
                'cost',
                'hp',
                'power',
                'textStyled',
                'deployBoxStyled',
                'artist',
                'publishedAt',
            ],
            'pagination[page]' => $this->page,
            'pagination[pageSize]' => self::PAGE_SIZE,
        ]);

        if ($response->failed()) {
            // Lancia l'eccezione invece di fare "break": la coda ritenta la pagina e, se non basta, failed() lo segnala
            throw new \RuntimeException("Pagina {$this->page}: richiesta API fallita ({$response->status()})");
        }

        $payload = $response->json();
        $lastPage = (int) ($payload['meta']['pagination']['pageCount'] ?? $this->page);
        $latestRotation = Expansion::max('rotation') ?? '0';

        $telegram->editMessage($chatId, $state['message_id'], "Scan in corso: pagina {$this->page}/{$lastPage}...");

        $processed = 0;
        foreach ($payload['data'] ?? [] as $cardEntry) {
            if ($this->processCard($cardEntry['attributes'] ?? [], $latestRotation, $imageDownloader)) {
                $processed++;
            }
        }

        Log::info("Scan {$this->runId}: pagina {$this->page}/{$lastPage} elaborata", [
            'carte' => $processed,
            'memoria_picco_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        $state['processed'] += $processed;
        Cache::put($this->stateKey(), $state, now()->addDay());

        if ($this->page < $lastPage) {
            self::dispatch($this->page + 1, $this->runId);

            return;
        }

        $this->finish($telegram, $state, $chatId);
    }

    /**
     * Called by the queue when the page has failed all its attempts
     * Chiamato dalla coda quando la pagina ha esaurito i tentativi
     */
    public function failed(\Throwable $e): void
    {
        SystemError::create([
            'source' => self::class,
            'message' => "Scan interrotto alla pagina {$this->page}",
            'stack_trace' => $e->getTraceAsString(),
            'context' => [
                'error_message' => $e->getMessage(),
                'page' => $this->page,
                'run_id' => $this->runId,
            ],
        ]);

        app(TelegramService::class)->sendMessage(
            config('services.telegram.admin_chat_id'),
            "Scan interrotto alla pagina {$this->page}: {$e->getMessage()}. Per riprendere: php artisan cards:scan --page={$this->page}"
        );

        $this->releaseLock();
    }

    /**
     * Imports a single card. Returns false (after saving a SystemError) if the card could not be processed.
     * Importa una carta. Ritorna false (dopo aver salvato un SystemError) se la carta non si è potuta elaborare.
     */
    private function processCard(array $cardData, string $latestRotation, CardImageDownloader $imageDownloader): bool
    {
        $cid = $cardData['cardUid'] ?? null;
        $card = null;

        try {
            if (! $cid) {
                throw new \RuntimeException('cardUid mancante nel payload');
            }

            if (str_contains($cardData['type']['data']['attributes']['name'] ?? '', 'Segnalino')) {
                $cardData['expansion']['data']['attributes']['code'] =
                    "T{$cardData['expansion']['data']['attributes']['code']}";
            }

            $this->createExpansionIfMissing($cardData, $latestRotation);

            $card = Card::updateOrCreate(
                ['cid' => $cid],
                [
                    'expansion' => $cardData['expansion']['data']['attributes']['code'] ?? null,
                    'number' => $cardData['cardNumber'],
                    'unique_card' => $cardData['unique'] ?? false,
                    'name' => $cardData['title'],
                    'title' => $cardData['subtitle'] ?? null,
                    'type' => $cardData['type']['data']['attributes']['name'] ?? null,
                    'rarity' => $cardData['rarity']['data']['attributes']['name'] ?? null,
                    'cost' => $cardData['cost'] ?? null,
                    'health' => $cardData['hp'] ?? null,
                    'power' => $cardData['power'] ?? null,
                    'text' => $cardData['textStyled'] ?? null,
                    'deploy_text' => $cardData['deployBoxStyled'] ?? null,
                    'arena' => $cardData['arenas']['data'][0]['attributes']['name'] ?? null,
                    'artist' => $cardData['artist'] ?? null,
                    'release_date' => isset($cardData['publishedAt']) ? Carbon::parse($cardData['publishedAt'])->toDateString() : null,
                    'max_copies' => $cardData['cardNumber'] == 256 && ($cardData['expansion']['data']['attributes']['code'] ?? null) == 'JTL' ? 15 : null,
                ]
            );

            $this->syncAspectsAndTraits($card, $cardData);
            $this->downloadImages($card, $cardData, $imageDownloader);

            Log::debug("Carta {$cid} ({$card->expansion}-{$card->number}) elaborata");

            return true;
        } catch (\Throwable $e) {
            SystemError::create([
                'source' => self::class,
                'message' => 'Errore su carta '.($cid ?? '(senza cardUid)').' ('.($card?->expansion ?? 'Espansione Mancante').'-'.($card?->number ?? 'Numero Mancante').')',
                'stack_trace' => $e->getTraceAsString(),
                'context' => [
                    'error_message' => $e->getMessage(),
                    'error_line' => $e->getLine(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'raw' => $cardData,
                ],
            ]);
            Log::warning("Errore nell'elaborazione della carta {$cid}: {$e->getMessage()}");

            return false;
        }
    }

    private function createExpansionIfMissing(array $cardData, string $latestRotation): void
    {
        $expansionData = $cardData['expansion']['data']['attributes'] ?? null;
        $expansionCode = $expansionData['code'] ?? null;

        if ($expansionCode && ! Expansion::where('expansion', $expansionCode)->exists()) {
            Log::info("Creazione dell'espansione {$expansionCode}");
            Expansion::create([
                'expansion' => $expansionCode,
                'legal_date' => isset($expansionData['publishedAt']) ? Carbon::parse($expansionData['publishedAt'])->toDateString() : null,
                'rotation' => $latestRotation,
            ]);
        }
    }

    private function syncAspectsAndTraits(Card $card, array $cardData): void
    {
        $aspectIds = collect($cardData['aspects']['data'] ?? [])
            ->values()
            ->mapWithKeys(function ($aspectEntry, $index) {
                $attr = $aspectEntry['attributes'];

                $id = Aspect::updateOrCreate(
                    ['name' => $attr['name']],
                    [
                        'color' => $attr['color'] ?? null,
                        'order' => $attr['sortValue'] ?? Aspect::max('order') + 1,
                        'slug' => Str::slug($attr['englishName'] ?? $attr['name']),
                    ]
                )->id;

                return [$id => ['position' => $index]];
            });

        $card->aspects()->sync($aspectIds);

        $traitNames = collect($cardData['traits']['data'] ?? [])
            ->pluck('attributes.name')
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        $traitNames->each(fn ($name) => CardTrait::firstOrCreate(['name' => $name]));

        $card->traits()->sync($traitNames);
    }

    private function downloadImages(Card $card, array $cardData, CardImageDownloader $imageDownloader): void
    {
        $frontUrl = $cardData['artFront']['data']['attributes']['url']
            ?? $cardData['artFront']['data']['attributes']['formats']['card']['url']
            ?? null;
        $backAttrs = $cardData['artBack']['data']['attributes'] ?? null;
        $backUrl = $backAttrs['url'] ?? $backAttrs['formats']['card']['url'] ?? null;

        // un lato assente nell'API non è un errore: si salta (e si logga solo a livello debug)
        $sides = array_filter(['front' => $frontUrl, 'back' => $backUrl]);

        foreach ($sides as $side => $url) {
            $column = "{$side}_art_path";

            if ($card->{$column}) {
                continue;
            }

            $path = $imageDownloader->download($url, $card, $side);

            if ($path) {
                $card->update([$column => $path]);
            } else {
                SystemError::create([
                    'source' => CardImageDownloader::class,
                    'message' => "Download immagine {$side} fallito per {$card->cid} ({$card->expansion}-{$card->number} - {$card->name}, {$card->title})",
                    'context' => [
                        'error_message' => "Il download di {$url} non ha restituito un path",
                        'source_url' => $url,
                        'side' => $side,
                    ],
                ]);
            }
        }
    }

    /**
     * Last page: counts new cards and errors from the database, sends the notifications and the final message
     * Ultima pagina: ricava carte nuove ed errori dal database, manda le notifiche e il messaggio finale
     */
    private function finish(TelegramService $telegram, array $state, int|string|null $chatId): void
    {
        $since = Carbon::parse($state['started_at']);
        $newCount = Card::where('created_at', '>=', $since)->count();
        $errorCount = $this->runErrors($since)->count();

        $this->sendNotifications($since, $newCount, $errorCount);

        // si cancella il messaggio di avanzamento così il messaggio finale arriva come notifica
        $telegram->deleteMessage($chatId, $state['message_id']);
        $summary = "Scan completato: {$state['processed']} carte elaborate, {$newCount} nuove, {$errorCount} problemi.";
        $telegram->sendMessage($chatId, $summary);
        Log::info("Scan {$this->runId}: {$summary}");

        Cache::forget($this->stateKey());
        $this->releaseLock();
    }

    private function sendNotifications(Carbon $since, int $newCount, int $errorCount): void
    {
        if ($newCount > 0) {
            // L'elenco è limitato: al primo import (migliaia di carte) una mail con tutte le carte
            // sarebbe enorme e il job che la prepara rischierebbe lo stesso errore di memoria.
            // orderBy esplicito: niente ordinamento di default di CardBuilder.
            $cards = Card::where('created_at', '>=', $since)
                ->orderBy('release_date')->orderBy('id')
                ->limit(self::EMAIL_CARDS_LIMIT)
                ->get();

            User::query()->chunkById(100, function ($users) use ($cards, $newCount) {
                foreach ($users as $user) {
                    Mail::to($user)->queue(new NewCardsEmail($cards, $newCount));
                }
            });
        }

        if ($errorCount > 0) {
            $errors = $this->runErrors($since)->latest('id')->limit(self::EMAIL_ERRORS_LIMIT)->get();

            // whereHas e non User::role('admin'): quest'ultimo lancia RoleDoesNotExist se il ruolo non esiste
            User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->each(fn ($admin) => Mail::to($admin)->queue(new AdminScanReportEmail($errors, $errorCount)));
        }
    }

    /** @return Builder<SystemError> */
    private function runErrors(Carbon $since): Builder
    {
        return SystemError::where('created_at', '>=', $since)
            ->whereIn('source', [self::class, CardImageDownloader::class]);
    }

    private function stateKey(): string
    {
        return "cards-import:{$this->runId}";
    }

    private function acquireLock(): bool
    {
        if (Cache::add(self::LOCK_KEY, $this->runId, self::LOCK_SECONDS)) {
            return true;
        }

        if (Cache::get(self::LOCK_KEY) !== $this->runId) {
            return false;
        }

        Cache::put(self::LOCK_KEY, $this->runId, self::LOCK_SECONDS); // stesso scan: rinnova la scadenza

        return true;
    }

    private function releaseLock(): void
    {
        if (Cache::get(self::LOCK_KEY) === $this->runId) {
            Cache::forget(self::LOCK_KEY);
        }
    }
}
```

Note di lettura:
- `runId` viene generato nel costruttore, quindi resta lo stesso se la coda ritenta il job: il lock e lo stato in cache si riconoscono anche al secondo tentativo.
- Le carte nuove si riconoscono da `created_at >= started_at`, gli errori da `system_errors` dello stesso periodo: una pagina ritentata dopo un fallimento a metà non fa perdere nessuna carta nuova (con le liste in memoria le avrebbe contate come "già presenti").
- Una pagina ritentata può duplicare un `SystemError` della stessa carta: è il prezzo accettato per non tenere liste in memoria.
- Il vecchio `$card->save()` dopo `updateOrCreate` e il vecchio `existed` (una query per carta) non servono più.
- `Cache::` usa `CACHE_STORE` (`database` in produzione, `array` nei test): lo stato sono tre campi, pochi byte.
- Se in futuro cambierai `PAGE_SIZE`, cambia anche il commento sul timeout.

## Step 4 — Download immagini più robusto

`app/Services/CardImageDownloader.php`:
```diff
-            $response = Http::get($sourceUrl);
+            $response = Http::timeout(20)->retry(2, 500, throw: false)->get($sourceUrl);
```
e nel `Log::warning`, al posto del modello intero:
```diff
-                'card' => $card,
+                'cid' => $card->cid,
```

## Step 5 — Worker e coda

#### 5.1 — `docker/supervisor/worker.conf`
```diff
-command=php /var/www/html/artisan queue:work --max-time=3600
+command=php /var/www/html/artisan queue:work --max-time=3600 --memory=100 --timeout=240
```
`--memory=100`: se il worker supera i 100 MB dopo un job si ferma e supervisor lo rilancia, **prima** di arrivare al limite di 128 MB di PHP. `--timeout=240` è per i job: senza l'estensione `pcntl` (l'immagine non la ha) non viene fatto rispettare dal worker, ma resta coerente con `$timeout` e con `retry_after`.

#### 5.1bis — Worker di sviluppo
`docker-compose.dev.yml` ha il suo comando (`php artisan queue:work --max-time=3600`, senza supervisor): per lo Step 7 usa le stesse opzioni, altrimenti la prova in locale non è rappresentativa della produzione:
```diff
-    command: php artisan queue:work --max-time=3600
+    command: php artisan queue:work --max-time=3600 --memory=100 --timeout=240
```
(è "docker infra": se preferisci lo applico io.) Nel compose di sviluppo non c'è `schedule:work`, quindi lo scan si lancia a mano come oggi.

#### 5.2 — `retry_after`
`DB_QUEUE_RETRY_AFTER` deve essere **maggiore** del timeout (240 s): 300.
- `.env.example`: aggiungi `DB_QUEUE_RETRY_AFTER=300` sotto `QUEUE_CONNECTION=database`.
- `.env` del progetto in locale e `.env` sulla VM (`~/sites/unlimiteddb/.env`): aggiungi la stessa riga, poi `docker compose restart worker` (in dev `docker compose -f docker-compose.dev.yml restart worker`).

#### 5.3 — Log
Per l'import completo sulla VM imposta `LOG_LEVEL=info` nel `.env` (i log per carta sono `debug`). Controlla a fine import la dimensione: `du -sh storage/logs`.

## Step 6 — Test Pest

File: `tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php` (con `QUEUE_CONNECTION=sync` dei test, il job che accoda sé stesso viene eseguito subito: le pagine successive girano dentro la stessa chiamata a `handle()`).

#### 6.1 — Fixture e test esistenti
In `fakeCardEntry()`, dopo `$base = $first['data'][0];` aggiungi il campo che il job chiede nei `fields` e che il fixture non ha (causa 5.5.8 punto 1):
```php
$base['attributes']['publishedAt'] ??= '2025-01-01T00:00:00.000Z';
```
Nel test `crea le carte nuove ricevute dall'API` aggiungi, prima di `Mail::fake();`, un utente (senza utenti la mail alle persone non parte e `assertQueued` fallirebbe):
```php
User::factory()->create();
```
Gli altri test esistenti restano invariati.

#### 6.2 — Test nuovi, in fondo allo stesso file
Servono questi `use` in testa: `use Illuminate\Support\Facades\Artisan;`, `use Illuminate\Support\Facades\Cache;`, `use Illuminate\Support\Sleep;`.
```php
it('riprende da una pagina con cards:scan --page', function () {
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 2]],
        ]),
    ]);
    Mail::fake();

    Artisan::call('cards:scan', ['--page' => 2]);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'pagination[page]=2'));
    Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), 'pagination[page]=1'));
});

it('non avvia due scan insieme', function () {
    fakeSwuHttp([]);
    Cache::put(ImportCardsFromSwuApiJob::LOCK_KEY, 'un-altro-scan', now()->addHour());

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    expect(Card::count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'card-list'));
});

it('lancia un errore se l\'API risponde con un 5xx invece di chiudere lo scan', function () {
    Sleep::fake();
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response('Bad Gateway', 502),
    ]);
    Mail::fake();

    expect(fn () => (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class)))
        ->toThrow(RuntimeException::class, 'richiesta API fallita (502)');
});

it('failed() salva un SystemError con la pagina e libera il lock', function () {
    $job = new ImportCardsFromSwuApiJob(4);
    Cache::put(ImportCardsFromSwuApiJob::LOCK_KEY, $job->runId, now()->addHour());

    $job->failed(new RuntimeException('boom'));

    expect(SystemError::where('message', 'like', '%pagina 4%')->exists())->toBeTrue();
    expect(Cache::has(ImportCardsFromSwuApiJob::LOCK_KEY))->toBeFalse();
});

it('limita l\'elenco delle carte nella mail ma ne riporta il totale', function () {
    User::factory()->create();
    $entries = collect(range(1, 105))->map(fn ($i) => fakeCardEntry(['attributes' => ['cardUid' => "uid{$i}", 'cardNumber' => $i]]))->all();
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => $entries,
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();

    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertQueued(NewCardsEmail::class, fn ($mail) => $mail->newCards->count() === ImportCardsFromSwuApiJob::EMAIL_CARDS_LIMIT
        && $mail->total === 105);
});

it('una seconda scansione non segnala come nuove le carte già importate', function () {
    User::factory()->create();
    fakeSwuHttp([
        'admin.starwarsunlimited.com/api/card-list*' => Http::response([
            'data' => [fakeCardEntry()],
            'meta' => ['pagination' => ['pageCount' => 1]],
        ]),
    ]);
    Mail::fake();
    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    $this->travel(2)->minutes(); // created_at ha la precisione dei secondi
    Mail::fake();              // azzera le mail registrate dal primo scan
    (new ImportCardsFromSwuApiJob)->handle(app(TelegramService::class), app(CardImageDownloader::class));

    Mail::assertNotQueued(NewCardsEmail::class);
});

it('mostra nel report admin quanti errori ci sono oltre a quelli elencati', function () {
    $error = SystemError::create(['source' => 'test', 'message' => 'Carta X', 'context' => ['error_message' => 'boom']]);

    $html = (new AdminScanReportEmail(collect([$error]), 7))->render();

    expect($html)->toContain('7 errori')->toContain('Gestione errori');
});
```

#### 6.3 — Esecuzione
```
php artisan test tests/Feature/Jobs/ImportCardsFromSwuApiJobTest.php
composer test
```
Atteso: i test del job verdi (si chiudono anche le cause 1 e 2 di 5.5.8: `publishedAt` e `RoleDoesNotExist`). Se la suite intera mostra ancora fallimenti con `near "ORDER"`, sono la causa dello Step 11.5.8 del piano 10 (nuova query su `Card` con ordinamento di default su SQLite), non di questo piano.

## Step 7 — Verifica in locale (compose di sviluppo)

Con `DB_QUEUE_RETRY_AFTER=300` nel `.env` e il worker riavviato (Step 5):
```bash
docker compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed
docker compose -f docker-compose.dev.yml exec app php artisan cards:scan
```
Un import completo in locale è lungo (come sulla VM) ma sul tuo PC la memoria si vede subito:
```bash
docker stats --no-stream                                   # il container del worker: la memoria non cresce pagina dopo pagina
findstr "elaborata" storage\logs\laravel.log               # (cmd.exe) una riga per pagina con "memoria_picco_mb"
```
Atteso: `memoria_picco_mb` piatto (poche decine di MB) dalla pagina 1 all'ultima, nessun "Allowed memory size", `failed_jobs` vuota, e alla fine il messaggio Telegram "Scan completato: N carte elaborate, M nuove, K problemi." con **una sola** mail `NewCardsEmail` (elenco di 100 carte più "…e altre N").
Prova anche la ripresa: ferma il worker a metà (`docker compose -f docker-compose.dev.yml stop worker`), poi o riavvii il worker (il job riservato torna disponibile dopo `retry_after`, 300 s, e la catena riprende da sola) oppure liberi il lock (`php artisan cache:forget cards-import:running`) e rilanci con `cards:scan --page=<pagina raggiunta>`.

Attenzione in produzione: un deploy che ricrea il container `worker` (piano 11) durante uno scan lo interrompe a metà pagina. Non si perde niente (l'import è idempotente e il job riservato viene ripreso dopo `retry_after`, consumando un tentativo), ma conviene non fare deploy mentre lo scan è in corso, e soprattutto non durante il reimport completo dello Step 13.4 del piano 13.

## Step 8 — Documentazione

1. **README.md**: sezione "Scansione carte" (punti 3 e 4: il job lavora a pagine, riparte con `cards:scan --page=N`, lock, mail con tetto) e "Non ancora implementato" (togliere il problema noto e le rifiniture del job di import). Aggiungi `DB_QUEUE_RETRY_AFTER` alla tabella delle variabili.
2. **todo.md**: spunta la sezione "Import carte per pagina" e le voci 5.5.6–5.5.8 che questo piano chiude (5.5.6 dopo aver rilanciato la suite).
3. **Indice dei piani**: stato di questo piano a completato.
4. Rinomina questo file in `implementationPlan-V-12-importCartePerPagina.md`.
5. Riprendi lo Step 13.4 del piano 13: il prerequisito "import completo funzionante" è soddisfatto quando lo Step 7 qui sopra riesce.
