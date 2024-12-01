<?php
    require_once("header.php");
    if(!isset($_SESSION["user"])){
        ?><meta http-equiv="refresh" content="0; url=./logIn?from=<?php echo $file; ?>"><?php
    }
    if(isset($_GET["from"])){
        $GLOBALS["conn"]->query("DELETE FROM composizione 
              WHERE idMazzo = (SELECT id FROM mazzi WHERE nome = '".$_GET["mazzo"]."')
                AND espansione = '".$_GET["espansione"]."'
                AND numero = ".$_GET["numero"].";");
        $modifiche = $GLOBALS["conn"]->affected_rows;
        echo $modifiche;
        echo "<br>";
        while($modifiche>1){
            echo "insert into composizione values(SELECT id FROM mazzi WHERE nome = '".$_GET["mazzo"]."'), '".$_GET["espansione"]."', ".$_GET["numero"].", ".$_GET["foil"].")";
            $GLOBALS["conn"]-> query("insert into composizione values(SELECT id FROM mazzi WHERE nome = '".$_GET["mazzo"]."'), '".$_GET["espansione"]."', ".$_GET["numero"].", ".$_GET["foil"].")");
            $modifiche--;
        }
        echo $modifiche
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"];?>"><?php
    }else{
        ?><meta http-equiv="refresh" content="0; url=./profilo"><?php
    }