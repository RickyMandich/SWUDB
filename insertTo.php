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
            $_GET["espansione"] = strtoupper($_GET["espansione"]);
            if(exist($_GET["espansione"], $_GET["numero"])){
                if($_GET["mazzo"] === "Collezione" or quante($_GET["mazzo"], $_GET["espansione"], $_GET["numero"])<3){
                    $id = $GLOBALS["conn"]->query("select id from mazzi where nome = '".$_GET["mazzo"]."';");
                    if(!$id = $id->fetch_assoc()){
                        $GLOBALS["conn"]->query("insert into mazzi (nome, public, codUtente) values('".$_GET["mazzo"]."', 0, ".unserialize($_SESSION["user"])->getID().");");
                    }
                    echo "insert into composizione (idMazzo, espansione, numero, foil) values(".$id["id"].", '".$_GET["espansione"]."', ".$_GET["numero"].", ".($_GET["foil"] === 'on' ? "true" : "false").");";
                    $result = $GLOBALS["conn"]->query("insert into composizione (idMazzo, espansione, numero, foil) values(".$id["id"].", '".$_GET["espansione"]."', ".$_GET["numero"].", ".($_GET["foil"] === 'on' ? "true" : "false").");");
                    if($result === true){
                        $resultClass = "success";
                        $resultText = "carta aggiunta";
                    }else{
                        $resultClass = "failed";
                        $resultText = $GLOBALS["conn"]->error;
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
        <meta http-equiv="refresh" content="0; url=insertTo<?php echo $_GET["from"].'?'.http_build_query(array('resultClass' => $resultClass, 'resultText' => $resultText, 'espansione' => $_GET["espansione"], 'numero' => $_GET["numero"], 'mazzo' => $_GET["mazzo"]));?>">
        <?php endif; ?>
    </body>
</html>