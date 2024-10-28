<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert to collezione</title>
        <link rel="stylesheet" href="./css/insertTo.css">
    </head>
    <?php
        require_once("Utente.php");
        session_start();
        if(!isset($_SESSION["user"])){
            echo "<meta http-equiv=\"refresh\" content=\"0; url=./logIn\">";
        }
    ?>
    <body>
        <form action="insertTo">
            <input type="hidden" name="mazzo" values="Collezione">
            <br>
            <label for="espansione">inserisci il set della carta da inserire </label><input type="text" name="espansione" placeholder="set">
            <br>
            <label for="numero">inserisci il numero della carta da inserire </label><input type="number" name="numero" placeholder="numero">
            <br>
            <input type="hidden" name="from" values="Collezione">
            <input type="submit" value="aggiungi carta">
        </form>
        <span id="result" class="<?php echo $_POST["resultClass"];?>">
            <?php echo $_POST["resultText"];?>
        </span>
    </body>
</html>