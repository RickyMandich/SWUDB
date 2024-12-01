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
                unset($j);
                for($j = 0;$j<=$deep;$j++){
                    echo "\t";
                }
                echo "$i-->";
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