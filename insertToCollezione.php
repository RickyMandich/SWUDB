<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert to collezione</title>
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
            <input type="hidden" name="mazzo" value="Collezione">
            <label for="espansione">inserisci il set della carta da inserire <input type="text" name="espansione" placeholder="set" value="<?php echo $_GET["espansione"];?>"></label>
            <br>
            <label for="numero">inserisci il numero della carta da inserire <input type="number" name="numero" placeholder="numero"></label>
            <br>
            <label for="foil">inserisci se la carta è foil <input type="checkbox" name="foil" id="foil"></label>
            <br>
            <input type="hidden" name="from" value="Collezione">
            <input type="submit" value="aggiungi carta">
        </form>
        <span id="result" class="<?php echo isset($_GET["resultClass"]) ? $_GET["resultClass"] : "hidden"?>">
            <?php echo isset($_GET["resultText"]) ? $_GET["resultText"] : ""?>
        </span>
        <img src="https://swudb.com/cards/<?php echo $_GET["espansione"]."/".sprintf("%0".$numeri[$_GET["espansione"]]."d",$_GET["numero"]);?>.png">
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