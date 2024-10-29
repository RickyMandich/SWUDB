<?php
    session_start();
    require_once("classi/Utente.php");
    $_SESSION["user"] = new Utente("ssppoocckk", "13", "ricky.mandich@gmail.com", "Minecraft35?");
    echo isset($_SESSION["user"]);