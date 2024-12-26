<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert to deck</title>
        <link rel="stylesheet" href="./css/insertTo.css">
    </head>
    <?php
        require_once("header.php");
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="refresh" content="0; url=./login?from=<?php echo $file; ?>"><?php
        }
    ?>
    <body>
        <form action="insertTo">
            <label for="mazzo">inserisci il nome del mezzo in cui vuoi inserire la carta </label><input list="mazzi" type="text" value="<?php if(isset($_GET["mazzo"])) echo $_GET["mazzo"];?>" name="mazzo" placeholder="mazzo">
            <datalist id="mazzi">
                <?php 
                    $rs = $GLOBALS["conn"]->query("select nome as mazzo from mazzi where codUtente = ".unserialize($_SESSION["user"])->getID()." and not nome like 'mancanti di %' order by nome");
                    $mazzi = [];
                    while($line = $rs->fetch_assoc()){
                        array_push($mazzi, $line["mazzo"]);
                    }
                ?>
                <?php foreach($mazzi as $m):?>
                    <option value="<?php echo $m?>">
                <?php endforeach;?>
            </datalist>
            <br>
            <label for="espansione">inserisci il set della carta da inserire </label><input list="espansioni" type="text" name="espansione" placeholder="set" value="<?php echo $_GET["espansione"];?>">
            <datalist id="espansioni">
                <?php 
                    $rs = $GLOBALS["conn"]->query("select distinct espansione from carte order by uscita, espansione");
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
            <label for="numero">inserisci il numero della carta da inserire </label><input <?php if(isset($_GET["espansione"])){echo "autofocus";}?> type="number" name="numero" placeholder="numero">
            <br>
            <label for="foil">inserisci se la carta è foil <input type="checkbox" name="foil" id="foil"></label>
            <br>
            <input type="hidden" name="from" value="Deck">
            <input type="submit" value="aggiungi carta">
        </form>
        <form action="remove">
            <input type="hidden" name="from" value="insertToDeck?mazzo=<?php echo $_GET["mazzo"]?>&espansione=<?php echo $_GET["espansione"] ?>">
            <input type="hidden" name="numero" value="<?php echo $_GET["numero"]?>">
            <input type="hidden" name="mazzo" value="<?php echo $_GET["mazzo"]?>">
            <input type="hidden" name="espansione" value="<?php echo $_GET["espansione"]?>">
            <input type="submit" value="annulla">
        </form>
        <span id="result" class="<?php echo isset($_GET["resultClass"]) ? $_GET["resultClass"] : "hidden"?>">
            <?php echo isset($_GET["resultText"]) ? $_GET["resultText"] : ""?>
        </span>
        <img src="https://swudb.com/cards/<?php echo $_GET["espansione"]."/".sprintf("%0".$numeri[$_GET["espansione"]]."d",$_GET["numero"]);?>.png" class="<?php if($_GET["foil"]){echo "foil";}?>">
    </body>
    <style>
        body{
            align-items: center;
        }

        body img{
            max-width: 35vw;
            max-height: 35vw;
        }
    </style>
</html>