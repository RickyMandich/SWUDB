<?php
    session_start();
    require_once "classi/Utente.php";
    require_once "classi/Deck.php";
    require_once "classi/Card.php";
    require_once "classi/Cards.php";
    require_once "variabili.php";
    function printlnd($line, $deep){
        echo "<br>";
        for($i = 0;$i<$deep;$i++){
            echo "--";
        }
        echo "$line->".gettype($line);
        if(gettype($line) == 'array'){
            foreach($line as $i/* => $value*/){
                println($line);
            }
        }else{
            echo $line;
        }
    }

    function println($line){
        printlnd($line, 0);
    }