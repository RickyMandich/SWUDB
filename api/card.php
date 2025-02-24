<?php
require_once "/header.php";
if(isset($_GET["espansione"]) and isset($_GET["numero"])){
    $carta = $GLOBALS["conn"]->query("select * from carte where espansione = '".$_GET["espansione"]."' and numero = ".$_GET["numero"])->fetch_assoc();
    if($carta){
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($carta);
    }else{
        echo "carta non valida<br>";
        var_dump($carta);
    }
}