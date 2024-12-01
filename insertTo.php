<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert To</title>
    </head>
    <?php
        function exist($espansione, $numero){
            $resultSet = $GLOBALS["conn"]->query("select * from carte where espansione = '".$espansione."' and numero = ".$numero);
            if($resultSet->fetch_assoc()){
                return true;
            }
            return false;
        };
        function quante($mazzo, $espansione, $numero){
            $query = "select * from mazzi m, composizione c where m.id = c.idMazzo and c.espansione = '".$espansione."' and c.numero = ".$numero." and m.codUtente = ".unserialize($_SESSION["user"])->getID();
            $resultSet = $GLOBALS["conn"]->query($query);
            $quante = 0;
            while($resultSet->fetch_assoc()){
                $quante++;
            }
            return $quante;
        };
        require_once("header.php");
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="" content="0; url=./logIn?from=<?php echo $file; ?>"><?php
        }
        $resultClass = "hidden";
        $resultText = "";
        if(isset($_GET["mazzo"])){
            $result = insertTo($_GET["espansione"], $_GET["numero"], $_GET["mazzo"], $_GET["foil"]);
            $_GET["resultText"] = $result["resultText"];
            $_GET["resultClass"] = $result["resultClass"];
        }
        if(!isset($_GET["from"])) $_GET["from"] = "Deck";
    ?>
    <body>
        <?php
        if(str_starts_with($_GET["from"], "./carte")): ?>
            <meta http-equiv="refresh" content="0; url=<?php echo $_GET["from"];?>">
        <?php else: ?>
        <meta http-equiv="refresh" content="0; url=insertTo<?php echo $_GET["from"].'?'.http_build_query(array('resultClass' => $resultClass, 'resultText' => $resultText, 'espansione' => $_GET["espansione"], 'numero' => $_GET["numero"], 'mazzo' => $_GET["mazzo"]));?>">
        <?php endif; ?>
    </body>
</html>