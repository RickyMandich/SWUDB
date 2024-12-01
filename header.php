<?php
    session_start();
    require_once "classi/Utente.php";
    require_once "classi/Deck.php";
    require_once "classi/Card.php";
    require_once "classi/Cards.php";
    require_once "variabili.php";
    function printlnd($line, $deep){
        for($i = 0;$i<$deep;$i++){
            echo "--";
        }
        if(gettype($line) == 'array'){
            foreach($line as $i => $value){
                echo $i;
                println($value);
            }
        }else{
            echo $line;
        }
    }

    function println($line){
        printlnd($line, 0);
    }