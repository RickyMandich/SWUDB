<?php
    require_once "header.php";
    $getNumero = function($el) use ($conn){
        $query = "select numero from carte where espansione = '".$el["espansione"]."' and nome like '".str_replace("'", "\'", $el["nome"])."' and titolo like '".str_replace("'", "\'", $el["titolo"])."' order by numero";
        $result = $conn->query($query)->fetch_assoc();
        $numero = $result["numero"];
        return $numero ?? $el["numero"];
    };

    function compareElements(&$el1, &$el2) {
        //definisco l'ordine dei mazzi
        $mazzoOrder = [];
        $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $result = $conn->query("SELECT DISTINCT mazzo, codUtente, public FROM mazzi where codUtente = ".unserialize($_SESSION["user"])->getID()." or public = '1' order by mazzo");
        while($line = $result->fetch_assoc()){
            if($line["codUtente"] == unserialize($_SESSION["user"])->getID()){
                array_push($mazzoOrder, $line["mazzo"]);
            }else{
                array_push($mazzoOrder, $line["mazzo"]." di ".$conn->query("select nome from utenti where id = ".$line["codUtente"])->fetch_assoc()["nome"]);
            }
        }
        // Definisco l'ordine dei tipi
        $tipoOrder = ['Leader', 'Base'];
        
        // Definisco l'ordine degli aspetti primari
        $primaryAspectOrder = ['Blue', 'Green', 'Red', 'Yellow'];
        
        // Funzione per ottenere il peso del mazzo
        $getMazzoWeight = function($element) use ($mazzoOrder) {
            $mazzo = $element['mazzo'];
            $index = array_search($mazzo, $mazzoOrder);
            return $index !== false ? $index : count($mazzoOrder);
        };
        
        // Funzione per ottenere il peso del tipo
        $getTipoWeight = function($element) use ($tipoOrder) {
            $tipo = $element['tipo'];
            $index = array_search($tipo, $tipoOrder);
            return $index !== false ? $index : count($tipoOrder);
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
            
            if ($aspettoSecondario === 'Dark') {
                return 0;
            }
            
            if ($aspettoSecondario === 'Light') {
                return 1;
            }
            
            return 2;
        };
        
        // faccio un confronto per utente
        if ($el1['codUtente'] < $el2['codUtente']) {
            return -1;
        }
        
        if ($el1['codUtente'] > $el2['codUtente']) {
            return 1;
        }
        
        // Confronto per mazzo
        $mazzoWeight1 = $getMazzoWeight($el1);
        $mazzoWeight2 = $getMazzoWeight($el2);
        
        if ($mazzoWeight1 < $mazzoWeight2) {
            return -1;
        }
        
        if ($mazzoWeight1 > $mazzoWeight2) {
            return 1;
        }
        
        // Confronto per tipo
        $tipoWeight1 = $getTipoWeight($el1);
        $tipoWeight2 = $getTipoWeight($el2);
        
        if ($tipoWeight1 < $tipoWeight2) {
            return -1;
        }
        
        if ($tipoWeight1 > $tipoWeight2) {
            return 1;
        }
        
        // Se i tipi sono uguali, confronto per aspetto primario
        $primaryAspectWeight1 = $getPrimaryAspectWeight($el1);
        $primaryAspectWeight2 = $getPrimaryAspectWeight($el2);
        
        if ($primaryAspectWeight1 < $primaryAspectWeight2) {
            return -1;
        }
        
        if ($primaryAspectWeight1 > $primaryAspectWeight2) {
            return 1;
        }
        
        // Se gli aspetti primari sono uguali, confronto per aspetto secondario
        $secondaryAspectWeight1 = $getSecondaryAspectWeight($el1);
        $secondaryAspectWeight2 = $getSecondaryAspectWeight($el2);
        
        if ($secondaryAspectWeight1 < $secondaryAspectWeight2) {
            return -1;
        }
        
        if ($secondaryAspectWeight1 > $secondaryAspectWeight2) {
            return 1;
        }
        
        // Se aspetti secondari sono uguali, confronto per uscita (formato aaaa mm gg)
        $compareDate = strcmp($el1['uscita'], $el2['uscita']);
        if ($compareDate < 0) {
            return -1;
        }
        
        if ($compareDate > 0) {
            return 1;
        }
        
        // Se uscita è uguale, confronto per numero (in ordine crescente)
        if ($el1["getNumero"] < $el2["getNumero"]) {
            return -1;
        }
        
        if ($el1["getNumero"] > $el2["getNumero"]) {
            return 1;
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
        mergeSort($left);
        mergeSort($right);
        
        // Fondo le due metà
        $i = $j = $k = 0;
        
        while ($i < count($left) && $j < count($right)) {
            // Uso la funzione compareElements per confrontare
            if (compareElements($left[$i], $right[$j]) <= 0) {
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