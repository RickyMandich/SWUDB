<?php
    session_start();
    require_once "classi/Utente.php";
    require_once "classi/Deck.php";
    require_once "classi/Card.php";
    require_once "classi/Cards.php";
    require_once "variabili.php";
    function printlnd($line, $deep){
        if(gettype($line) == 'array'){
            echo "{<br>";
            foreach($line as $i => $value){
                echo $i;
                for($i = 0;$i<$deep;$i++){
                    echo "--";
                }
                echo $deep>0?">":"";
                printlnd($value, $deep+1);
            }
            echo "}<br>";
        }else{
            echo $line;
            echo "<br>";
        }
    }

    function println($line){
        printlnd($line, 0);
    }