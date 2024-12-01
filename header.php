<?php
    session_start();
    require_once "classi/Utente.php";
    require_once "classi/Deck.php";
    require_once "classi/Card.php";
    require_once "classi/Cards.php";
    require_once "variabili.php";
    function println($line){
        echo "$line-->".gettype($line);
        if(gettype($line) == 'array'){
            foreach($line as $i => $value){
                echo "$i-->".println($line)."<br>";
            }
        }else{
            echo $line;
        }
    }