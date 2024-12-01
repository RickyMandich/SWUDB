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
        require_once "orderCard.php";
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
        $resultSet = $GLOBALS["conn"]->query("select * from carte where nome like '%".(isset($_GET["nome"])?$_GET["nome"]:"")."%'".$queryEspansione);
        unset($rs);
        $rs = [];
        while($line = $resultSet ->fetch_assoc()){
            $line["getNumero"] = $getNumero($line);
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
                                    <?php foreach($rs[0] as $key=>$column): ?>
                                        <td>
                                            <?php echo $key; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($i = 0;$i < count($rs); $i++): ?>
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