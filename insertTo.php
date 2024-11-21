<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert To</title>
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
        };
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
        };
        require_once("header.php");
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="" content="0; url=./logIn?from=<?php echo $file; ?>"><?php
        }
        $resultClass = "hidden";
        $resultText = "";
        if(isset($_GET["mazzo"])){
            $_GET["espansione"] = strtoupper($_GET["espansione"]);
            $conn = new mysqli("localhost","swudb","", "my_swudb", 3306);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            if(exist($_GET["espansione"], $_GET["numero"])){
                var_dump($_GET);
                if($_GET["mazzo"] === "Collezione" or quante($_GET["mazzo"], $_GET["espansione"], $_GET["numero"])<3){
                    $result = $conn->query("insert into mazzi (mazzo, espansione, numero, foil, codUtente) values('".$_GET["mazzo"]."', '".$_GET["espansione"]."', ".$_GET["numero"].", ".($_GET["foil"] === 1 ? "true" : "false").", ".unserialize($_SESSION["user"])->getID().");");
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
        if(!isset($_GET["from"])) $_GET["from"] = "Deck";
    ?>
    <body>
        <?php
        if(str_starts_with($_GET["from"], "./carte")): ?>
            <meta http-equiv="refresh" content="0; url=<?php echo $_GET["from"];?>">
        <?php else: ?>
        <meta http-equiv="refresh" content="15; url=insertTo<?php echo $_GET["from"].'?'.http_build_query(array('resultClass' => $resultClass, 'resultText' => $resultText, 'espansione' => $_GET["espansione"], 'numero' => $_GET["numero"], 'mazzo' => $_GET["mazzo"]));?>">
        <?php endif; ?>
    </body>
</html>