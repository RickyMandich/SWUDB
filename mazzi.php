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
        $resultSet = $conn->query("select m.mazzo, m.foil, m.public, c.*, m.codUtente from mazzi m, carte c where m.espansione = c.espansione and m.numero = c.numero and (m.codUtente = ". unserialize($_SESSION["user"])->getID()." or m.public = '1') order by mazzo, numero, espansione");
        $deck = [];
        while($line = $resultSet->fetch_assoc()){
            $row = [];
            foreach($line as $key => $value){
                if($key === 'mazzo' and $line["codUtente"] !== unserialize($_SESSION["user"])->getID()){
                    $value = $value." di ".$conn->query("select nome from utenti where id = ".$line["codUtente"]);
                }
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
            array_push($mazzi, $line["mazzo"]);
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
                                <td>rimuovi carta</td>
                                <td>sposta carta</td>
                                <td>esporta mazzo come elenco di carte o segna come mancante</td>
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
                                                <input type="hidden" name="foil" value="<?php echo $row["foil"]?>">
                                                <input type="hidden" name="from" value="<?php echo "mazzi"?>">
                                                <input type='image' src='img/rimuovi.png' width='100vw' height='auto' alt='Invia il form'>
                                            </form>
                                        <?php else: ?>
                                            <img src="https://swudb.com/cards/<?php echo $row["espansione"]."/".sprintf("%0".$numeri[$row["espansione"]]."d", $row["numero"])."-portrait.png";?>" width="100vw">
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 100vw">
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
                                    </td>
                                    <td>
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
                                                <input type="hidden" name="public" value="1">
                                                <input type="hidden" name="from" value="mazzi">
                                                <input type="image" src="https://imgs.search.brave.com/tOlbrzqxPM8E8cIRWHPtdsVrBcMPZfG3NHK4TIWZJoc/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9hc3Nl/dHMuZHJ5aWNvbnMu/Y29tL3VwbG9hZHMv/aWNvbi9wcmV2aWV3/Lzc0MTQvc21hbGxf/MXhfbGlzdC5wbmc" alt="mancante">
                                            </form>
                                        <?php }?>
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