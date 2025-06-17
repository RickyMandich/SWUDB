<?php

namespace App\Http\Controllers;

use App\Events\ThreadMessageCreated;
use App\Services\ThreadManager;

use App\Mail\NewCardsEmail;

use App\Models\Card;
use App\Models\Deck;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Mail;

class CardsController extends Controller
{
    /**
     * Display a listing of cards with optional filtering by expansion and name
     * Mostra l'elenco delle carte con filtri opzionali per espansione e nome
     *
     * This method handles the main cards listing page with search functionality.
     * It supports filtering by expansion code and card name, with automatic sorting.
     *
     * @param Request $request The HTTP request containing search parameters
     * @param string|null $espansione Optional expansion code to filter by
     * @return \Illuminate\View\View The cards index view with filtered results
     */
    public function index(Request $request){
        $get = $request->all();
        if(!isset($get["nome"])){
            $get["nome"] = "";
        }
        $title = "Carte";
        return view('carte.index', [
            "nome" => $get["nome"],
            "title" => $title,
        ]);
    }

    /**
     * Compare two specific cards and return detailed comparison output
     * Confronta due carte specifiche e restituisce un output dettagliato del confronto
     *
     * This method is primarily used for debugging the card sorting algorithm.
     * It retrieves two cards and runs them through the comparison logic with verbose output.
     *
     * @param string $espansione1 First card's expansion code
     * @param int $numero1 First card's number
     * @param string $espansione2 Second card's expansion code
     * @param int $numero2 Second card's number
     * @return string Debug output showing comparison details or error message
     */
    public function compare($espansione1, $numero1, $espansione2, $numero2) {
        if(isset($espansione1) and isset($numero1) and isset($espansione2) and isset($numero2)){
            $cards = Card::where(function($query) use ($espansione1, $numero1) {
                $query->where('espansione', $espansione1)
                    ->where('numero', $numero1);
            })->orWhere(function($query) use ($espansione2, $numero2) {
                $query->where('espansione', $espansione2)
                    ->where('numero', $numero2);
            })->get();
            ob_start();
            CardsController::mergeSort($cards, true);
            $output = ob_get_clean();
            return view("carte.update", ["output" => $output]);
        }else{
            return "Please provide espansione1, numero1, espansione2, and numero2 in the query parameters.";
        }
    }

    /**
     * API endpoint to retrieve a single card by expansion and number
     * Endpoint API per recuperare una singola carta tramite espansione e numero
     *
     * @param string $espansione The expansion code
     * @param int $numero The card number
     * @return \App\Models\Card|null The card model or null if not found
     */
    public function api($espansione, $numero){
        return Card::where('numero', $numero)->where('espansione', $espansione)->first();
    }

    /**
     * API endpoint to retrieve all cards from a specific expansion with count
     * Endpoint API per recuperare tutte le carte di una specifica espansione con conteggio
     *
     * @param string $espansione The expansion code
     * @return array Array containing [count, collection] of cards
     */
    public function apis($espansione){
        $ret = Card::where('espansione', $espansione)->get();
        return [$ret->count(), $ret];
    }

    /**
     * Display a single card with navigation to previous/next cards
     * Mostra una singola carta con navigazione verso carte precedenti/successive
     *
     * @param string $espansione The expansion code
     * @param int $numero The card number
     * @return \Illuminate\View\View The card detail view with navigation
     */
    public function show($espansione, $numero){
        $carta = Card::where('numero', $numero)->where('espansione', $espansione)->first();
        $next = Card::where('numero', '>', $numero)->where('espansione', $espansione)->orderBy('numero')->first();
        $back = Card::where('numero', '<', $numero)->where('espansione', $espansione)->orderByDesc('numero')->first();
        return view('carte.show', ["carta" => $carta, "numero" => $numero, "espansione" => $espansione, "next" => $next, "back" => $back]);
    }
    
    /**
     * Get the scan log file path for current session
     * Ottiene il percorso del file di log per la sessione corrente
     *
     * @return string Log file path
     */
    private function getScanLogPath()
    {
        $timestamp = now()->format('Y_m_d_H_i');
        return storage_path("logs/scansione_{$timestamp}.log");
    }

