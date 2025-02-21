<?php require_once "header.php"; ?>
<!DOCTYPE html>
<html lang="it" class="<?php echo $file; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $file; ?></title>
        <link rel="stylesheet" href="./css/cartaPopUp.css">
        <link rel="stylesheet" href="css/profilo.css">
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
        $resultSet = $GLOBALS["conn"]->query("select * from carte where nome like '%".(isset($_GET["nome"])?str_replace("'", "\'", $_GET["nome"]):"")."%'".$queryEspansione);
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
                <input type="text" name="nome" value="<?php echo $_GET["nome"] ?? ""; ?>">
                <select multiple name="espansione[]" id="espansione">
                    <option <?php if(isset($_GET["espansione"]) && in_array("all", $_GET["espansione"])) echo "selected"; ?>>all</option>
                    <?php foreach($espansioni as $set) : ?>
                        <option <?php if(isset($_GET["espansione"]) && in_array($set, $_GET["espansione"])) echo "selected"; ?>>
                            <?php echo $set; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" value="cerca">
            </form>
            <?php
                $_GET["nome"] = str_replace("'", "\'", $_GET["nome"]);
            ?>
            <div class="decks-section">
                <div class="decks-container">
                    <?php if(count($rs) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <?php if(isset($_SESSION["user"])): ?>
                                        <th>Aggiungi alla collezione</th>
                                    <?php endif; ?>
                                    <?php foreach($rs[0] as $key => $column): ?>
                                        <th><?php echo htmlspecialchars($key); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $toggle = false;?>
                                <?php foreach($rs as $card): ?>
                                    <tr class="<?php echo $toggle ? "oddCard" : "notOddCard"; ?>">
                                        <?php if(isset($_SESSION["user"])): ?>
                                            <td>
                                                <form action="./insertTo" method="get">
                                                    <input type="hidden" name="mazzo" value="Collezione">
                                                    <input type="hidden" name="espansione" value="<?php echo $card["espansione"]; ?>">
                                                    <input type="hidden" name="numero" value="<?php echo $card["numero"]; ?>">
                                                    <input type="hidden" name="from" value="<?php echo "." . $_SERVER['REQUEST_URI']; ?>">
                                                    <input type="image" src='img/collezione.png' width='100px' height='auto' alt="Invia il form">
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                        <?php foreach($card as $value): ?>
                                            <td>
                                                <a href="<?php echo "https://swudb.com/card/" . $card["espansione"] . "/" . sprintf("%0" . $numeri[$card["espansione"]] . "d", $card["numero"]); ?>" target="_blank">
                                                    <?php echo htmlspecialchars($value); ?>
                                                    <?php if($value === $card["nome"]): ?>
                                                        <img class="card-hover" src="https://swudb.com/images/cards/<?php echo $card["espansione"] . "/" . sprintf("%0" . $numeri[$card["espansione"]] . "d", $card["numero"]); ?>.png">
                                                    <?php endif; ?>
                                                </a>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        nessuna carta trovata
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </body>
    <style>
        
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: darkblue;
            color: white;
        }

        .oddCard {
            background-color: #6b98c1;
            cursor: pointer;
        }

        .notOddCard {
            background-color: lightgray;
            cursor: pointer;
        }
    </style>
</html>