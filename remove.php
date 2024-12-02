<?php
    require_once("header.php");
    if(!isset($_SESSION["user"])){
        ?><meta http-equiv="refresh" content="0; url=./login?from=<?php echo $file; ?>"><?php
    }
    if(isset($_GET["from"])){
        remove($_GET["numero"], $_GET["mazzo"], $_GET["espansione"], $_GET["foil"]);
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"];?>"><?php
    }else{
        ?><meta http-equiv="refresh" content="0; url=./profilo"><?php
    }