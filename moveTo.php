<?php
    require_once "header.php";
    if(isset($_GET["from"])){
        moveTo($_GET["into"], $_GET["espansione"], $_GET["numero"], $_GET["foil"], $_GET["mazzo"]);
    }