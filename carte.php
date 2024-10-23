<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Carte</title>
        <link rel="stylesheet" href="profilo.css">
    </head>
    <?php
        $conn = new mysqli(hostname: "localhost",username: "swudb", database:"my_swudb", port:3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $resultSet = $conn->query("select * from carte where nome like '%" . ($_GET["nome"] ?? "") . "%' order by uscita, espansione, numero");
        $numeri = [];
        $rs = [];
        if( $resultSet->num_rows > 0) {
            $primo = true;
            while($row = $resultSet->fetch_assoc()){
                $line = [];
                if($primo){
                    $primo = false;
                    foreach($row as $key => $value){
                        array_push($line, $key);
                    }
                    $rs[0] = $line;
                    $line = [];
                }
                $numeri[$row["espansione"]] = strlen((string) $row["numero"]);
                foreach($row as $key => $value) array_push($line, $value);
                array_push($rs, $line);
            }
        }
        $conn->close();
    ?>
    <body>
        <form action="carte.php" method="get">
            <input type="text" name="nome">
            <input type="submit" value="cerca">
        </form>
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
                                            <input type='image' src='img/rimuovi.png' width='25px' height='auto' alt='Invia il form'>
                                        </form>
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
        <!--<table border="">
            <thead>
                <tr>
                    <?php foreach($rs[0] as $column): ?>
                        <td>
                            <?php echo $column; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for($i = 1;$i < count($rs); $i++): ?>
                    <tr>
                        <?php foreach($rs[$i] as $value): ?>
                            <td>
                                <a href="<?php echo "https://www.swudb.com/card/" . $rs[$i][0] . "/" . sprintf("%0" . $numeri[$rs[$i][0]] . "d", $rs[$i][1]);?>" target="_blank">
                                <?php echo $value; ?>
                                </a>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>-->
    </body>
</html>