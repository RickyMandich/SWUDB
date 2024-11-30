<?php
    require_once "header.php";
    if(isset($_GET["from"])){
        $exist = $conn->query("select id from mazzi where nome = '".$_GET["into"]."';");
        if(!$exist = $exist->fetch_assoc()){
            $conn->query("insert into mazzi (nome, public, codUtente) values('".$_GET["into"]."', 0, ".unserialize($_SESSION["user"])->getID().");");
            $id = $conn->query("select id from mazzi where nome = '".$_GET["into"]."';")->fetch_assoc()["id"];
        }else{
            $id = $exist["id"];
        }
        $query = "insert into composizione\n values(".$id.", '".$_GET["espansione"]."', ".$_GET["numero"].", ".$_GET["foil"].")";
        echo "<br>query:";
        var_dump($query);
        echo "<br>get:";
        var_dump($_GET);
        $conn-> query($query);
        ?>
            <meta http-equiv="refresh" content="0; url=<?php echo "remove?".http_build_query($_GET);?>">
        <?php
    }