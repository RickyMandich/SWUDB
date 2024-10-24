<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>mazzi</title>
        <link rel="stylesheet" href="css/mazzi.css">
        <script src="js/profilo.js"></script>
    </head>
    <?php
        require_once("Utente.php");
        session_start();
        if (!isset($_SESSION["user"])){
            ?>
            <meta http-equiv="refresh" content="0; url=./logIn">
            <?php
        }
        $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $resultSet = $conn->query("select m.mazzo, c.* from mazzi m, carte c where m.espansione = c.espansione and m.numero = c.numero and m.codUtente = ". unserialize($_SESSION["user"])->getID()." order by mazzo, numero, espansione");
        $deck = [];
        $precedente;
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
    ?>
    <body>
        <div class="container">
            <div class="decks-section">
                <h2>I Tuoi Mazzi</h2>
                <div class="decks-container">
                    <table>
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
                        <tbody>
                            <?php 
                            $precedente;
                            foreach($deck as $row): ?>
                                <tr class="<?php if(!isset($precedente) or $row["mazzo"] !== $precedente) echo "deck-header"; else echo "deck-card";?>">
                                    <td>
                                        <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                            <form action="./remove">
                                                <input type="hidden" name="mazzo" value="<?php echo $row["mazzo"]?>">
                                                <input type="hidden" name="espansione" value="<?php echo $row["espansione"]?>">
                                                <input type="hidden" name="numero" value="<?php echo $row["numero"]?>">
                                                <input type="hidden" name="from" value="<?php echo "profilo"?>">
                                                <input type='image' src='img/rimuovi.png' width='100vw' height='auto' alt='Invia il form'>
                                            </form>
                                        <?php else: ?>
                                            <img src="https://swudb.com/cards/<?php echo $row["espansione"]."/".sprintf("%03d", $row["numero"])."-portrait.png";?>" width="100vw">
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach($row as $cell): ?>
                                        <td>
                                            <?php if(!(!isset($precedente) or $row["mazzo"] !== $precedente)): ?>
                                            <a href="<?php echo "https://www.swudb.com/card/".$row["espansione"]."/".sprintf("%03d", $row["numero"])?>" target="_blank">
                                            <?php 
                                            endif;
                                            echo $cell;
                                            if(!(!isset($precedente) or $row["mazzo"] !== $precedente)):?>
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
</html>