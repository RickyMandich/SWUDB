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
        echo ">";
        if(gettype($line) == 'array'){
            echo "{<br>";
            foreach($line as $i => $value){
                echo $i;
                printlnd($value, $deep+1);
            }
            echo "<br>}<br>"
        }else{
            echo $line;
            echo "<br>";
        }
    }

    function println($line){
        printlnd($line, 0);
    }