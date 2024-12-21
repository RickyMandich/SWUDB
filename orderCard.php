<?php
    require_once "header.php";
    $getNumero = function($el){
        $query = "select numero from carte where espansione = '".$el["espansione"]."' and nome like '".str_replace("'", "\'", $el["nome"])."' and titolo like '".str_replace("'", "\'", $el["titolo"])."' order by numero";
        $result = $GLOBALS["conn"]->query($query)->fetch_assoc();
        $numero = $result["numero"];
        return $numero ?? $el["numero"];
    };

    function compareElements(&$el1, &$el2, $verbose) {
        //definisco l'ordine dei mazzi
        $mazzoOrder = [];
        $result = $GLOBALS["conn"]->query("SELECT DISTINCT nome as mazzo, codUtente, public FROM mazzi where codUtente = ".(isset($_SESSION["user"])?unserialize($_SESSION["user"])->getID():"-1")." or public = '1' order by id");
        while($line = $result->fetch_assoc()){
            if($line["codUtente"] == (isset($_SESSION["user"])?unserialize($_SESSION["user"])->getID():"-1")){
                array_push($mazzoOrder, $line["mazzo"]);
            }else{
                array_push($mazzoOrder, $line["mazzo"]." di ".$GLOBALS["conn"]->query("select nome from utenti where id = ".$line["codUtente"])->fetch_assoc()["nome"]);
            }
        }
        // Definisco l'ordine dei tipi generici
        $genericTipoOrder = ['Leader', 'Base'];
        
        // Definisco l'ordine degli aspetti primari
        $primaryAspectOrder = ['Blue', 'Green', 'Red', 'Yellow'];

        // Definisco l'ordine dei tipi specifici
        $specificTipoOrder = ['Unit', 'Upgrade', 'Event'];
        
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
            
            if ($aspettoSecondario === 'Dark') {
                return 0;
            }
            
            if ($aspettoSecondario === 'Light') {
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
        
        // Confronto per mazzo
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
        /*
        // Se nome è uguale, confronto per numero (in ordine crescente)
        if ($el1["getNumero"] < $el2["getNumero"]) {
            return -1;
        }
        
        if ($el1["getNumero"] > $el2["getNumero"]) {
            return 1;
        }
        */
        
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
        mergeSort($left);
        mergeSort($right);
        
        // Fondo le due metà
        $i = $j = $k = 0;
        
        while ($i < count($left) && $j < count($right)) {
            // Uso la funzione compareElements per confrontare
            if (compareElements($left[$i], $right[$j], false) <= 0) {
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