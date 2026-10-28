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
 * (the last page also sends the final notifications). Memory stays flat: no job holds more than PAGE_SIZE (100) cards.
 * Importa UNA pagina dell'elenco carte ufficiale e poi accoda sé stesso per la successiva
 * (l'ultima pagina manda anche le notifiche finali). La memoria resta piatta: nessun job tiene più di PAGE_SIZE (100) carte.
 */
class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public const API_URL = 'https://admin.starwarsunlimited.com/api/card-list';

    public const PHASE_ORIGINALS = 'originals';

    public const PHASE_VARIANTS = 'variants';

    public const PAGE_SIZE = 100;

    /** Chiave del lock: contiene il runId dello scan in corso. */
    public const LOCK_KEY = 'cards-import:running';

    /** Il lock scade da solo se uno scan muore senza liberarlo. */
    public const LOCK_SECONDS = 10800;

    public const EMAIL_CARDS_LIMIT = 100;

    public const EMAIL_ERRORS_LIMIT = 50;

    // Una pagina (100 carte, fino a 200 immagini) deve stare nei 240 s di timeout,
    // e retry_after (DB_QUEUE_RETRY_AFTER, Step 5) maggiore del timeout.
    public $tries = 3;

    public $backoff = [60, 180];

    public $timeout = 240;

    /** Identifica lo scan: resta uguale su tutte le pagine e su ogni tentativo. */
    public string $runId;

    /** True solo per il job che avvia lo scan (manda il messaggio "Scan avviato..."). */
    public bool $startsRun;

    public function __construct(public int $page = 1, ?string $runId = null, public string $phase = self::PHASE_ORIGINALS)
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

        $variantFilter = $this->phase === self::PHASE_VARIANTS ? '$notNull' : '$null';

        $response = Http::timeout(30)->retry(3, 2000, throw: false)->get(self::API_URL, [
            'locale' => 'it',
            "filters[variantOf][id][{$variantFilter}]" => 'true',
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
            self::dispatch($this->page + 1, $this->runId, $this->phase);

            return;
        }

        if ($this->phase === self::PHASE_ORIGINALS) {
            // Finiti gli originali: parte la fase varianti dalla pagina 1
            self::dispatch(1, $this->runId, self::PHASE_VARIANTS);

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

            $expansionCode = $this->resolveExpansionCode($cardData);
            $this->createExpansionIfMissing($expansionCode, $cardData['expansion']['data']['attributes'] ?? null, $latestRotation);

            $variantAttributes = [];
            if ($this->phase === self::PHASE_VARIANTS) {
                $vType = $cardData['variantTypes']['data'][0]['attributes']['name'] ?? null;
                $vCode = $cardData['variantTypes']['data'][0]['attributes']['variantId'] ?? null;

                // Fallback per foil senza variantTypes nominale (es. Mina Bonteri)
                if (! $vType && ! empty($cardData['hasFoil'])) {
                    $vType = 'Foil';
                }

                $variantAttributes = [
                    'original_card_id' => $this->resolveOriginalCardId($cardData),
                    'variant_type' => $vType,
                    'variant_code' => $vCode,
                ];
            }

            $card = Card::updateOrCreate(
                ['cid' => $cid],
                $variantAttributes + [
                    'expansion' => $expansionCode,
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
                    'max_copies' => $cardData['cardNumber'] == 256 && $expansionCode == 'JTL' ? 15 : null,
                ]
            );

            $this->syncAspectsAndTraits($card, $cardData);
            $this->downloadImages($card, $cardData, $imageDownloader);

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

    private function createExpansionIfMissing(string $expansionCode, ?array $rawExpansionData, string $latestRotation): void
    {
        if (! Expansion::where('expansion', $expansionCode)->exists()) {
            // Se è un child set (es. SOROP, TSOR, JTLP), individua il core set genitore
            $mainExpansion = null;
            if (preg_match('/^(T)?(SOR|SHD|TWI|JTL|LOF|SEC|LAW|HMW|ASH)/', $expansionCode, $m)) {
                $mainExpansion = $m[2];
            }

            Expansion::create([
                'expansion' => $expansionCode,
                'rotation' => $latestRotation,
                'legal_date' => $rawExpansionData['publishedAt'] ?? now()->toDateString(),
                'confirmed' => false,
                'group_main_expansion' => $mainExpansion,
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
        if ($this->phase === self::PHASE_VARIANTS && ! config('services.swu.variant_images')) {
            return;
        }

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

    /**
     * Resolves the canonical expansion code for a card, separating promo sets like SWUDB
     * Risolve il codice espansione canonico per una carta, separando i set promo come fa SWUDB
     */
    private function resolveExpansionCode(array $cardData): string
    {
        $rawExp = $cardData['expansion']['data']['attributes']['code'] ?? null;
        $vTypeName = $cardData['variantTypes']['data'][0]['attributes']['name'] ?? '';
        $vId = $cardData['variantTypes']['data'][0]['attributes']['variantId'] ?? '';
        $serial = $cardData['serialCode'] ?? '';
        $cardNum = (int) ($cardData['cardNumber'] ?? 0);

        // 1. Segnalini (Tokens): T{EXP}
        if (str_contains($cardData['type']['data']['attributes']['name'] ?? '', 'Segnalino')) {
            return 'T'.($rawExp ?: 'SOR');
        }

        // 2. Set promo già ufficiali o standalone (JTLP, SECP, LOFP, C24, J24, P25, etc.)
        if ($rawExp && ! in_array($rawExp, ['SOR', 'SHD', 'TWI'], true)) {
            return $rawExp;
        }

        // 3. Determinazione del set genitore (fallback se rawExp è null come per Ammiraglio Motti)
        $parentSet = $rawExp;
        if (! $parentSet) {
            $parentSet = match (true) {
                str_starts_with($serial, '01') => 'SOR',
                str_starts_with($serial, '02') => 'SHD',
                str_starts_with($serial, '03') => 'TWI',
                default => 'SOR',
            };
        }

        // 4. Mappatura promozionale stile SWUDB per i primi 3 set (SOR, SHD, TWI):

        // Prerelease (vId 04 o nome Prerelease) -> SORPR, SHDPR, TWIPR
        if ($vId === '04' || str_contains($vTypeName, 'Prerelease')) {
            if (str_contains($vTypeName, 'Judge')) {
                return $parentSet === 'SOR' ? 'J24' : 'J25';
            }

            return "{$parentSet}PR";
        }

        // Planetary Qualifier (vId 51, 52, 56 o sigla PQ) -> SHDPQ, TWIPQ
        if (in_array($vId, ['51', '52', '56'], true) || str_contains($vTypeName, 'PQ') || str_contains($vTypeName, 'Planetary')) {
            return "{$parentSet}PQ";
        }

        // Store Showdown (vId 40..45, 63..66 o sigla SS) -> SORSH, SHDSH, TWISH
        if (in_array($vId, ['40', '41', '42', '43', '44', '45', '63', '64', '65', '66'], true) || str_contains($vTypeName, 'SS') || str_contains($vTypeName, 'Store Showdown')) {
            return "{$parentSet}SH";
        }

        // Weekly Play / Organized Play (vId 60, 35) -> SOROP, SHDOP, TWIOP
        if ($vId === '60' || $vId === '35' || str_contains($vTypeName, 'Weekly Play') || str_contains($vTypeName, 'Organized Play')) {
            return "{$parentSet}OP";
        }

        // Hyperspace del 2-Player Starter Set di SOR (numeri 1-11 in Hyperspace) -> assegnati a SOROP come su SWUDB
        if ($parentSet === 'SOR' && $cardNum <= 11 && $vId === '02') {
            return 'SOROP';
        }

        // Event Promos (GenCon 2023, Essen 2024)
        if (str_contains($serial, 'GC') || str_contains($vTypeName, 'Gen Con')) {
            return 'GC23';
        }
        if (str_contains($serial, 'EE') || str_contains($vTypeName, 'Essen')) {
            return 'EE24';
        }

        return $parentSet;
    }

    private function resolveOriginalCardId(array $cardData): string
    {
        $originalCid = $cardData['variantOf']['data']['attributes']['cardUid'] ?? null;

        if (! $originalCid) {
            throw new \RuntimeException('variantOf senza cardUid nel payload');
        }

        $originalId = Card::where('cid', $originalCid)->value('id');

        if (! $originalId) {
            throw new \RuntimeException("Carta originale {$originalCid} non trovata: gli originali sono stati importati?");
        }

        return $originalId;
    }
}
