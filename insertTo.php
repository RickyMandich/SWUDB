<!DOCTYPE html>
<html lang="it" class="<?php echo $file;?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Insert To</title>
    </head>
    <?php
        require_once("header.php");
        if(!isset($_SESSION["user"])){
            ?><meta http-equiv="" content="0; url=./login?from=<?php echo $file;?>"><?php
        }
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
        <meta http-equiv="refresh" content="0; url=insertTo<?php echo $_GET["from"].'?'.http_build_query(array('resultClass' => $result["resultClass"], 'resultText' => $result["resultText"], 'espansione' => strtoupper($_GET["espansione"]), 'numero' => sprintf("%0".$numeri[strtoupper($_GET["espansione"])]."d", $_GET["numero"]), 'mazzo' => $_GET["mazzo"]));?>">
        <?php endif; ?>
    </body>
</html>