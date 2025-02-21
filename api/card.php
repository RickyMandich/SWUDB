<?php
require_once "header.php";
if(isset($_GET["set"]) and isset($_GET["num"])){
    $carta = $GLOBALS["conn"]->query("select * from carte where espansione = '".$_GET["set"]."' and numero = ".$_GET["num"])->fetch_assoc();
    if($carta){
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($carta);
    }else{
        echo "carta non valida<br>";
        var_dump($carta);
    }
}