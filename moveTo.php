<?php
    require_once "header.php";
    if(isset($_GET["from"])){
        moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["foil"], $_GET["mazzo"]);
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"]?>"><?
    }