    /**
     * Write to scan log file (equivalent to Java System.out.println)
     * Scrive nel file di log della scansione (equivalente a Java System.out.println)
     *
     * @param string $message Message to log
     * @param string|null $logFile Optional specific log file path
     * @return void
     */
    private function writeScanLog($message, $logFile = null)
    {
        if (!$logFile) {
            $logFile = $this->getScanLogPath();
        }

        $timestamp = now()->format('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";

        // Ensure logs directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to Laravel log for debugging
        Log::info("SCAN: {$message}");
    }

    /**
     * Retrieve all card IDs from the Star Wars Unlimited API
     * Recupera tutti gli ID delle carte dall'API di Star Wars Unlimited
     *
     * This method calls the official API to get a complete list of all card IDs,
     * similar to the Java implementation's scan functionality.
     *
     * @param string $threadId Thread ID for messaging
     * @param string $logFile Log file path for this session
     * @return array Array of card IDs (cid values)
     */
    private function getAllCardIdsFromAPI($threadId, $logFile)
    {
        $allCardIds = [];
        $page = 1;
        $pageFinished = false;

        $this->writeScanLog("Inizio recupero tutti i CID", $logFile);

        while (!$pageFinished) {
            $url = "https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][\$null]=true&pagination[page]={$page}&pagination[pageSize]=10";

            $this->writeScanLog("Chiamata API pagina {$page}: {$url}", $logFile);

            try {
                $response = Http::timeout(30)->get($url);

                if (!$response->successful()) {
                    $errorMsg = "Errore API alla pagina {$page}: " . $response->status();
                    $this->writeScanLog($errorMsg, $logFile);
                    $this->sendTelegramAlert($errorMsg);
                    ThreadMessageCreated::dispatch($threadId, $errorMsg);
                    break;
                }

                $jsonData = $response->json();
                $cards = $jsonData['data'] ?? [];

                $this->writeScanLog("Pagina {$page}: trovate " . count($cards) . " carte", $logFile);

                foreach ($cards as $card) {
                    $cardId = $card['attributes']['cardUid'] ?? null;
                    if ($cardId) {
                        $allCardIds[] = $cardId;
                        $this->writeScanLog("Trovato CID: {$cardId}", $logFile);
                    }
                }

                // Check if we've reached the last page
                $pagination = $jsonData['meta']['pagination'] ?? [];
                $currentPage = $pagination['page'] ?? $page;
                $totalPages = $pagination['pageCount'] ?? $page;

                $pageFinished = $currentPage >= $totalPages;
                $this->writeScanLog("Pagina {$currentPage} di {$totalPages} completata", $logFile);

                if ($page % 5 === 0) {
                    ThreadMessageCreated::dispatch($threadId, "Elaborate {$page} pagine API...");
                }

                $page++;

            } catch (\Exception $e) {
                $errorMsg = "Eccezione durante chiamata API pagina {$page}: " . $e->getMessage();
                $this->writeScanLog($errorMsg, $logFile);
                $this->sendTelegramAlert($errorMsg);
                ThreadMessageCreated::dispatch($threadId, $errorMsg);
                break;
            }
        }

        $finalMsg = "Completato recupero CID: " . count($allCardIds) . " carte totali";
        $this->writeScanLog($finalMsg, $logFile);
        return $allCardIds;
    }

    /**
     * Retrieve detailed card information from API using card ID
     * Recupera informazioni dettagliate della carta dall'API usando l'ID carta
     *
     * @param string $cardId The card ID (cid)
     * @param string|null $logFile Log file path for this session
     * @return array|null Card data array or null if failed
     */
    private function getCardDetailsFromAPI($cardId, $logFile = null)
    {
        $url = "https://admin.starwarsunlimited.com/api/card/{$cardId}?locale=it";

        if ($logFile) {
            $this->writeScanLog("Chiamata API dettagli carta: {$url}", $logFile);
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                $errorMsg = "Errore recupero dettagli carta {$cardId}: " . $response->status();
                if ($logFile) {
                    $this->writeScanLog($errorMsg, $logFile);
                }
                Log::error($errorMsg);
                return null;
            }

            $jsonData = $response->json();
            $data = $jsonData['data'] ?? null;

            if (!$data) {
                $errorMsg = "Nessun dato trovato per carta {$cardId}";
                if ($logFile) {
                    $this->writeScanLog($errorMsg, $logFile);
                }
                Log::error($errorMsg);
                return null;
            }

            $cardData = $this->parseCardDataFromAPI($data, $cardId);

            if ($logFile && $cardData) {
                $cardInfo = ($cardData['nome'] ?? 'N/A') . " " . ($cardData['titolo'] ?? '') .
                           " (" . ($cardData['espansione'] ?? 'N/A') . "-" . ($cardData['numero'] ?? 'N/A') . ")";
                $this->writeScanLog("Carta elaborata con successo: {$cardInfo}", $logFile);
            }

            return $cardData;

        } catch (\Exception $e) {
            $errorMsg = "Eccezione durante recupero dettagli carta {$cardId}: " . $e->getMessage();
            if ($logFile) {
                $this->writeScanLog($errorMsg, $logFile);
            }
            Log::error($errorMsg);
            return null;
        }
    }

