<?php

namespace App\Http\Controllers;

use App\Events\ThreadMessageCreated;
use App\Services\ThreadManager;

use App\Mail\NewCardsEmail;

use App\Models\Card;
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
            "initialNome" => $get["nome"],
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
        return storage_path("logs/scansione/{$timestamp}.log");
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

        // Ensure logs/scansione directory exists
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

        // Special case: Force numero to 100 for specific CID
        if ($cardId === '7499995534') {
            $cardData['numero'] = 100;
        }

        // Extract expansion
        $expansion = $attributes['expansion']['data']['attributes'] ?? [];
        $cardData['espansione'] = $expansion['code'] ?? null;

        // Extract arena
        $arenas = $attributes['arenas']['data'] ?? [];
        $cardData['arena'] = (!empty($arenas) && isset($arenas[0])) ? ($arenas[0]['attributes']['name'] ?? null) : null;

        // Extract aspects
        $aspects = $attributes['aspects']['data'] ?? [];
        if (!empty($aspects) && isset($aspects[0])) {
            $cardData['aspettoPrimario'] = $this->translateAspect($aspects[0]['attributes']['name'] ?? '');
        }
        if (count($aspects) > 1 && isset($aspects[1])) {
            $cardData['aspettoSecondario'] = $this->translateAspect($aspects[1]['attributes']['name'] ?? '');
        }

        // Handle aspect duplicates
        $aspectDuplicates = $attributes['aspectDuplicates']['data'] ?? [];
        if (!empty($aspectDuplicates) && !empty($aspects) && isset($aspects[0])) {
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
     * Fetch all card IDs from the official API with pagination
     * Recupera tutti gli ID carte dall'API ufficiale con paginazione
     *
     * @param string $logFile Log file path
     * @return array Array of card IDs
     */
    private function fetchAllCardIds($logFile)
    {
        $allCardIds = [];
        $page = 1;
        $maxPages = 1000; // Safety limit

        while ($page <= $maxPages) {
            $url = "https://admin.starwarsunlimited.com/api/card-list?locale=it&filters[variantOf][id][\$null]=true&pagination[page]={$page}&pagination[pageSize]=50";

            $this->writeScanLog("Chiamata API pagina {$page}: {$url}", $logFile);

            try {
                $response = Http::timeout(30)->get($url);

                if (!$response->successful()) {
                    $this->writeScanLog("Errore API pagina {$page}: " . $response->status(), $logFile);
                    break;
                }

                $jsonData = $response->json();
                $cards = $jsonData['data'] ?? [];

                if (empty($cards)) {
                    $this->writeScanLog("Nessuna carta trovata alla pagina {$page}, fine paginazione", $logFile);
                    break;
                }

                foreach ($cards as $card) {
                    $cardId = $card['attributes']['cardUid'] ?? null;
                    if ($cardId) {
                        $allCardIds[] = $cardId;
                    }
                }

                $this->writeScanLog("Pagina {$page}: " . count($cards) . " carte elaborate", $logFile);
                $page++;

            } catch (\Exception $e) {
                $this->writeScanLog("Eccezione pagina {$page}: " . $e->getMessage(), $logFile);
                break;
            }
        }

        return array_unique($allCardIds);
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

                // Debug: Log sample IDs for comparison
                if (!empty($allCardIds) && !empty($dbCards)) {
                    $this->writeScanLog("=== DEBUG CONFRONTO ID ===", $logFile);
                    $this->writeScanLog("Primi 5 ID dall'API: " . implode(', ', array_slice($allCardIds, 0, 5)), $logFile);
                    $this->writeScanLog("Primi 5 ID dal DB: " . implode(', ', array_slice($dbCards, 0, 5)), $logFile);

                    // Check for format differences
                    $apiSample = $allCardIds[0] ?? '';
                    $dbSample = $dbCards[0] ?? '';
                    $this->writeScanLog("Formato API: '{$apiSample}' (lunghezza: " . strlen($apiSample) . ")", $logFile);
                    $this->writeScanLog("Formato DB: '{$dbSample}' (lunghezza: " . strlen($dbSample) . ")", $logFile);
                }

                // Step 3: Find new card IDs
                $newCardIds = array_diff($allCardIds, $dbCards);
                $this->writeScanLog("Nuove carte da elaborare: " . count($newCardIds), $logFile);

                // Debug: Log some new card IDs if any
                if (!empty($newCardIds)) {
                    $this->writeScanLog("Primi 10 nuovi ID: " . implode(', ', array_slice($newCardIds, 0, 10)), $logFile);
                }

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

                } else {
                    $this->writeScanLog("Nessuna nuova carta trovata", $logFile);
                    ThreadMessageCreated::dispatch($threadId, "✅ Scansione completata! Nessuna nuova carta trovata tramite API", true);
                }
            } else {
                $this->writeScanLog("ERRORE: Nessun ID carta recuperato dall'API", $logFile);
                ThreadMessageCreated::dispatch($threadId, "❌ Errore nel recupero carte dall'API", true);
            }
        } catch (\Exception $e) {
            $errorMsg = "Errore scansione API: " . $e->getMessage();
            $this->writeScanLog("ERRORE CRITICO: " . $errorMsg, $logFile);
            Log::error("API scan failed: " . $e->getMessage());
            ThreadMessageCreated::dispatch($threadId, "❌ " . $errorMsg, true);
        }
    }

    /**
     * Process new cards found via API (background job)
     * Elabora le nuove carte trovate tramite API (job in background)
     *
     * This method runs ONLY after the API scan is completely finished
     * Now includes checkpoint system for recovery from interruptions
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

            $newCardIds = array_values($processData['cardIds']); // Reindex array to ensure consecutive indices
            $logFile = $processData['logFile'];

            // Debug logging
            $this->writeScanLog("Array newCardIds dopo reindex: " . json_encode($newCardIds), $logFile);
            $this->writeScanLog("Numero elementi in newCardIds: " . count($newCardIds), $logFile);

            // Check for existing checkpoint
            $checkpointFile = storage_path("app/processing_checkpoint.json");
            $checkpoint = [];
            $startIndex = 0;
            $toInsert = [];

            if (file_exists($checkpointFile)) {
                $checkpoint = json_decode(file_get_contents($checkpointFile), true);
                if ($checkpoint && $checkpoint['threadId'] === $threadId) {
                    $startIndex = $checkpoint['lastProcessedIndex'] + 1;
                    $toInsert = $checkpoint['processedCards'] ?? [];
                    $this->writeScanLog("=== RIPRESA DA CHECKPOINT ===", $logFile);
                    $this->writeScanLog("Ripresa dall'indice: {$startIndex}", $logFile);
                    $this->writeScanLog("Carte già elaborate: " . count($toInsert), $logFile);
                    ThreadMessageCreated::dispatch($threadId, "Ripresa elaborazione da carta " . ($startIndex + 1) . "/" . count($newCardIds));
                }
            }

            if ($startIndex === 0) {
                $this->writeScanLog("=== INIZIO ELABORAZIONE DETTAGLI CARTE ===", $logFile);
                $this->writeScanLog("Carte da elaborare: " . count($newCardIds), $logFile);
                ThreadMessageCreated::dispatch($threadId, "Inizio elaborazione dettagli per " . count($newCardIds) . " carte");
            }

            $processedCount = count($toInsert);
            $batchSize = 50; // Increased batch size for checkpoints
            $checkpointInterval = 100; // Save checkpoint every 100 cards

            for ($index = $startIndex; $index < count($newCardIds); $index++) {
                if (!isset($newCardIds[$index])) {
                    $this->writeScanLog("ERRORE: Indice {$index} non trovato nell'array newCardIds", $logFile);
                    continue;
                }

                $cardId = $newCardIds[$index];
                $this->writeScanLog("Elaborazione carta " . ($index + 1) . "/" . count($newCardIds) . ": {$cardId}", $logFile);

                $cardData = $this->getCardDetailsFromAPI($cardId, $logFile);
                if ($cardData) {
                    $toInsert[] = $cardData;
                    $processedCount++;

                    $cardInfo = ($cardData['espansione'] ?? 'N/A') . "-" . ($cardData['numero'] ?? 'N/A') . " " . ($cardData['nome'] ?? 'N/A');
                    $this->writeScanLog("✅ Carta elaborata: {$cardInfo}", $logFile);
                } else {
                    $this->writeScanLog("❌ Errore elaborazione carta: {$cardId}", $logFile);
                }

                // Update progress and checkpoint
                if (($index + 1) % $batchSize === 0) {
                    ThreadMessageCreated::dispatch($threadId, "Elaborate " . ($index + 1) . "/" . count($newCardIds) . " carte");
                    $this->writeScanLog("Progresso: " . ($index + 1) . "/" . count($newCardIds) . " carte elaborate", $logFile);
                }

                // Save checkpoint every N cards
                if (($index + 1) % $checkpointInterval === 0) {
                    $checkpointData = [
                        'threadId' => $threadId,
                        'lastProcessedIndex' => $index,
                        'processedCards' => $toInsert,
                        'timestamp' => time()
                    ];
                    file_put_contents($checkpointFile, json_encode($checkpointData));
                    $this->writeScanLog("Checkpoint salvato all'indice: " . ($index + 1), $logFile);
                }

                // Check execution time and break if approaching limits
                if ((time() - ($_SERVER['REQUEST_TIME'] ?? time())) > 240) { // 4 minutes limit
                    $this->writeScanLog("Limite tempo raggiunto, salvataggio checkpoint e riavvio...", $logFile);

                    // Save final checkpoint
                    $checkpointData = [
                        'threadId' => $threadId,
                        'lastProcessedIndex' => $index,
                        'processedCards' => $toInsert,
                        'timestamp' => time()
                    ];
                    file_put_contents($checkpointFile, json_encode($checkpointData));

                    // Restart the process
                    JobController::fireAndForgetGet(route('carte.processNewCards', ['threadId' => $threadId]), [
                        "token" => env('JOB_TOKEN')
                    ]);

                    ThreadMessageCreated::dispatch($threadId, "Processo riavviato automaticamente - Elaborate " . ($index + 1) . "/" . count($newCardIds) . " carte");
                    return;
                }
            }

            // Processing completed
            $this->writeScanLog("=== ELABORAZIONE COMPLETATA ===", $logFile);
            $this->writeScanLog("Carte elaborate con successo: " . count($toInsert) . "/" . count($newCardIds), $logFile);

            // Clean up checkpoint file
            if (file_exists($checkpointFile)) {
                unlink($checkpointFile);
                $this->writeScanLog("Checkpoint rimosso", $logFile);
            }

            ThreadMessageCreated::dispatch($threadId, "Completata elaborazione: " . count($toInsert) . " carte pronte per importazione");

            if (!empty($toInsert)) {
                $this->writeScanLog("Avvio inserimento asincrono nel database", $logFile);

                // Avvia inserimento asincrono per evitare timeout
                $this->startAsyncCardInsertion($toInsert, $threadId, $logFile);

                // Mark thread as complete since the scan and processing is done
                ThreadMessageCreated::dispatch($threadId, "✅ Scansione completata! Avviato inserimento di " . count($toInsert) . " carte", true);
            } else {
                $this->writeScanLog("Nessuna carta da importare", $logFile);
                ThreadMessageCreated::dispatch($threadId, "Nessuna carta da importare", true);
            }

        } catch (\Exception $e) {
            $errorMsg = "Errore elaborazione carte: " . $e->getMessage();
            Log::error($errorMsg);

            if (isset($logFile)) {
                $this->writeScanLog("ERRORE CRITICO: " . $errorMsg, $logFile);
                $this->writeScanLog("Stack trace: " . $e->getTraceAsString(), $logFile);
            }
            ThreadMessageCreated::dispatch($threadId, "❌ " . $errorMsg, true);
        }
    }



    /**
     * Start asynchronous card insertion to avoid timeout issues
     * Avvia inserimento asincrono delle carte per evitare problemi di timeout
     *
     * @param array $cards Array of cards to insert
     * @param string $threadId Thread ID for progress tracking
     * @param string|null $logFile Log file path for detailed logging
     * @return void
     */
    private function startAsyncCardInsertion($cards, $threadId, $logFile = null)
    {
        // Save cards data to temporary file for async processing
        $insertData = [
            'cards' => $cards,
            'threadId' => $threadId,
            'logFile' => $logFile,
            'timestamp' => time()
        ];

        file_put_contents(storage_path("app/cards_to_insert.json"), json_encode($insertData));

        if ($logFile) {
            $this->writeScanLog("Dati salvati per inserimento asincrono: " . count($cards) . " carte", $logFile);
        }

        // Launch async insertion process
        JobController::fireAndForgetGet(route('carte.insertCards', ['threadId' => $threadId]), [
            "token" => env('JOB_TOKEN')
        ]);
    }

    /**
     * Process asynchronous card insertion from temporary file
     * Elabora inserimento asincrono delle carte dal file temporaneo
     *
     * @param Request $request HTTP request containing threadId
     * @return void
     */
    public function insertCards(Request $request)
    {
        $threadId = $request->input('threadId');

        try {
            // Read insertion data from temporary file
            $insertDataFile = storage_path("app/cards_to_insert.json");
            if (!file_exists($insertDataFile)) {
                ThreadMessageCreated::dispatch($threadId, "Errore: file dati inserimento non trovato", true);
                return;
            }

            $insertData = json_decode(file_get_contents($insertDataFile), true);
            $cards = $insertData['cards'] ?? [];
            $logFile = $insertData['logFile'] ?? null;

            if (empty($cards)) {
                ThreadMessageCreated::dispatch($threadId, "Nessuna carta da inserire", true);
                return;
            }

            // Process cards with timeout management
            $this->insertCardsDirectly($cards, $threadId, $logFile);

            // Send notifications after successful insertion
            $this->sendEmailNotifications($cards);
            $this->sendTelegramAlert("Importazione completata per " . count($cards) . " nuove carte");

            if ($logFile) {
                $this->writeScanLog("Importazione completata e notifiche inviate", $logFile);
            }

            // Clean up temporary file
            if (file_exists($insertDataFile)) {
                unlink($insertDataFile);
            }

        } catch (\Exception $e) {
            $errorMsg = "Errore inserimento asincrono: " . $e->getMessage();
            Log::error($errorMsg);
            ThreadMessageCreated::dispatch($threadId, $errorMsg, true);
        }
    }

    /**
     * Insert cards directly into database with timeout management
     * Inserisce le carte direttamente nel database con gestione timeout
     *
     * @param array $cards Array of cards to insert
     * @param string $threadId Thread ID for progress tracking
     * @param string|null $logFile Log file path for detailed logging
     * @return void
     */
    private function insertCardsDirectly($cards, $threadId, $logFile = null)
    {
        $batchSize = 5;
        $totalCards = count($cards);
        $insertedCount = 0;
        $errorCount = 0;
        $startTime = time();

        if ($logFile) {
            $this->writeScanLog("Inizio inserimento diretto di {$totalCards} carte", $logFile);
        }

        ThreadMessageCreated::dispatch($threadId, "Inizio inserimento di {$totalCards} carte nel database");

        // Process cards in batches with timeout management
        for ($i = 0; $i < $totalCards; $i += $batchSize) {
            $batch = array_slice($cards, $i, $batchSize);
            $batchNumber = intval($i / $batchSize) + 1;
            $totalBatches = ceil($totalCards / $batchSize);

            ThreadMessageCreated::dispatch($threadId, "Inserimento batch {$batchNumber} di {$totalBatches}");

            foreach ($batch as $cardData) {
                try {
                    $cardCid = $cardData["cid"]; // Use the real CID from API, not espansione-numero

                    // Double-check if card already exists before inserting
                    $existingCard = Card::where('cid', $cardCid)->first();
                    if ($existingCard) {
                        if ($logFile) {
                            $this->writeScanLog("Carta già esistente saltata: {$cardCid}", $logFile);
                        }
                        continue; // Skip this card
                    }

                    // Create new card instance
                    $carta = new Card();

                    // Set card attributes - use the real CID from API
                    $carta->cid = $cardCid;
                    $carta->espansione = $cardData["espansione"];
                    $carta->numero = $cardData["numero"];
                    $carta->aspettoPrimario = $cardData["aspettoPrimario"] ?? null;
                    $carta->aspettoSecondario = $cardData["aspettoSecondario"] ?? null;
                    $carta->unica = $cardData["unica"] ?? false;
                    $carta->nome = $cardData["nome"];
                    $carta->titolo = $cardData["titolo"] ?? "";
                    $carta->tipo = $cardData["tipo"];
                    $carta->rarita = $cardData["rarita"];
                    $carta->costo = $cardData["costo"] ?? 0;
                    $carta->vita = $cardData["vita"] ?? null;
                    $carta->potenza = $cardData["potenza"] ?? null;
                    $carta->descrizione = $cardData["descrizione"];
                    $carta->tratti = $cardData["tratti"];
                    $carta->arena = $cardData["arena"] ?? null;
                    $carta->artista = $cardData["artista"];
                    $carta->uscita = $cardData["uscita"];
                    $carta->frontArt = $cardData["frontArt"] ?? null;
                    $carta->backArt = $cardData["backArt"] ?? null;
                    $carta->maxCopie = $cardData["maxCopie"] ?? 3;

                    // Save card to database
                    $carta->save();
                    $insertedCount++;

                    if ($logFile) {
                        $this->writeScanLog("Carta inserita: {$carta->cid} - {$carta->nome}", $logFile);
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    $cardInfo = ($cardData['espansione'] ?? 'N/A') . "-" . ($cardData['numero'] ?? 'N/A');
                    $errorMsg = "Errore inserimento carta {$cardInfo}: " . $e->getMessage();

                    if ($logFile) {
                        $this->writeScanLog($errorMsg, $logFile);
                    }
                    Log::error($errorMsg);
                }
            }

            // Check execution time and restart if approaching limits (4 minutes)
            if ((time() - $startTime) > 240) {
                if ($logFile) {
                    $this->writeScanLog("Limite tempo raggiunto, riavvio processo inserimento...", $logFile);
                }

                // Save remaining cards for next iteration
                $remainingCards = array_slice($cards, $i + $batchSize);
                if (!empty($remainingCards)) {
                    $insertData = [
                        'cards' => $remainingCards,
                        'threadId' => $threadId,
                        'logFile' => $logFile,
                        'timestamp' => time()
                    ];
                    file_put_contents(storage_path("app/cards_to_insert.json"), json_encode($insertData));

                    // Restart the process
                    JobController::fireAndForgetGet(route('carte.insertCards', ['threadId' => $threadId]), [
                        "token" => env('JOB_TOKEN')
                    ]);

                    ThreadMessageCreated::dispatch($threadId, "Processo riavviato automaticamente - Inserite " . ($insertedCount) . "/" . $totalCards . " carte");
                    return;
                }
            }

            // Small delay between batches to avoid overwhelming the database
            usleep(100000); // 100ms delay
        }

        $finalMsg = "Inserimento completato: {$insertedCount} carte inserite";
        if ($errorCount > 0) {
            $finalMsg .= ", {$errorCount} errori";
        }

        if ($logFile) {
            $this->writeScanLog($finalMsg, $logFile);
        }

        ThreadMessageCreated::dispatch($threadId, $finalMsg, true);
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
            try{
                Mail::to($user['email'])->send(new NewCardsEmail($cardsData));
            }catch(\Error $e){
                sleep(1);
                Mail::to($user['email'])->send(new NewCardsEmail($cardsData));
            }
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
     * Clean up processing checkpoint (manual recovery)
     * Pulisce il checkpoint di elaborazione (recovery manuale)
     *
     * @return \Illuminate\Http\JsonResponse Status of cleanup
     */
    public function cleanupCheckpoint()
    {
        $checkpointFile = storage_path("app/processing_checkpoint.json");

        if (file_exists($checkpointFile)) {
            $checkpoint = json_decode(file_get_contents($checkpointFile), true);
            unlink($checkpointFile);

            return response()->json([
                'success' => true,
                'message' => 'Checkpoint rimosso',
                'checkpoint' => $checkpoint
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Nessun checkpoint trovato'
        ]);
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
     * 1. Generic type (Leader, Base vs others)
     * 2. Primary aspect (Blu, Verde, Rosso, Giallo, Nero, Bianco)
     * 3. Secondary aspect (Nero, Bianco, same as primary, others)
     * 4. Specific type (Unità, Miglioria, Evento)
     * 5. Cost (costo) - ascending order, except for Leaders
     * 6. Release date (uscita) - for different expansions
     * 7. Card number (numero) - final tie-breaker
     *
     * @param array &$el1 First card element (passed by reference)
     * @param array &$el2 Second card element (passed by reference)
     * @param bool $verbose Whether to output detailed comparison steps
     * @return int -1 if el1 < el2, 1 if el1 > el2, 0 if equal
     */
    public static function compareElements(&$el1, &$el2, $verbose) {
        // Converto gli elementi in array se sono modelli Eloquent
        if (is_object($el1) && method_exists($el1, 'toArray')) {
            $el1 = $el1->toArray();
        }
        if (is_object($el2) && method_exists($el2, 'toArray')) {
            $el2 = $el2->toArray();
        }

        // Funzione helper per accesso sicuro agli array
        $getValue = function($element, $key, $default = '') {
            return isset($element[$key]) ? $element[$key] : $default;
        };

        if($verbose){
            echo "Confronto tra ".$getValue($el1, "nome")." e ".$getValue($el2, "nome")."<br>";
        }

        // Definisco l'ordine dei tipi generici
        $genericTipoOrder = ['Leader', 'Base'];

        // Definisco l'ordine degli aspetti primari
        $primaryAspectOrder = ['Blu', 'Verde', 'Rosso', 'Giallo', "Nero", "Bianco"];

        // Definisco l'ordine dei tipi specifici
        $specificTipoOrder = ['Unità', 'Miglioria', 'Evento'];
        
        // Funzione per ottenere il peso del tipo
        $getGenericTipoWeight = function($element) use ($genericTipoOrder) {
            // Converto l'elemento in array se è un modello Eloquent
            if (is_object($element) && method_exists($element, 'toArray')) {
                $element = $element->toArray();
            }
            $tipo = isset($element['tipo']) ? $element['tipo'] : '';
            $index = array_search($tipo, $genericTipoOrder);
            return $index !== false ? $index : count($genericTipoOrder);
        };
        
        // Funzione per ottenere il peso dell'aspetto primario
        $getPrimaryAspectWeight = function($element) use ($primaryAspectOrder) {
            // Converto l'elemento in array se è un modello Eloquent
            if (is_object($element) && method_exists($element, 'toArray')) {
                $element = $element->toArray();
            }
            $aspetto = isset($element['aspettoPrimario']) ? $element['aspettoPrimario'] : '';
            $index = array_search($aspetto, $primaryAspectOrder);
            return $index !== false ? $index : count($primaryAspectOrder);
        };
        
        // Funzione per verificare la presenza di Dark/Light nell'aspetto secondario
        $getSecondaryAspectWeight = function($element) {
            // Converto l'elemento in array se è un modello Eloquent
            if (is_object($element) && method_exists($element, 'toArray')) {
                $element = $element->toArray();
            }
            $aspettoSecondario = isset($element['aspettoSecondario']) ? $element['aspettoSecondario'] : '';

            if ($aspettoSecondario === 'Nero') {
                return 0;
            }

            if ($aspettoSecondario === 'Bianco') {
                return 1;
            }

            $aspettoPrimario = isset($element["aspettoPrimario"]) ? $element["aspettoPrimario"] : '';
            if ($aspettoSecondario === $aspettoPrimario) {
                return 2;
            }

            return 3;
        };

        $getSpecificTipoWeight = function($element) use ($specificTipoOrder){
            // Converto l'elemento in array se è un modello Eloquent
            if (is_object($element) && method_exists($element, 'toArray')) {
                $element = $element->toArray();
            }
            $tipo = isset($element["tipo"]) ? $element["tipo"] : '';
            $index = array_search($tipo, $specificTipoOrder);
            return $index !== false ? $index : count($specificTipoOrder);
        };
        

        
        // Confronto per tipo generico
        $tipoWeight1 = $getGenericTipoWeight($el1);
        $tipoWeight2 = $getGenericTipoWeight($el2);
        
        if ($tipoWeight1 < $tipoWeight2) {
            if($verbose){
                echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base del tipo generico<br>";
            }
            return -1;
        }

        if ($tipoWeight1 > $tipoWeight2) {
            if($verbose){
                echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base del tipo generico<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte sono dello stesso tipo generico(".$getValue($el1, "tipo").")<br>";
        }
        
        // Se i tipi sono uguali, confronto per aspetto primario
        $primaryAspectWeight1 = $getPrimaryAspectWeight($el1);
        $primaryAspectWeight2 = $getPrimaryAspectWeight($el2);
        
        if ($primaryAspectWeight1 < $primaryAspectWeight2) {
            if($verbose){
                echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base dell'aspetto primario<br>";
            }
            return -1;
        }

        if ($primaryAspectWeight1 > $primaryAspectWeight2) {
            if($verbose){
                echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base dell'aspetto primario<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte hanno lo stesso aspetto primario (".$getValue($el1, "aspettoPrimario").")<br>";
        }
        
        // Se gli aspetti primari sono uguali, confronto per aspetto secondario
        $secondaryAspectWeight1 = $getSecondaryAspectWeight($el1);
        $secondaryAspectWeight2 = $getSecondaryAspectWeight($el2);
        
        if ($secondaryAspectWeight1 < $secondaryAspectWeight2) {
            if($verbose){
                echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base dell'aspetto secondario<br>";
            }
            return -1;
        }

        if ($secondaryAspectWeight1 > $secondaryAspectWeight2) {
            if($verbose){
                echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base dell'aspetto secondario<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte hanno lo stesso aspetto secondario (".$getValue($el1, "aspettoSecondario").")<br>";
        }
        
        // Se aspetto secondario è uguale, confronto per tipo specifico
        $tipoWeight1 = $getSpecificTipoWeight($el1);
        $tipoWeight2 = $getSpecificTipoWeight($el2);
        
        if ($tipoWeight1 < $tipoWeight2) {
            if($verbose){
                echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base del tipo specifico<br>";
            }
            return -1;
        }

        if ($tipoWeight1 > $tipoWeight2) {
            if($verbose){
                echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base del tipo specifico<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte hanno lo stesso tipo specifico (".$getValue($el1, "tipo").")<br>";
        }

        // Se tipo specifico è uguale, confronto per costo (in ordine crescente)
        if($getValue($el1, "tipo") != "Leader"){
            $costo1 = $getValue($el1, "costo", 0);
            $costo2 = $getValue($el2, "costo", 0);
            if ($costo1 < $costo2) {
                if($verbose){
                    echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base del costo<br>";
                }
                return -1;
            }

            if ($costo1 > $costo2) {
                if($verbose){
                    echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base del costo<br>";
                }
                return 1;
            }

            if($verbose){
                echo "le carte hanno lo stesso costo (".$getValue($el1, "costo").")<br>";
            }
        }

        // Se nome è uguali, confronto per uscita (formato aaaa mm gg)
        $espansione1 = $getValue($el1, "espansione");
        $espansione2 = $getValue($el2, "espansione");
        if($espansione1 != $espansione2){
            $uscita1 = $getValue($el1, "uscita");
            $uscita2 = $getValue($el2, "uscita");
            $compareDate = strcmp($uscita1, $uscita2);
            if ($compareDate < 0) {
                if($verbose){
                    echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base dell'uscita<br>";
                }
                return -1;
            }

            if ($compareDate > 0) {
                if($verbose){
                    echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base dell'uscita<br>";
                }
                return 1;
            }

            if($verbose){
                echo "le carte hanno la stessa uscita (".$getValue($el1, "uscita").")<br>";
            }
        }

        // Se la carta è uguale, confronto per numero
        $numero1 = $getValue($el1, "numero", 0);
        $numero2 = $getValue($el2, "numero", 0);
        if ($numero1 < $numero2) {
            if($verbose){
                echo $getValue($el1, "nome")." viene prima di ".$getValue($el2, "nome")." sulla base del numero<br>";
            }
            return -1;
        }
        
        if ($numero1 > $numero2) {
            if($verbose){
                echo $getValue($el2, "nome")." viene prima di ".$getValue($el1, "nome")." sulla base del numero<br>";
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
