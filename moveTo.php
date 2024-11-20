<?php
    require_once "header.php";
    if(isset($_GET["from"])){
        if($_GET["mazzo"] !== "Collezione"){
            $conn-> query("insert into mazzi values('Collezione', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().", ".$_GET["foil"].")");
        }else{
            $conn-> query("insert into mazzi values('".$_GET["into"]."', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().", ".$_GET["foil"].")");
        }
        ?><meta http-equiv="refresh" content="0; url=<?php echo "remove?".http_build_query($_GET);?>"><?php
    }