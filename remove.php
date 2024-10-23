<?php
    require_once("Utente.php");
    session_start();
    if(!isset($_SESSION["user"])){
        ?><meta http-equiv="refresh" content="0; url=./login"><?php
    }
    if(isset($_GET["from"])){
        $conn = new mysqli("localhost","root","Minecraft35?", "starwarsunlimited", 3306);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $conn->query("delete from mazzi where mazzo='".$_GET["mazzo"]."' and espansione = '".$_GET["espansione"]."' and numero = ".$_GET["numero"]);
        $modifiche = $conn->affected_rows;
        echo $modifiche;
        echo "<br>";
        while($modifiche>1){
            $conn-> query("insert into mazzi values('".$_GET["mazzo"]."', '".$_GET["espansione"]."', ".$_GET["numero"].", ".unserialize($_SESSION["user"])->getID().")");
            $modifiche--;
        }
        echo $modifiche
        ?><meta http-equiv="refresh" content="0; url=./<?php echo $_GET["from"];?>"><?php
    }else{
        ?><meta http-equiv="refresh" content="0; url=./profilo"><?php
    }