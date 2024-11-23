<?php
    require_once "header.php";
    if(isset($_GET["from"])){
        $query = "insert into mazzi\n values('".$_GET["into"]."', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().", ".$_GET["foil"].", ".$_GET["public"].")";
        var_dump($query);
        var_dump($get);
        $conn-> query($query);
        ?><meta http-equiv="refresh" content="0; url=<?php echo "remove?".http_build_query($_GET);?>"><?php
    }