<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>mazzi</title>
        <link rel="stylesheet" href="css/mazzi.css">
        <link rel="stylesheet" href="css/cartaPopUp.css">
        <script src="js/profilo.js"></script>
    </head>
    <?php
        require_once("header.php");
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
        $resultSet = $conn->query("select m.mazzo, c.* from mazzi m, carte c where m.espansione = c.espansione and m.numero = c.numero and m.codUtente = ". unserialize($_SESSION["user"])->getID()." order by mazzo, numero, espansione");
        $deck = [];
        while($line = $resultSet->fetch_assoc()){
            $row = [];
            foreach($line as $key => $value){
                $row[$key] = $value;
            }
            if(!isset($precedente) or $precedente != $line["mazzo"]){
                $header = $row;
                foreach($header as $key => $i){
                    if($key !== "mazzo" and $key !== "espansione" and $key !== "numero") $header[$key] = null;
                }
                array_push($deck, $header);
            }
            $precedente = $line["mazzo"];
            array_push($deck, $row);
        }
        $resultSet = $conn->query("select distinct mazzo from mazzi");
        $mazzi = [];
        while($line = $resultSet->fetch_assoc()){
            if($line["mazzo"] !== "Collezione"){
                array_push($mazzi, $line["mazzo"]);
            }
        }
    ?>
    <body>
        <div class="container">
            <div class="decks-section">
                <h2>I Tuoi Mazzi</h2>
                <div class="decks-container">
                    <table>
                        <?php if (count($deck) > 0): ?>
                        <thead>
                            <tr class="deck-header">
                                <td></td>
                                <?php foreach($deck[0] as $key => $value):?>
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
                                        <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                            <form action="./remove">
                                                <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                <input type='image' src='img/rimuovi.png' width='100vw' height='auto' alt='Invia il form'>
                                            </form>
                                        <?php else: ?>
                                            <img src="https://swudb.com/cards/<?php echo $row["espansione"]."/".sprintf("%0".$numeri[$leader["espansione"]]."d", $row["numero"])."-portrait.png";?>" width="100vw">
                                            <?php echo "https://swudb.com/cards/".$row["espansione"]."/".sprintf("%0".$numeri[$leader["espansione"]]."d", $row["numero"])."-portrait.png"?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 100vw">
                                        <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                            <?php if($row["mazzo"] === "Collezione"):?>
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
                                                        <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                        <label for="into">nome nuovo mazzo <input type="text" name="into" id="newInto"></label>
                                                        <input type="submit" value="crea nuovo mazzo">
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <form action="./moveTo" method="get">
                                                    <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                    <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                    <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                    <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                    <input type="image" src='img/collezione.png' width='100px' height='auto' alt="Invia il form">
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <img src="https://swudb.com/cards/<?php echo $row["espansione"]."/".sprintf("%0".$numeri[$leader["espansione"]]."d", $row["numero"]).".png";?>" height="100vh">
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach($row as $key=>$cell): ?>
                                        <td>
                                            <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                            <a href="<?php echo "https://www.swudb.com/card/".$row["espansione"]."/".sprintf("%0".$numeri[$leader["espansione"]]."d", $row["numero"])?>" target="_blank">
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
</html>