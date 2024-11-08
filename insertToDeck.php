<!DOCTYPE html>
<html lang="it" class="<?php echo $match[1];?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert to deck</title>
        <link rel="stylesheet" href="./css/insertTo.css">
    </head>
    <?php
        require_once("header.php");
        if(!isset($_SESSION["user"])){
            echo "<meta http-equiv=\"refresh\" content=\"0; url=./login\">";
        }
    ?>
    <body>
        <form action="insertTo">
            <label for="mazzo">inserisci il nome del mezzo in cui vuoi inserire la carta </label><input type="text" name="mazzo" placeholder="mazzo">
            <br>
            <label for="espansione">inserisci il set della carta da inserire </label><input type="text" name="espansione" placeholder="set">
            <br>
            <label for="numero">inserisci il numero della carta da inserire </label><input type="number" name="numero" placeholder="numero">
            <br>
            <input type="hidden" name="from" values="Deck">
            <input type="submit" value="aggiungi carta">
        </form>
        <span id="result" class="<?php echo isset($_GET["resultClass"]) ? $_GET["resultClass"] : "hidden"?>">
            <?php echo isset($_GET["resultText"]) ? $_GET["resultText"] : ""?>
        </span>
    </body>
</html>