<?php require_once "header.php"?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $file?></title>
    </head>
    <body>
        <form action="compare">
            <label for="espansione1">inserisci il set della prima carta da confrontare </label><input list="espansioni" type="text" name="espansione1" placeholder="set" value="<?php echo $_GET["espansione"];?>">
            <datalist id="espansioni">
                <?php 
                    $rs = $GLOBALS["conn"]->query("select distinct espansione from carte order by uscita");
                    $espansioni = [];
                    while($line = $rs->fetch_assoc()){
                        array_push($espansioni, $line["espansione"]);
                    }
                ?>
                <?php foreach($espansioni as $e):?>
                    <option value="<?php echo $e?>">
                <?php endforeach;?>
            </datalist>
            <br>    
            <label for="numero1">inserisci il numero della prima carta da confrontare </label><input <?php if(isset($_GET["espansione"])){echo "autofocus";}?> type="number" name="numero1" placeholder="numero">
            <br>
            <label for="espansione2">inserisci il set della seconda carta da confrontare </label><input list="espansioni" type="text" name="espansione2" placeholder="set" value="<?php echo $_GET["espansione2"];?>">
            <datalist id="espansioni">
                <?php 
                    $rs = $GLOBALS["conn"]->query("select distinct espansione from carte order by uscita");
                    $espansioni = [];
                    while($line = $rs->fetch_assoc()){
                        array_push($espansioni, $line["espansione"]);
                    }
                ?>
                <?php foreach($espansioni as $e):?>
                    <option value="<?php echo $e?>">
                <?php endforeach;?>
            </datalist>
            <br>
            <label for="numero2">inserisci il numero della seconda carta da confrontare </label><input <?php if(isset($_GET["espansione"])){echo "autofocus";}?> type="number" name="numero2" placeholder="numero">
            <br>
            <input type="submit" value="confronta">
        </form>
        <?php if(isset($_GET["espansione1"])):
            $espansione1 = $_GET["espansione1"];
            $numero1 = $_GET["numero1"];
            $espansione2 = $_GET["espansione2"];
            $numero2 = $_GET["numero2"];
            $carta1 = $GLOBALS["conn"]->query("select * from carte where espanasione = $espansione1 and numero = $numero1")->fetch_assoc();
            $carta2 = $GLOBALS["conn"]->query("select * from carte where espanasione = $espansione2 and numero = $numero2")->fetch_assoc();
            ?>
            <div>
                <table>
                    <?php
                        ?><tr><?php
                        foreach($carta1 as $column=>$value){
                            ?><td><?php
                            echo $column;
                            ?></td><?php
                        }
                        ?></tr><?php
                        if(compareElements($carta1, $carta2)){
                            ?><tr><?php
                            foreach($carta1 as $value){
                                ?><td><?php
                                echo $value;
                                ?></td><?php
                            }
                            ?></tr><?php
                            ?><tr><?php
                            foreach($carta2 as $value){
                                ?><td><?php
                                echo $value;
                                ?></td><?php
                            }
                            ?></tr><?php
                        }else{
                            ?><tr><?php
                            foreach($carta2 as $value){
                                ?><td><?php
                                echo $value;
                                ?></td><?php
                            }
                            ?></tr><?php
                            ?><tr><?php
                            foreach($carta1 as $value){
                                ?><td><?php
                                echo $value;
                                ?></td><?php
                            }
                            ?></tr><?php
                        }
                    ?>
                </table>
            </div>
        <?php endif;?>
    </body>
</html>