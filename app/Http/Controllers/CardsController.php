<?php

namespace App\Http\Controllers;

use App\Events\CardReceived;

use App\Events\MessageCreated;
use Illuminate\Http\Request;

use App\Models\Card;
use App\Models\Deck;

class CardsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ?string $espansione = ""){
        $espansione = strtoupper($espansione);
        $get = $request->all();
        if(!isset($get["nome"])){
            $get["nome"] = "";
        }
        $model = Card::whereLike("nome", "%".$get["nome"]."%")->whereLike("espansione", "%$espansione%")->get();
        $empty = $model->isEmpty();
        $model = CardsController::mergeSort($model);
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
    
    public function startImport(){
        $url = 'http://swudb.altervista.org/collezione.json';
        $json = file_get_contents($url);
        $fullSet = json_decode($json, true);
        $dbSet = Card::select('espansione', 'numero')->get()->toArray();

        $toInsert = [];

        foreach ($fullSet as $card) {
            if (!$this::contain($dbSet, $card)) {
                $toInsert[] = $card;
            }
        }

        // Salva su file temporaneo sul server
        if(env("APP_DEBUG")){
            file_put_contents(storage_path("app/to_insert.json"), json_encode($toInsert));
        }
        JobController::fireAndForgetGet(route('carte.sendBatch'), [
            "token" => env('JOB_TOKEN')
        ]);
        return view("carte.update", ["result" => true, "count" => count($toInsert), "data" => $toInsert]);
    }

    public function sendBatch(Request $request){
        $next = intval($request->input("next", 0));
        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug.log", "sendBatch next:$next \n\n", FILE_APPEND);
        MessageCreated::dispatch("Inizio importazione batch " . $next);
        $batchSize = 5;
        JobController::fireAndForgetGet(route('carte.dispatchBatch', ['start' => $next, 'batchSize' => $batchSize]));
        $data = json_decode(file_get_contents(storage_path("app/to_insert.json")), true);
        if ($next + $batchSize >= count($data)) {
            echo "Import completato!\n";
            MessageCreated::dispatch("Import completato!");
            // file_put_contents(storage_path("app/to_insert.json"), "[]"); // Pulisce il file dopo l'importazione
        } else {
            echo "Batch $next dispatchato, prossima esecuzione tra 100ms...\n";
            MessageCreated::dispatch("Batch $next dispatchato");
            $next += $batchSize;
            usleep(100000); // 100ms delay
            JobController::fireAndForgetGet(route('carte.sendBatch', ['next' => $next]), [
                "token" => env('JOB_TOKEN')
            ]);
        }
    }

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

    public function api($espansione, $numero){
        return Card::where('numero', $numero)->where('espansione', $espansione)->first();
    }

    public function apis($espansione){
        $ret = Card::where('espansione', $espansione)->get();
        return [$ret->count(), $ret];
    }

    public function show($espansione, $numero){
        $carta = Card::where('numero', $numero)->where('espansione', $espansione)->get();
        $carta = $carta->get(0);
        try{
            $carta->nome;
            $find = true;
        }catch(\Exception $e){
            $find = false;
        }
        return view('carte.show', ["find" => $find, "carta" => $carta, "numero" => $numero, "espansione" => $espansione]);
    }

    /**
     * control if the card is contained by the array
     * @param mixed $array
     * @param mixed $element
     * @return bool
     */
    static function contain($array, $element){
        foreach($array as $el){
            if($el["espansione"] === $element["espansione"] && $el["numero"] === $element["numero"]){
                return true;
            }
        }
        return false;
    }

    static function compareElements(&$el1, &$el2, $verbose) {
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

    static function mergeSort(&$array, $verbose = false) {
        // Caso base: se la collezione ha 0 o 1 elemento, è già ordinata
        if ($array->count() <= 1) {
            return $array;
        }
        
        // Divido la collezione in due metà
        $mid = floor($array->count() / 2);
        $left = $array->slice(0, $mid)->values();
        $right = $array->slice($mid)->values();
        
        // Richiamo ricorsivamente mergeSort sulle due metà
        $left = CardsController::mergeSort($left);
        $right = CardsController::mergeSort($right);
        
        // Fondo le due metà
        $result = collect();
        $leftIndex = 0;
        $rightIndex = 0;
        
        while ($leftIndex < $left->count() && $rightIndex < $right->count()) {
            // Uso la funzione compareElements per confrontare
            if (CardsController::compareElements($left[$leftIndex], $right[$rightIndex], $verbose) <= 0) {
            $result->push($left[$leftIndex]);
            $leftIndex++;
            } else {
            $result->push($right[$rightIndex]);
            $rightIndex++;
            }
        }
        
        // Aggiungo gli eventuali elementi rimanenti di left
        while ($leftIndex < $left->count()) {
            $result->push($left[$leftIndex]);
            $leftIndex++;
        }
        
        // Aggiungo gli eventuali elementi rimanenti di right
        while ($rightIndex < $right->count()) {
            $result->push($right[$rightIndex]);
            $rightIndex++;
        }
        
        return $result;
    }
}
