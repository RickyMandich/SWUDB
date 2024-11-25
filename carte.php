<?php require_once "header.php";?>
<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $file?></title>
        <link rel="stylesheet" href="./css/cartaPopUp.css">
        <link rel="stylesheet" href="css/mazzi.css">
    </head>
    <?php
        function compareElements($el1, $el2) {
            // Definisco l'ordine dei tipi
            $tipoOrder = ['Leader', 'Base'];
            
            // Definisco l'ordine degli aspetti primari
            $primaryAspectOrder = ['Blue', 'Green', 'Red', 'Yellow'];
            
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
            if ($el1['numero'] < $el2['numero']) {
                return -1;
            }
            
            if ($el1['numero'] > $el2['numero']) {
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
        if(count($_GET)>0 and $_GET["nome"] === "" and $_GET["espansione"] === "all"): ?>
            <meta http-equiv="refresh" content="0; url=carte">
        <?php endif;
        $queryEspansione = "";
        if(isset($_GET["espansione"])){
            for($i = 0;$i<count($_GET["espansione"]);$i++):
                if($i===0){
                    $queryEspansione .= "and (";
                }
                if($_GET["espansione"][$i] !== "all"){
                    $queryEspansione = $queryEspansione."espansione = '".$_GET["espansione"][$i]."' ";
                }else{
                    $queryEspansione = "";
                    break;
                }
                if($i != count($_GET["espansione"])-1){
                    $queryEspansione = $queryEspansione."or ";
                }else{
                    $queryEspansione .= ") ";
                }
            endfor;
        }
        $resultSet = $conn->query("select * from carte where nome like '%".(isset($_GET["nome"])?$_GET["nome"]:"")."%'".$queryEspansione);
        unset($rs);
        $rs = [];
        while($line = $resultSet ->fetch_assoc()){
            array_push($rs, $line);
        }
        $rs = mergeSort($rs);
    ?>
    <body>
        <div class="container">
            <form action="carte">
                <input type="text" name="nome" value="<?php echo $_GET["nome"] ?? ""?>">
                <select multiple name="espansione[]" id="espansione">
                    <option <?php if(isset($_GET["espansione"]) and in_array("all", $_GET["espansione"])) echo "selected" ?>>all</option>
                    <?php foreach($espansioni as $set) : ?>
                        <option <?php if(isset($_GET["espansione"]) and in_array($set, $_GET["espansione"])) echo "selected" ?>>
                            <?php echo $set ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" value="cerca">
            </form><?php
            $_GET["nome"] = str_replace("'", "\'", $_GET["nome"]);
            ?>
            <div class="decks-section">
                <div class="decks-container">
                    <?php if(count($rs)> 0): ?>
                        <table>
                            <thead>
                                <tr class="deck-header">
                                <?php if(isset($_SESSION["user"])): ?>
                                    <td>aggiungi alla collezione</td>
                                <?php endif; ?>
                                    <?php foreach($rs[0] as $column): ?>
                                        <td>
                                            <?php echo $column; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($i = 1;$i < count($rs); $i++): ?>
                                <tr class="card-in-deck-row deck-card">
                                    <?php if(isset($_SESSION["user"])): ?>
                                        <td style="max-width: 100vw">
                                            <form action="./insertTo" method="get">
                                                <input type="hidden" name="mazzo" value="Collezione">
                                                <input type="hidden" name="espansione" value="<?php echo $rs[$i]["espansione"]?>">
                                                <input type="hidden" name="numero" value="<?php echo $rs[$i]["numero"]?>">
                                                <input type="hidden" name="from" value="<?php echo ".".$_SERVER['REQUEST_URI'];?>">
                                                <input type="image" src='img/collezione.png' width='100px' height='auto' alt="Invia il form">
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                    <?php foreach($rs[$i] as $value): ?>
                                    <td>
                                        <a href="<?php echo "https://swudb.com/card/" . $rs[$i]["espansione"] . "/" . sprintf("%0" . $numeri[$rs[$i]["espansione"]] . "d", $rs[$i]["numero"]);?>" target="_blank">
                                            <?php echo $value;
                                                if($value === $rs[$i]["nome"]){
                                                    ?><img class="card-hover" src="https://swudb.com/cards/<?php echo $rs[$i]["espansione"] . "/" . sprintf("%0" . $numeri[$rs[$i]["espansione"]] . "d", $rs[$i]["numero"]);?>.png"><?php
                                                }; ?>
                                        </a>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        nessuna carta trovata
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </body>
</html>