    /**
     * Parse card data from API response into database format
     * Analizza i dati della carta dalla risposta API nel formato del database
     *
     * @param array $apiData Raw API response data
     * @param string $cardId Card ID
     * @return array Parsed card data ready for database insertion
     */
    private function parseCardDataFromAPI($apiData, $cardId)
    {
        $attributes = $apiData['attributes'] ?? [];

        // Extract basic information
        $cardData = [
            'cid' => $cardId,
            'nome' => $attributes['title'] ?? null,
            'titolo' => $attributes['subtitle'] ?? '',
            'unica' => $attributes['unique'] ?? false,
            'numero' => $attributes['cardNumber'] ?? 0,
            'descrizione' => $attributes['textStyled'] ?? null,
            'costo' => $attributes['cost'] ?? 0,
            'vita' => $attributes['hp'] ?? 0,
            'potenza' => $attributes['power'] ?? 0,
            'artista' => $attributes['artist'] ?? null,
            'uscita' => isset($attributes['publishedAt']) ? explode('T', $attributes['publishedAt'])[0] : '',
        ];

        // Extract expansion
        $expansion = $attributes['expansion']['data']['attributes'] ?? [];
        $cardData['espansione'] = $expansion['code'] ?? null;

        // Extract arena
        $arenas = $attributes['arenas']['data'] ?? [];
        $cardData['arena'] = !empty($arenas) ? ($arenas[0]['attributes']['name'] ?? null) : null;

        // Extract aspects
        $aspects = $attributes['aspects']['data'] ?? [];
        if (!empty($aspects)) {
            $cardData['aspettoPrimario'] = $this->translateAspect($aspects[0]['attributes']['name'] ?? '');
        }
        if (count($aspects) > 1) {
            $cardData['aspettoSecondario'] = $this->translateAspect($aspects[1]['attributes']['name'] ?? '');
        }

        // Handle aspect duplicates
        $aspectDuplicates = $attributes['aspectDuplicates']['data'] ?? [];
        if (!empty($aspectDuplicates) && !empty($aspects)) {
            $cardData['aspettoSecondario'] = $this->translateAspect($aspects[0]['attributes']['name'] ?? '');
        }

        // Extract type
        $type = $attributes['type']['data']['attributes'] ?? [];
        $cardData['tipo'] = $type['name'] ?? '';

        // Extract traits
        $traits = $attributes['traits']['data'] ?? [];
        $traitNames = [];
        foreach ($traits as $trait) {
            $traitNames[] = $trait['attributes']['name'] ?? '';
        }
        $cardData['tratti'] = implode(' * ', $traitNames);

        // Extract rarity
        $rarity = $attributes['rarity']['data']['attributes'] ?? [];
        $cardData['rarita'] = $rarity['name'] ?? null;

        // Extract art URLs
        $frontArt = $attributes['artFront']['data']['attributes'] ?? [];
        $cardData['frontArt'] = $frontArt['url'] ?? null;

        // Handle Leader cards with back art and special description
        if ($cardData['tipo'] === 'Leader') {
            $backArt = $attributes['artBack']['data']['attributes'] ?? [];
            $cardData['backArt'] = $backArt['url'] ?? null;

            $deployText = $attributes['deployBoxStyled'] ?? null;
            if ($deployText) {
                $cardData['descrizione'] = "<strong>-----NON SCHIERATO-----</strong><br>" .
                                         $cardData['descrizione'] .
                                         "<strong>-----SCHIERATO-----</strong><br>" .
                                         $deployText;
            }
        }

        // Handle aspect ordering (secondary should be Nero/Bianco if different from primary)
        if (isset($cardData['aspettoPrimario']) && isset($cardData['aspettoSecondario'])) {
            if ($cardData['aspettoPrimario'] !== $cardData['aspettoSecondario'] &&
                !in_array($cardData['aspettoSecondario'], ['Bianco', 'Nero']) &&
                !empty($cardData['aspettoSecondario'])) {
                // Swap primary and secondary
                $temp = $cardData['aspettoPrimario'];
                $cardData['aspettoPrimario'] = $cardData['aspettoSecondario'];
                $cardData['aspettoSecondario'] = $temp;
            }
        }

        // Add unique symbol to name if unique
        if ($cardData['unica']) {
            $cardData['nome'] = "⟡" . $cardData['nome'];
        }

        // Handle token cards
        if (strpos($cardData['tipo'], 'Segnalin') !== false) {
            $cardData['espansione'] = "T" . $cardData['espansione'];
        }

        return $cardData;
    }

    /**
     * Translate aspect names from English to Italian
     * Traduce i nomi degli aspetti dall'inglese all'italiano
     *
     * @param string $aspect English aspect name
     * @return string Italian aspect name
     */
    private function translateAspect($aspect)
    {
        $translations = [
            'Vigilanza' => 'Blu',
            'Malvagità' => 'Nero',
            'Eroismo' => 'Bianco',
            'Autorità' => 'Verde',
            'Offensiva' => 'Rosso',
            'Astuzia' => 'Giallo',
        ];

        return $translations[$aspect] ?? $aspect;
    }

    /**
     * Send alert message via Telegram
     * Invia messaggio di avviso tramite Telegram
     *
     * @param string $message Message to send
     * @return void
     */
    private function sendTelegramAlert($message)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN', '7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc');
        $chatId = env('TELEGRAM_CHAT_ID', '5533337157');

