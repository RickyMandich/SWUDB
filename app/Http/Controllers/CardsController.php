<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Events\ThreadMessageCreated;
use App\Services\ThreadManager;

use App\Mail\NewCardsEmail;

use App\Models\Card;
use App\Models\Deck;

use App\Models\User;
use Illuminate\Http\Request;

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
    public function index(Request $request, ?string $espansione = ""){
        $espansione = strtoupper($espansione);
        $get = $request->all();
        if(!isset($get["nome"])){
            $get["nome"] = "";
        }
        $model = Card::whereLike("nome", "%".$get["nome"]."%")->whereLike("espansione", "%$espansione%")->get();
        $empty = $model->isEmpty();
        $modelArray = $model->toArray();
        $sortedArray = CardsController::mergeSort($modelArray);
        $model = collect($sortedArray);
        if($espansione == ""){
            $title = "Carte";
        }else{
            $title = "Carte ".strtoupper($espansione);
        }
        $espansioni = Card::select('espansione')->selectRaw('MIN(uscita) as prima_uscita')->groupBy('espansione')->orderBy('prima_uscita')->get();
        return view('carte.index', [
            "content" => $model,
            "empty" => $empty,
            "nome" => $get["nome"],
            "title" => $title,
            "espansioni" => $espansioni,
            "espansione" => $espansione,
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
            $cardsArray = $cards->toArray();
            CardsController::mergeSort($cardsArray, true);
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
     * Start the card import process from external JSON source
     * Avvia il processo di importazione delle carte da sorgente JSON esterna
     *
     * This method handles the complete card import workflow:
     * 1. Fetches card data from external JSON API
     * 2. Compares with existing database cards
     * 3. Identifies new cards to import
     * 4. Saves them to temporary file and triggers batch processing
     * 5. Sends email notifications to all users about new cards
     *
     * @return \Illuminate\View\View The update result view with import statistics
     */
    public function startImport(){
        $url = 'http://swudb.altervista.org/collezione.json';
        $json = file_get_contents($url);
        $fullSet = json_decode($json, true);
        $dbSet = Card::select('espansione', 'numero')->get()->toArray();

        $toInsert = [];

        foreach ($fullSet as $card) {
            if (!$this::contain($dbSet, $card)) {
                $card["tratti"] = implode(" * ", $card["tratti"]);
                $toInsert[] = $card;
            }
        }

        // Salva su file temporaneo sul server
        file_put_contents(storage_path("app/to_insert.json"), json_encode($toInsert));
        if(count($toInsert) > 0){
            // Generate a thread ID for this import session
            $threadId = ThreadManager::generateThreadId('import');
            ThreadMessageCreated::dispatch($threadId, "Avvio importazione di " . count($toInsert) . " nuove carte");

            JobController::fireAndForgetGet(route('carte.sendBatch', ['threadId' => $threadId]), [
                "token" => env('JOB_TOKEN')
            ]);
            $message = "Sono disponibili queste nuove carte:\n";
            foreach($toInsert as $card){
                $message .= $card["espansione"] . "-" . $card["numero"] . " - " . $card["nome"] . (" " . $card["titolo"] ?? "") . "\n";
            }
            $users = User::select("email")->where('email', '!=', null)->get();
            foreach($users as $user){
                Mail::to($user['email'])->send(new NewCardsEmail($message));
            }
        }
        return view("carte.update", ["result" => true, "count" => count($toInsert), "data" => $toInsert]);
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
        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug.log", "sendBatch next:$next \n\n", FILE_APPEND);
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
        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug.log", "startBatch start:$start \n\n", FILE_APPEND);
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
     * Recursive merge sort implementation for card arrays
     * Implementazione ricorsiva del merge sort per array di carte
     *
     * This method implements the merge sort algorithm specifically designed for
     * arrays of cards. It uses the compareElements method to determine
     * the sorting order based on complex card comparison rules.
     *
     * The algorithm divides the array recursively until single elements,
     * then merges them back in sorted order using the custom comparison logic.
     *
     * @param array &$array Array of cards to sort (passed by reference)
     * @param bool $verbose Whether to enable verbose output during comparison
     * @return array The sorted array
     */
    public static function mergeSort(&$array, $verbose = false) {
        // Caso base: se l'array ha 0 o 1 elemento, è già ordinato
        if (count($array) <= 1) {
            return $array;
        }

        // Divido l'array in due metà
        $mid = floor(count($array) / 2);
        $left = array_slice($array, 0, $mid);
        $right = array_slice($array, $mid);

        // Richiamo ricorsivamente mergeSort sulle due metà
        $left = CardsController::mergeSort($left);
        $right = CardsController::mergeSort($right);

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
