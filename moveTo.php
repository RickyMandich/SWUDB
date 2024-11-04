<?php
    require_once("remove.php");
    if(isset($_GET["from"])){
        if($_GET["mazzo"] !== "Collezione"){
            $conn-> query("insert into mazzi values('Collezione', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().")");
        }else{
            $conn-> query("insert into mazzi values('".$_GET["into"]."', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().")");
        }
    }