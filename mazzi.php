<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>mazzi</title>
        <!-- <link rel="stylesheet" href="css/mazzi.css"> -->
        <!-- <link rel="stylesheet" href="css/cartaPopUp.css"> -->
        <script src="js/profilo.js"></script>
    </head>
    <?php
        require_once("header.php");

        $getNumero = function($el) use ($conn){
            $query = "select numero from carte where espansione = '".$el["espansione"]."' and nome like '".str_replace("'", "\'", $el["nome"])."' and titolo like '".str_replace("'", "\'", $el["titolo"])."'";
            echo "$query<br>";
            $result = $conn->query($query)->fetch_assoc();
            var_dump($result);
            $numero = $result["numero"];
            echo "<br>numero originale:".$el["numero"]."    "."getNumero:$numero<br>";
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
        if (!isset($_SESSION["user"])){
            echo $file;
            ?>
            <meta http-equiv="refresh" content="0; url=./logIn?from=<?php echo $file; ?>">
            <?php
        }else{
        $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $resultSet = $conn->query("select m.mazzo, m.foil, m.public, c.*, m.codUtente from mazzi m, carte c where m.espansione = c.espansione and m.numero = c.numero and (m.codUtente = ". unserialize($_SESSION["user"])->getID()." or m.public = '1') order by mazzo, numero, espansione");
        $preDeck = [];
        while($line = $resultSet->fetch_assoc()){
            $row = [];
            foreach($line as $key => $value){
                if($key === 'mazzo' && $line["codUtente"] != unserialize($_SESSION["user"])->getID()){
                    $value = $value." di ".$conn->query("select nome from utenti where id = ".$line["codUtente"])->fetch_assoc()["nome"];
                }
                $row[$key] = $value;
            }
            $row["getNumero"] = $getNumero($row);
            array_push($preDeck, $row);
        }
        mergeSort($preDeck);
        $deck = [];
        $precedente = "";
        foreach($preDeck as $row){
            if(!isset($precedente) or $row["mazzo"] !== $precedente){
                $header = $row;
                foreach($header as $key => $i){
                    if($key !== "mazzo" and $key !== "espansione" and $key !== "numero") $header[$key] = null;
                }
                array_push($deck, $header);
            }
            array_push($deck, $row);
            $precedente = $row["mazzo"];
        }
        $resultSet = $conn->query("select distinct mazzo from mazzi");
        $mazzi = [];
        while($line = $resultSet->fetch_assoc()){
            array_push($mazzi, $line["mazzo"]);
        }
    ?>
    <body>
        <div class="container">
            <table>
                <thead>
                    <?php foreach($preDeck as $carta):?>
                        <tr>
                            <td>
                                <?php var_dump($carta)?>
                            </td>
                        </tr>
                    <?php endforeach;?>
                </thead>
            </table>
            <div class="decks-section">
                <h2>I Tuoi Mazzi <?php echo $conn->query("select nome from utenti where id = ".unserialize($_SESSION["user"])->getID())->fetch_assoc()["nome"]?></h2>
                <div class="decks-container">
                    <table>
                        <?php if (count($deck) > 0): ?>
                        <thead>
                            <tr class="deck-header">
                                <td>rimuovi carta</td>
                                <td>sposta carta</td>
                                <td>esporta mazzo come elenco di carte o segna come mancante</td>
                                <?php foreach($deck[1] as $key => $value):?>
                                <td>
                                    <?php echo $key; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <?php endif; ?>
                        <tbody>
                            <?php 
                            unset($precedente);
                            foreach($deck as $row): ?>
                                <tr class="card-in-deck-row <?php if(!isset($precedente) or $row["mazzo"] !== $precedente) echo "deck-header"; else echo "deck-card";?>">
                                    <td>
                                        <?php if(unserialize($_SESSION["user"])->getID() == $row["codUtente"]):?>
                                            <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                                <form action="./remove">
                                                    <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                    <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                    <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                    <input type="hidden" name="foil" value="<?php echo $row["foil"]?>">
                                                    <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                    <input type='image' src='img/rimuovi.png' width='100vw' height='auto' alt='Invia il form'>
                                                </form>
                                            <?php else: ?>
                                                <img src="https://swudb.com/cards/<?php echo $row["espansione"]."/".sprintf("%0".$numeri[$row["espansione"]]."d", $row["numero"])."-portrait.png";?>" width="100vw">
                                            <?php endif; ?>
                                        <?php endif;?>
                                    </td>
                                    <td style="max-width: 100vw">
                                        <?php if(unserialize($_SESSION["user"])->getID() == $row["codUtente"]):?>
                                            <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                                <img src='img/collezione.png' width='100px' height='auto' onclick="showMenuCollezione(this)">
                                                <div class="menuCollezione">
                                                    <span class="closeMenu" onclick="hideMenuCollezione(this)">
                                                        &times
                                                    </span>
                                                    <?php foreach($mazzi as $mazzo):?>
                                                        <form action="./moveTo" method="get">
                                                            <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                            <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                            <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                            <input type="hidden" name="foil" value="<?php echo $row["foil"]?>">
                                                            <input type="hidden" name="public" value="<?php echo $row["public"]?>">
                                                            <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                            <span class="mazzo">
                                                                <label for="into">
                                                                    <input type="submit" name="into" value="<?php echo $mazzo;?>">
                                                                </label>
                                                            </span>
                                                        </form>
                                                    <?php endforeach; ?>
                                                    <form action="./moveTo" method="get">
                                                        <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                        <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                        <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                        <input type="hidden" name="foil" value="<?php echo $row["foil"]?>">
                                                        <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                        <label for="into">nome nuovo mazzo <input type="text" name="into" id="newInto"></label>
                                                        <input type="submit" value="crea nuovo mazzo">
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <img src="https://swudb.com/cards/<?php echo $row["espansione"]."/".sprintf("%0".$numeri[$row["espansione"]]."d", $row["numero"]).".png";?>" height="100vh">
                                            <?php endif; ?>
                                        <?php endif;?>
                                    </td>
                                    <td>
                                        <?php if(unserialize($_SESSION["user"])->getID() == $row["codUtente"]):?>
                                            <?php if(!isset($precedente) or $row["mazzo"] !== $precedente){?>
                                                <form action="exportDeck">
                                                    <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"];?>">
                                                    <input type="image" src="https://imgs.search.brave.com/8lh3CqznYphqQs7SYu1sy98oK3cOR-SqnP2fN0vs8UQ/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9pY29u/cy52ZXJ5aWNvbi5j/b20vcG5nLzEyOC9t/aXNjZWxsYW5lb3Vz/L2Vhc2Vtb2ItaWNv/bi9leHBvcnQtZmls/ZS0xLnBuZw" alt="export as text" class="exportDeck">
                                                </form>
                                            <?php }else{?>
                                                <form action="moveTo">
                                                    <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                    <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                    <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                    <input type="hidden" name="foil" value="<?php echo $row["foil"]?>">
                                                    <input type="hidden" name="into" value="mancanti di <?php echo $row["mazzo"];?>">
                                                    <input type="hidden" name="public" value="1">
                                                    <input type="hidden" name="from" value="mazzi">
                                                    <input type="image" src="https://imgs.search.brave.com/tOlbrzqxPM8E8cIRWHPtdsVrBcMPZfG3NHK4TIWZJoc/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9hc3Nl/dHMuZHJ5aWNvbnMu/Y29tL3VwbG9hZHMv/aWNvbi9wcmV2aWV3/Lzc0MTQvc21hbGxf/MXhfbGlzdC5wbmc" alt="mancante">
                                                </form>
                                            <?php }?>
                                        <?php endif;?>
                                    </td>
                                    <?php foreach($row as $key=>$cell): ?>
                                        <td>
                                            <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                            <a href="<?php echo "https://www.swudb.com/card/".$row["espansione"]."/".sprintf("%0".$numeri[$row["espansione"]]."d", $row["numero"])?>" target="_blank">
                                            <?php 
                                            endif;
                                            echo $cell;
                                            if(!(!isset($precedente) or $row["mazzo"] !== $precedente)):
                                                if($key === "nome"){
                                                    ?><img class="card-hover" src="https://www.swudb.com/cards/<?php echo $row["espansione"] . "/" . sprintf("%0" . $numeri[$row["espansione"]] . "d", $row["numero"]);?>.png"><?php
                                                }?>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach;
                                    $precedente = $row["mazzo"];?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </body>
    <?php } ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log("entrato in listener DOMContentLoaded")
            Array.from(document.getElementsByClassName("exportDeck")).forEach(button => {
                button.addEventListener('click', function(event) {
                    console.log("click")
                    event.stopPropagation();
                });
            });
        });
    </script>
</html>