<?php

namespace App\Http\Controllers;

use App\Events\CardReceived;

use Illuminate\Http\Request;

use App\Models\Card;
use App\Models\Deck;

class CardsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($espansione, Request $request){
        $get = $request->all();
        if(!isset($get["nome"])){
            $get["nome"] = "";
        }
        $model = Card::select("espansione", "numero", "aspettoPrimario", "aspettoSecondario", "nome", "titolo", "tipo", "rarita", "costo", "vita", "potenza", "descrizione", "tratti", "arena", "artista", "uscita")->whereLike("nome", "%".$get["nome"]."%")->whereLike("espansione", "%$espansione%")->get();
        $empty = $model->isEmpty();
        $model = $model->toArray();
        $model = $this->mergeSort($model);
        foreach($model as &$carta){
            unset($carta["nome"]);
            unset($carta["titolo"]);
            unset($carta["id"]);
        }
        $header = ["snippet", "aspettoPrimario", "aspettoSecondario", "tipo", "rarita", "costo", "vita", "potenza", "descrizione", "tratti", "arena", "artista", "uscita"];
        return view('carte.index', ["content" => $model, "empty" => $empty, "nome" => $get["nome"], "header" => $header]);
    }

    public function indexAll(Request $request){
        return CardsController::index("", $request);
    }
    
    public function create(){
        $url = 'http://swudb.altervista.org/collezione.json';
        $json = file_get_contents($url);
        $data = json_decode($json, true);
        $keys = [];
        foreach($data as $carta){
            foreach($carta as $key => $value){
                if(!in_array($key, $keys)){
                    array_push($keys, $key);
                }
            }
        }
        foreach($data as &$value){
            foreach($keys as $key){
                if(!isset($value[$key])){
                    $value[$key] = "";
                }
            }
        }
    
        $result = false;
        if (json_last_error() === JSON_ERROR_NONE) {
            $result = true;
            $fullSet = $data;
            $data = [];
            $dataRaw = Card::get()->toArray();
            foreach ($fullSet as &$card) {
                $card["tratti"] = implode(" * ", $card["tratti"]);
                $card["snippet"] = $card["espansione"]."-".$card["numero"]." - ".$card["nome"].((strlen($card["titolo"]) > 0 ? ", ". strtoupper($card["titolo"]) : ""));
                if(!$this->contain($dataRaw, $card)){
                    array_push($dataRaw, $card);
                    array_push($data, $card);
                }
            }
            foreach($data as $card){
                CardReceived::dispatch($card);
            }
        }
        return view('carte.update', ["result"=>$result, "data"=>$data]);
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
    function contain($array, $element){
        foreach($array as $el){
            if($el["espansione"] == $element["espansione"] && $el["numero"] == $element["numero"]){
                // echo "la carta ".$element["snippet"]." è già presente<br>";
                return true;
            }
        }
        // echo "la carta ".$element["snippet"]." non è presente<br>";
        return false;
    }

    function compareElements(&$el1, &$el2, $verbose) {
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
        
        // Se costo è uguale, confronto per nome (in ordine alfabetico)
        if ($el1["nome"] < $el2["nome"]) {
            if($verbose){
                echo $el1["nome"]." viene prima di ".$el2['nome']." sulla base del nome<br>";
            }
            return -1;
        }
        
        if ($el1["nome"] > $el2["nome"]) {
            if($verbose){
                echo $el2["nome"]." viene prima di ".$el1['nome']." sulla base del nome<br>";
            }
            return 1;
        }

        if($verbose){
            echo "le carte hanno lo stesso nome (".$el1["nome"].")<br>";
        }
        
        // Se nome è uguali, confronto per uscita (formato aaaa mm gg)
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

    function mergeSort(&$array) {
        // Caso base: se l'array ha 0 o 1 elemento, è già ordinato
        if (count($array) <= 1) {
            return $array;
        }
        
        // Divido l'array in due metà
        $mid = floor(count($array) / 2);
        $left = array_slice($array, 0, $mid);
        $right = array_slice($array, $mid);
        
        // Richiamo ricorsivamente mergeSort sulle due metà
        $this->mergeSort($left);
        $this->mergeSort($right);
        
        // Fondo le due metà
        $i = $j = $k = 0;
        
        while ($i < count($left) && $j < count($right)) {
            // Uso la funzione compareElements per confrontare
            if ($this->compareElements($left[$i], $right[$j], false) <= 0) {
                $array[$k] = $left[$i];
                $i++;
            } else {
                $array[$k] = $right[$j];
                $j++;
            }
            $k++;
        }
        
        // Copio gli eventuali elementi rimanenti di left
        while ($i < count($left)) {
            $array[$k] = $left[$i];
            $i++;
            $k++;
        }
        
        // Copio gli eventuali elementi rimanenti di right
        while ($j < count($right)) {
            $array[$k] = $right[$j];
            $j++;
            $k++;
        }
        
        return $array;
    }
}
