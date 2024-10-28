<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert to deck</title>
        <link rel="stylesheet" href="./css/insertTo.css">
    </head>
    <?php
        function exist($espansione, $numero){
            $connected = false;
            while(!$connected){
                $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
                $connected = true;
                if ($conn->connect_error) {
                    $connected = false;
                }
            }
            $resultSet = $conn->query("select * from carte where espansione = '".$espansione."' and numero = ".$numero);
            if($resultSet->fetch_assoc()){
                return true;
            }
            return false;
        }
        function quante($mazzo, $espansione, $numero){
            $connected = false;
            while(!$connected){
                $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
                $connected = true;
                if ($conn->connect_error) {
                    $connected = false;
                }
            }
            $query = "select * from mazzi where mazzo = '".$mazzo."' and espansione = '".$espansione."' and numero = ".$numero." and codUtente = ".unserialize($_SESSION["user"])->getID();
            echo $query;
            $resultSet = $conn->query($query);
            $quante = 0;
            while($resultSet->fetch_assoc()){
                $quante++;
            }
            echo "<br>".$quante;
            return $quante;
        }
        require_once("Utente.php");
        session_start();
        if(!isset($_SESSION["user"])){
            echo "<meta http-equiv=\"refresh\" content=\"0; url=./logIn\">";
        }
        $resultClass = "hidden";
        $resultText = "";
        if(isset($_GET["mazzo"])){
            $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            if(exist($_GET["espansione"], $_GET["numero"])){
                if(quante($_GET["mazzo"], $_GET["espansione"], $_GET["numero"])<3){
                    $result = $conn->query("insert into mazzi values('".$_GET["mazzo"]."', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().");");
                    if($result === true){
                        $resultClass = "success";
                        $resultText = "carta aggiunta";
                    }else{
                        $resultClass = "failed";
                        $resultText = $conn->error;
                    }
                }else{
                    $resultClass = "failed";
                    $resultText = "hai già tre copie di questa carta";
                }
            }else{
                $resultClass = "failed";
                $resultText = "questa carta non esiste";
            }
        }
    ?>
    <body>
        <form action="insertToDeck">
            <label for="mazzo">inserisci il nome del mezzo in cui vuoi inserire la carta </label><input type="text" name="mazzo" placeholder="mazzo">
            <br>
            <label for="espansione">inserisci il set della carta da inserire </label><input type="text" name="espansione" placeholder="set">
            <br>
            <label for="numero">inserisci il numero della carta da inserire </label><input type="number" name="numero" placeholder="numero">
            <br>
            <input type="submit" value="aggiungi carta">
        </form>
        <span id="result" class="<?php echo $resultClass?>">
            <?php echo $resultText?>
        </span>
    </body>
</html>