        try {
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            Http::post($url, [
                'chat_id' => $chatId,
                'text' => $message
            ]);
            Log::info("Telegram alert sent: {$message}");
        } catch (\Exception $e) {
            Log::error("Failed to send Telegram alert: " . $e->getMessage());
        }
    }

    /**
     * Start the card import process from external JSON source and API
     * Avvia il processo di importazione delle carte da sorgente JSON esterna e API
     *
     * This method handles the complete card import workflow:
     * 1. Fetches card IDs from Star Wars Unlimited API
     * 2. Compares with existing database cards to find new ones
     * 3. Retrieves detailed information for new cards via API
     * 4. Falls back to JSON file if API fails
     * 5. Saves them to temporary file and triggers batch processing
     * 6. Sends email notifications to all users about new cards
     *
     * @return \Illuminate\View\View The update result view with import statistics
     */
    public function startImport(){
        Log::info("Starting card import process with API integration");
        $this->sendTelegramAlert("Inizio scansione nuove carte tramite API");

        // Generate a thread ID for this import session
        $threadId = ThreadManager::generateThreadId('import');
        ThreadMessageCreated::dispatch($threadId, "Avvio scansione carte tramite API");

        // Launch the API scan process in background
        JobController::fireAndForgetGet(route('carte.scanAPI', ['threadId' => $threadId]), [
            "token" => env('JOB_TOKEN')
        ]);

        return view("carte.update", [
            "result" => true,
            "count" => "In elaborazione...",
            "data" => [],
            "apiUsed" => true,
            "message" => "Scansione API avviata in background. Riceverai notifiche sui progressi.",
            "threadId" => $threadId
        ]);
    }

    /**
     * Scan API for new cards and process them (background job)
     * Scansiona l'API per nuove carte e le elabora (job in background)
     *
     * This method runs in background and handles the complete workflow:
     * 1. Gets all card IDs from API (WAITS for completion)
     * 2. Compares with database to find new cards
     * 3. Only AFTER scan is complete, retrieves detailed information
     * 4. Falls back to JSON if needed
     * 5. Triggers the import process
     *
     * @param Request $request HTTP request containing threadId and token
     * @return void Outputs status messages directly
     */
    public function scanAPI(Request $request){
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

        $threadId = $request->input('threadId', ThreadManager::generateThreadId('import'));
        $logFile = $this->getScanLogPath();
        $apiSuccess = false;

        $this->writeScanLog("=== INIZIO SCANSIONE API ===", $logFile);
        $this->writeScanLog("Thread ID: {$threadId}", $logFile);

        try {
            ThreadMessageCreated::dispatch($threadId, "Recupero lista carte dall'API...");

            // Step 1: Get all card IDs from API (COMPLETE scan before proceeding)
            $this->writeScanLog("Inizio recupero completo ID carte dall'API", $logFile);
            $allCardIds = $this->getAllCardIdsFromAPI($threadId, $logFile);

            if (!empty($allCardIds)) {
                $this->writeScanLog("SCANSIONE API COMPLETATA: " . count($allCardIds) . " ID carte recuperati", $logFile);
                ThreadMessageCreated::dispatch($threadId, "✅ Scansione API completata: " . count($allCardIds) . " carte trovate");

                // Step 2: Get existing cards from database
                $this->writeScanLog("Recupero carte esistenti dal database", $logFile);
                $dbCards = Card::select('cid')->whereNotNull('cid')->pluck('cid')->toArray();
                $this->writeScanLog("Carte esistenti nel DB: " . count($dbCards), $logFile);

                // Step 3: Find new card IDs
                $newCardIds = array_diff($allCardIds, $dbCards);
                $this->writeScanLog("Nuove carte da elaborare: " . count($newCardIds), $logFile);

                if (!empty($newCardIds)) {
                    ThreadMessageCreated::dispatch($threadId, "Trovate " . count($newCardIds) . " nuove carte da elaborare");

                    // Log each new card ID
                    foreach ($newCardIds as $index => $cardId) {
                        $this->writeScanLog(($index + 1) . ")\t" . $cardId, $logFile);
                    }

                    // Save new card IDs and log file path for background processing
                    $processData = [
                        'cardIds' => $newCardIds,
                        'logFile' => $logFile,
                        'threadId' => $threadId
                    ];
                    file_put_contents(storage_path("app/new_cards_process.json"), json_encode($processData));

                    $this->writeScanLog("=== FINE SCANSIONE, INIZIO ELABORAZIONE DETTAGLI ===", $logFile);

                    // NOW launch detailed card processing (only after scan is complete)
                    JobController::fireAndForgetGet(route('carte.processNewCards', ['threadId' => $threadId]), [
                        "token" => env('JOB_TOKEN')
                    ]);

                    $apiSuccess = true;
                } else {
                    $this->writeScanLog("Nessuna nuova carta trovata", $logFile);
                    ThreadMessageCreated::dispatch($threadId, "Nessuna nuova carta trovata tramite API", true);
                }
            } else {
                $this->writeScanLog("ERRORE: Nessun ID carta recuperato dall'API", $logFile);
                ThreadMessageCreated::dispatch($threadId, "Errore nel recupero carte dall'API, provo con JSON");
            }
        } catch (\Exception $e) {
            $errorMsg = "Errore scansione API: " . $e->getMessage();
            $this->writeScanLog("ERRORE CRITICO: " . $errorMsg, $logFile);
            Log::error("API scan failed: " . $e->getMessage());
            ThreadMessageCreated::dispatch($threadId, $errorMsg);
        }

        // If API failed or no new cards, try JSON fallback
        if (!$apiSuccess) {
            $this->writeScanLog("Avvio fallback su file JSON", $logFile);
            $this->fallbackToJSON($threadId, $logFile);
        }
    }

    /**
     * Process new cards found via API (background job)
     * Elabora le nuove carte trovate tramite API (job in background)
     *
     * This method runs ONLY after the API scan is completely finished
     *
     * @param Request $request HTTP request containing threadId and token
     * @return void
     */
    public function processNewCards(Request $request){
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

        $threadId = $request->input('threadId', ThreadManager::generateThreadId('import'));

        try {
            // Read process data from temporary file (includes cardIds, logFile, threadId)
            $processData = json_decode(file_get_contents(storage_path("app/new_cards_process.json")), true);

            if (empty($processData) || empty($processData['cardIds'])) {
                ThreadMessageCreated::dispatch($threadId, "Nessun ID carta da elaborare");
                return;
            }

            $newCardIds = $processData['cardIds'];
            $logFile = $processData['logFile'];

            $this->writeScanLog("=== INIZIO ELABORAZIONE DETTAGLI CARTE ===", $logFile);
            $this->writeScanLog("Carte da elaborare: " . count($newCardIds), $logFile);

            ThreadMessageCreated::dispatch($threadId, "Inizio elaborazione dettagli per " . count($newCardIds) . " carte");

            $toInsert = [];
            $processedCount = 0;
            $batchSize = 10; // Process in smaller batches

            foreach ($newCardIds as $index => $cardId) {
                $this->writeScanLog("Elaborazione carta " . ($index + 1) . "/" . count($newCardIds) . ": {$cardId}", $logFile);

                $cardData = $this->getCardDetailsFromAPI($cardId, $logFile);
                if ($cardData) {
                    $toInsert[] = $cardData;
                    $processedCount++;

                    $cardInfo = ($cardData['espansione'] ?? 'N/A') . "-" . ($cardData['numero'] ?? 'N/A') . " " . ($cardData['nome'] ?? 'N/A');
                    $this->writeScanLog("✅ Carta elaborata: {$cardInfo}", $logFile);

                    if ($processedCount % $batchSize === 0) {
                        ThreadMessageCreated::dispatch($threadId, "Elaborate {$processedCount}/" . count($newCardIds) . " carte");
                        $this->writeScanLog("Progresso: {$processedCount}/" . count($newCardIds) . " carte elaborate", $logFile);

                        // Small delay to avoid overwhelming the API
                        usleep(500000); // 500ms delay every 10 cards
                    }
                } else {
                    $this->writeScanLog("❌ Errore elaborazione carta: {$cardId}", $logFile);
                }

                // Small delay between each card
                usleep(100000); // 100ms delay
            }

            $this->writeScanLog("=== ELABORAZIONE COMPLETATA ===", $logFile);
            $this->writeScanLog("Carte elaborate con successo: " . count($toInsert) . "/" . count($newCardIds), $logFile);

            ThreadMessageCreated::dispatch($threadId, "Completata elaborazione: " . count($toInsert) . " carte pronte per importazione");

            if (!empty($toInsert)) {
                // Save to import file and trigger import process
                file_put_contents(storage_path("app/to_insert.json"), json_encode($toInsert));
                $this->writeScanLog("Carte salvate in to_insert.json per importazione", $logFile);

                JobController::fireAndForgetGet(route('carte.sendBatch', ['threadId' => $threadId]), [
                    "token" => env('JOB_TOKEN')
                ]);

                // Send email notifications
                $this->sendEmailNotifications($toInsert);
                $this->sendTelegramAlert("Importazione avviata per " . count($toInsert) . " nuove carte");
                $this->writeScanLog("Importazione avviata e notifiche inviate", $logFile);

                // Mark thread as complete since the scan and processing is done
                ThreadMessageCreated::dispatch($threadId, "✅ Scansione completata! Importazione di " . count($toInsert) . " carte avviata", true);
            } else {
                $this->writeScanLog("Nessuna carta da importare", $logFile);
                ThreadMessageCreated::dispatch($threadId, "Nessuna carta da importare", true);
            }

        } catch (\Exception $e) {
            $errorMsg = "Errore elaborazione carte: " . $e->getMessage();
            Log::error($errorMsg);
            ThreadMessageCreated::dispatch($threadId, $errorMsg);

            if (isset($logFile)) {
                $this->writeScanLog("ERRORE CRITICO: " . $errorMsg, $logFile);
                // Fallback to JSON
                $this->fallbackToJSON($threadId, $logFile);
            }
        }
    }

    /**
     * Fallback to JSON file import when API fails
     * Fallback su importazione file JSON quando l'API fallisce
     *
     * @param string $threadId Thread ID for messaging
     * @param string|null $logFile Log file path for this session
     * @return void
     */
    private function fallbackToJSON($threadId, $logFile = null){
        if ($logFile) {
            $this->writeScanLog("=== INIZIO FALLBACK SU FILE JSON ===", $logFile);
        }

        ThreadMessageCreated::dispatch($threadId, "Fallback su file JSON...");

        try {
            $url = 'http://swudb.altervista.org/collezione.json';
            if ($logFile) {
                $this->writeScanLog("Download file JSON da: {$url}", $logFile);
            }

            $json = file_get_contents($url);
            $fullSet = json_decode($json, true);

            if ($fullSet) {
                if ($logFile) {
                    $this->writeScanLog("File JSON scaricato: " . count($fullSet) . " carte totali", $logFile);
                }

                $dbSet = Card::select('espansione', 'numero')->get()->toArray();
                $toInsert = [];

                if ($logFile) {
                    $this->writeScanLog("Confronto con database: " . count($dbSet) . " carte esistenti", $logFile);
                }

                foreach ($fullSet as $card) {
                    if (!$this::contain($dbSet, $card)) {
                        // Ensure tratti is a string for JSON compatibility
                        if (is_array($card["tratti"])) {
                            $card["tratti"] = implode(" * ", $card["tratti"]);
                        }
                        $toInsert[] = $card;

                        if ($logFile) {
                            $cardInfo = ($card['espansione'] ?? 'N/A') . "-" . ($card['numero'] ?? 'N/A') . " " . ($card['nome'] ?? 'N/A');
                            $this->writeScanLog("Nuova carta trovata: {$cardInfo}", $logFile);
                        }
                    }
                }

                $resultMsg = "JSON fallback: trovate " . count($toInsert) . " nuove carte";
                if ($logFile) {
                    $this->writeScanLog($resultMsg, $logFile);
                }
                Log::info($resultMsg);
                ThreadMessageCreated::dispatch($threadId, $resultMsg);

                if (!empty($toInsert)) {
                    file_put_contents(storage_path("app/to_insert.json"), json_encode($toInsert));

                    if ($logFile) {
                        $this->writeScanLog("Carte salvate per importazione", $logFile);
                    }

                    JobController::fireAndForgetGet(route('carte.sendBatch', ['threadId' => $threadId]), [
                        "token" => env('JOB_TOKEN')
                    ]);

                    $this->sendEmailNotifications($toInsert);

                    if ($logFile) {
                        $this->writeScanLog("Importazione avviata e notifiche inviate", $logFile);
                    }

                    // Mark thread as complete since the scan and processing is done
                    ThreadMessageCreated::dispatch($threadId, "✅ Scansione completata! Importazione di " . count($toInsert) . " carte avviata (JSON fallback)", true);
                } else {
                    ThreadMessageCreated::dispatch($threadId, "Nessuna nuova carta trovata", true);
                    if ($logFile) {
                        $this->writeScanLog("Nessuna nuova carta da importare", $logFile);
                    }
                }
            } else {
                $errorMsg = "Errore nel parsing del file JSON";
                if ($logFile) {
                    $this->writeScanLog($errorMsg, $logFile);
                }
                ThreadMessageCreated::dispatch($threadId, $errorMsg, true);
            }
        } catch (\Exception $e) {
            $errorMsg = "Errore anche nel fallback JSON: " . $e->getMessage();
            if ($logFile) {
                $this->writeScanLog("ERRORE CRITICO FALLBACK: " . $errorMsg, $logFile);
            }
            Log::error($errorMsg);
            ThreadMessageCreated::dispatch($threadId, $errorMsg, true);
        }
    }

    /**
     * Send email notifications to all users about new cards
     * Invia notifiche email a tutti gli utenti sulle nuove carte
     *
     * @param array $toInsert Array of new cards to notify about
     * @return void
     */
    private function sendEmailNotifications($toInsert){
        // Prepare cards data with links for email template
        $cardsData = [];
        foreach($toInsert as $card){
            $espansione = $card["espansione"] ?? 'N/A';
            $numero = $card["numero"] ?? 'N/A';
            $nome = $card["nome"] ?? 'N/A';
            $titolo = $card["titolo"] ?? '';

            $cardsData[] = [
                'espansione' => $espansione,
                'numero' => $numero,
                'nome' => $nome,
                'titolo' => $titolo,
                'snippet' => "{$espansione}-{$numero} - {$nome}" . ($titolo ? " {$titolo}" : ""),
                'url' => route('carta', ['espansione' => $espansione, 'numero' => $numero])
            ];
        }

        $users = User::select("email")->where('email', '!=', null)->get();
        foreach($users as $user){
            Mail::to($user['email'])->send(new NewCardsEmail($cardsData));
        }
    }

    /**
     * Check the status of a scan process
     * Controlla lo stato di un processo di scansione
     *
     * @param string $threadId Thread ID to check
     * @return \Illuminate\Http\JsonResponse Status information
     */
    public function checkScanStatus($threadId)
    {
        // Check if the thread is still active by looking for recent messages
        $isComplete = \App\Services\ThreadManager::isThreadComplete($threadId);

        // Get the latest message for this thread
        $latestMessage = \App\Services\ThreadManager::getLatestMessage($threadId);

        return response()->json([
            'isComplete' => $isComplete,
            'latestMessage' => $latestMessage,
            'threadId' => $threadId
        ]);
    }

    /**
     * Process card import in batches to avoid timeout and memory issues
     * Elabora l'importazione delle carte in lotti per evitare timeout e problemi di memoria
     *
     * This method handles the batch processing of card imports by:
     * 1. Reading the next batch position from request
     * 2. Triggering the dispatch of current batch
     * 3. Recursively calling itself for next batch or completing import
     * 4. Uses threaded messaging to replace previous notifications
     *
     * @param Request $request HTTP request containing 'next' parameter for batch position
     * @return void Outputs status messages directly
     */
    public function sendBatch(Request $request){
        $next = intval($request->input("next", 0));
        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug.log", "sendBatch next:$next \n\n", FILE_APPEND);
        $data = json_decode(file_get_contents(storage_path("app/to_insert.json")), true);

        // Generate or retrieve thread ID for this import session
        $threadId = $request->input('threadId', ThreadManager::generateThreadId('import'));

        ThreadMessageCreated::dispatch($threadId, "Elaborazione batch $next di " . ceil(count($data) / 5));
        $batchSize = 5;
        JobController::fireAndForgetGet(route('carte.dispatchBatch', ['start' => $next, 'batchSize' => $batchSize]));
        if ($next + $batchSize >= count($data)) {
            echo "Import completato!\n";
            ThreadMessageCreated::dispatch($threadId, "Importazione completata! Elaborate " . count($data) . " carte", true);
            // file_put_contents(storage_path("app/to_insert.json"), "[]"); // Pulisce il file dopo l'importazione
        } else {
            echo "Batch $next dispatchato, prossima esecuzione tra 100ms...\n";
            $next += $batchSize;
            usleep(100000); // 100ms delay
            JobController::fireAndForgetGet(route('carte.sendBatch', ['next' => $next, 'threadId' => $threadId]), [
                "token" => env('JOB_TOKEN')
            ]);
        }
    }

    /**
     * Dispatch individual card import jobs for a specific batch
     * Invia i job individuali di importazione carte per un lotto specifico
     *
     * This method takes a slice of cards from the import queue and creates
     * individual background jobs for each card to be processed asynchronously.
     *
     * @param Request $request HTTP request containing 'start' and 'batchSize' parameters
     * @return \Illuminate\Http\JsonResponse JSON response with next batch info or completion status
     */
    public function dispatchBatch(Request $request){
        $cards = json_decode(file_get_contents(storage_path("app/to_insert.json")), true);
        $start = intval($request->input("start", 0));
        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug.log", "startBatch start:$start \n\n", FILE_APPEND);
        $batchSize = intval($request->input("batchSize", 5));
        $slice = array_slice($cards, $start, $batchSize);

        foreach($slice as $card){
            JobController::fireAndForgetGet(route('job.addCard'), [
                "card" => json_encode($card),
                "token" => env('JOB_TOKEN')
            ]);
        }

        $next = $start + $batchSize;

        if ($next >= count($cards)) {
            return response()->json(["done" => true]);
        }

        return response()->json(["next" => $next]);
    }

    /**
     * Check if a card is already contained in the given array
     * Controlla se una carta è già contenuta nell'array fornito
     *
     * This method compares cards by expansion and number to determine if
     * a card already exists in the database array during import process.
     *
     * @param array $array Array of existing cards from database
     * @param array $element Card element to check for existence
     * @return bool True if card exists, false otherwise
     */
    public static function contain($array, $element){
        foreach($array as $el){
            if($el["espansione"] === $element["espansione"] && $el["numero"] === $element["numero"]){
                return true;
            }
        }
        return false;
    }

    /**
     * Compare two card elements for sorting purposes with detailed priority rules
     * Confronta due elementi carta per l'ordinamento con regole di priorità dettagliate
     *
     * This is a complex comparison function that sorts cards by multiple criteria in order:
     * 1. User code (codUtente) - for deck ownership
     * 2. Deck name (mazzo) - for deck grouping
     * 3. Generic type (Leader, Base vs others)
     * 4. Primary aspect (Blu, Verde, Rosso, Giallo, Nero, Bianco)
     * 5. Secondary aspect (Nero, Bianco, same as primary, others)
     * 6. Specific type (Unità, Miglioria, Evento)
     * 7. Cost (costo) - ascending order, except for Leaders
     * 8. Release date (uscita) - for different expansions
     * 9. Card number (numero) - final tie-breaker
     *
     * @param array &$el1 First card element (passed by reference)
     * @param array &$el2 Second card element (passed by reference)
     * @param bool $verbose Whether to output detailed comparison steps
     * @return int -1 if el1 < el2, 1 if el1 > el2, 0 if equal
     */
    public static function compareElements(&$el1, &$el2, $verbose) {
        //definisco l'ordine dei mazzi
        $mazzoOrder = [];
        $result = Deck::select("nome as mazzo", "codUtente", "public", "id")->distinct()->orderBy("id")->get();
        foreach($result as &$line){
            array_push($mazzoOrder, $line["mazzo"]);
        }
        // Definisco l'ordine dei tipi generici
        $genericTipoOrder = ['Leader', 'Base'];
        
        // Definisco l'ordine degli aspetti primari
        $primaryAspectOrder = ['Blu', 'Verde', 'Rosso', 'Giallo', "Nero", "Bianco"];

        // Definisco l'ordine dei tipi specifici
        $specificTipoOrder = ['Unità', 'Miglioria', 'Evento'];
        
        // Funzione per ottenere il peso del mazzo
        $getMazzoWeight = function($element) use ($mazzoOrder) {
            $mazzo = $element["mazzo"];
            $index = array_search($mazzo, $mazzoOrder);
            return $index !== false ? $index : count($mazzoOrder);
        };
        
        // Funzione per ottenere il peso del tipo
        $getGenericTipoWeight = function($element) use ($genericTipoOrder) {
            $tipo = $element['tipo'];
            $index = array_search($tipo, $genericTipoOrder);
            return $index !== false ? $index : count($genericTipoOrder);
        };
        
        // Funzione per ottenere il peso dell'aspetto primario
        $getPrimaryAspectWeight = function($element) use ($primaryAspectOrder) {
            $aspetto = $element['aspettoPrimario'];
            $index = array_search($aspetto, $primaryAspectOrder);
            return $index !== false ? $index : count($primaryAspectOrder);
        };
        
        // Funzione per verificare la presenza di Dark/Light nell'aspetto secondario
        $getSecondaryAspectWeight = function($element) {
            $aspettoSecondario = $element['aspettoSecondario'];
            
            if ($aspettoSecondario === 'Nero') {
                return 0;
            }
            
            if ($aspettoSecondario === 'Bianco') {
                return 1;
            }

            if ($aspettoSecondario === $element["aspettoPrimario"]) {
                return 2;
            }
            
            return 3;
        };

        $getSpecificTipoWeight = function($element) use ($specificTipoOrder){
            $tipo = $element["tipo"];
            $index = array_search($tipo, $specificTipoOrder);
            return $index !== false ? $index : count($specificTipoOrder);
        };
        
        // faccio un confronto per utente
        if(isset($el1['codUtente']) && isset($el2['codUtente'])){
            if ($el1['codUtente'] < $el2['codUtente']) {
                if($verbose){
                    echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del codUtente del proprietario<br>";
                }
                return -1;
            }
        
            if ($el1['codUtente'] > $el2['codUtente']) {
                if($verbose){
                    echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base del codUtente del proprietario<br>";
                }
                return 1;
            }

            if($verbose){
                echo "i codici utente sono uguali(".$el1["codUtente"].")<br>";
            }
        }
        
        // Confronto per mazzo
        if(isset($el1['mazzo']) && isset($el2['mazzo'])){
            $mazzoWeight1 = $getMazzoWeight($el1);
            $mazzoWeight2 = $getMazzoWeight($el2);
            
            if ($mazzoWeight1 < $mazzoWeight2) {
                if($verbose){
                    echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del mazzo di appartenenza<br>";
                }
                return -1;
            }
            
            if ($mazzoWeight1 > $mazzoWeight2) {
                if($verbose){
                    echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base del mazzo di appartenenza<br>";
                }
                return 1;
            }

            if($verbose){
                echo "le carte sono dello stesso mazzo(".$el1["mazzo"].")<br>";
            }
        }
        
        // Confronto per tipo generico
        $tipoWeight1 = $getGenericTipoWeight($el1);
        $tipoWeight2 = $getGenericTipoWeight($el2);
        
        if ($tipoWeight1 < $tipoWeight2) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del tipo generico<br>";
            }
            return -1;
        }
        
        if ($tipoWeight1 > $tipoWeight2) {
            if($verbose){
                echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base del tipo generico<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte sono dello stesso tipo generico(".$el1["tipo"].")<br>";
        }
        
        // Se i tipi sono uguali, confronto per aspetto primario
        $primaryAspectWeight1 = $getPrimaryAspectWeight($el1);
        $primaryAspectWeight2 = $getPrimaryAspectWeight($el2);
        
        if ($primaryAspectWeight1 < $primaryAspectWeight2) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base dell'aspetto primario<br>";
            }
            return -1;
        }
        
        if ($primaryAspectWeight1 > $primaryAspectWeight2) {
            if($verbose){
                echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base dell'aspetto primario<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte hanno lo stesso aspetto primario (".$el1["aspettoPrimario"].")<br>";
        }
        
        // Se gli aspetti primari sono uguali, confronto per aspetto secondario
        $secondaryAspectWeight1 = $getSecondaryAspectWeight($el1);
        $secondaryAspectWeight2 = $getSecondaryAspectWeight($el2);
        
        if ($secondaryAspectWeight1 < $secondaryAspectWeight2) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base dell'aspetto secondario<br>";
            }
            return -1;
        }
        
        if ($secondaryAspectWeight1 > $secondaryAspectWeight2) {
            if($verbose){
                echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base dell'aspetto secondario<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le care hanno lo stesso aspetto secondario (".$el1["aspettoSecondario"].")<br>";
        }
        
        // Se aspetto secondario è uguale, confronto per tipo specifico
        $tipoWeight1 = $getSpecificTipoWeight($el1);
        $tipoWeight2 = $getSpecificTipoWeight($el2);
        
        if ($tipoWeight1 < $tipoWeight2) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del tipo specifico<br>";
            }
            return -1;
        }
        
        if ($tipoWeight1 > $tipoWeight2) {
            if($verbose){
                echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base del tipo specifico<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte hanno lo stesso tipo specifico (".$el1["tipo"].")<br>";
        }
        
        // Se tipo specifico è uguale, confronto per costo (in ordine crescente)
        if($el1["tipo"] != "Leader"){
            if ($el1["costo"] < $el2["costo"]) {
                if($verbose){
                    echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del costo<br>";
                }
                return -1;
            }
            
            if ($el1["costo"] > $el2["costo"]) {
                if($verbose){
                    echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base del costo<br>";
                }
                return 1;
            }

            if($verbose){
                echo "le carte hanno lo stesso costo (".$el1["costo"].")<br>";
            }
        }
        
        // Se nome è uguali, confronto per uscita (formato aaaa mm gg)
        if($el1["espansione"] != $el2["espansione"]){
            $compareDate = strcmp($el1['uscita'], $el2['uscita']);
            if ($compareDate < 0) {
                if($verbose){
                    echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base dell'uscita<br>";
                }
                return -1;
            }
            
            if ($compareDate > 0) {
                if($verbose){
                    echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base dell'uscita<br>";
                }
                return 1;
            }
            
            if($verbose){
                echo "le carte hanno la stessa uscita (".$el1["uscita"].")<br>";
            }
        }

        // Se la carta è uguale, confronto per numero
        if ($el1["numero"] < $el2["numero"]) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del numero<br>";
            }
            return -1;
        }
        
        if ($el1["numero"] > $el2["numero"]) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del numero<br>";
            }
            return 1;
        }

        if($verbose){
            echo "è la stessa carta<br>";
        }
        
        // Se tutti i criteri sono uguali
        return 0;
    }

    /**
     * Recursive merge sort implementation for card arrays or collections
     * Implementazione ricorsiva del merge sort per array di carte o collezioni
     *
     * This method implements the merge sort algorithm specifically designed for
     * arrays of cards or Laravel Collections. It uses the compareElements method to determine
     * the sorting order based on complex card comparison rules.
     *
     * The algorithm divides the data recursively until single elements,
     * then merges them back in sorted order using the custom comparison logic.
     * Uses Collection methods for Collections and array functions for arrays.
     *
     * @param array|\Illuminate\Support\Collection &$data Array or Collection of cards to sort (passed by reference)
     * @param bool $verbose Whether to enable verbose output during comparison
     * @return array|\Illuminate\Support\Collection The sorted data in the same format as input
     */
    public static function mergeSort(&$data, $verbose = false) {
        // Determino se l'input è una collezione o un array
        $isCollection = $data instanceof \Illuminate\Support\Collection;

        if ($isCollection) {
            // Uso le funzioni della Collection
            // Caso base: se la collezione ha 0 o 1 elemento, è già ordinata
            if ($data->count() <= 1) {
                return $data;
            }

            // Divido la collezione in due metà
            $mid = floor($data->count() / 2);
            $left = $data->take($mid);
            $right = $data->skip($mid);

            // Richiamo ricorsivamente mergeSort sulle due metà
            $left = CardsController::mergeSort($left, $verbose);
            $right = CardsController::mergeSort($right, $verbose);

            // Fondo le due metà usando Collection
            $result = collect();
            $leftIndex = 0;
            $rightIndex = 0;
            $leftArray = $left->values()->all();
            $rightArray = $right->values()->all();

            while ($leftIndex < count($leftArray) && $rightIndex < count($rightArray)) {
                // Converto temporaneamente in array per compareElements
                $leftElement = $leftArray[$leftIndex];
                $rightElement = $rightArray[$rightIndex];

                try{
                    if (CardsController::compareElements($leftElement, $rightElement, $verbose) <= 0) {
                        $result->push($leftArray[$leftIndex]);
                        $leftIndex++;
                    } else {
                        $result->push($rightArray[$rightIndex]);
                        $rightIndex++;
                    }
                }catch(\Error $e){
                    throw $e;
                }
            }

            // Aggiungo gli eventuali elementi rimanenti
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
            // Uso le funzioni degli array
            // Caso base: se l'array ha 0 o 1 elemento, è già ordinato
            if (count($data) <= 1) {
                return $data;
            }

            // Divido l'array in due metà
            $mid = floor(count($data) / 2);
            $left = array_slice($data, 0, $mid);
            $right = array_slice($data, $mid);

            // Richiamo ricorsivamente mergeSort sulle due metà
            $left = CardsController::mergeSort($left, $verbose);
            $right = CardsController::mergeSort($right, $verbose);

            // Fondo le due metà
            $result = [];
            $leftIndex = 0;
            $rightIndex = 0;

            while ($leftIndex < count($left) && $rightIndex < count($right)) {
                // Uso la funzione compareElements per confrontare
                if (CardsController::compareElements($left[$leftIndex], $right[$rightIndex], $verbose) <= 0) {
                    $result[] = $left[$leftIndex];
                    $leftIndex++;
                } else {
                    $result[] = $right[$rightIndex];
                    $rightIndex++;
                }
            }

            // Aggiungo gli eventuali elementi rimanenti di left
            while ($leftIndex < count($left)) {
                $result[] = $left[$leftIndex];
                $leftIndex++;
            }

            // Aggiungo gli eventuali elementi rimanenti di right
            while ($rightIndex < count($right)) {
                $result[] = $right[$rightIndex];
                $rightIndex++;
            }

            return $result;
        }
    }
}
