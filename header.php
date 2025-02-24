<?php
    session_start();
    require_once "classi/Utente.php";
    require_once "classi/Deck.php";
    require_once "classi/Card.php";
    require_once "classi/Cards.php";
    require_once "orderCard.php";
    require_once "variabili.php";
    echo __DIR__;
    /**
     * stampa di un oggetto che mi mostra tutti gli eventuali valori e array che lo compongono in modo ricorsivo con indentazione relativa alla profondità
     * @param mixed $line
     * @param int $deep
     * @return void
     */
    function printlnd($line, $deep = 0, $name){
        if(gettype($line) == 'array'){
            echo "$name-->{<br>";
            foreach($line as $i => $value){
                unset($j);
                for($j = 0;$j<=$deep;$j++){
                    echo "&nbsp;&nbsp;";
                }
                printlnd($value, $deep+1, $i);
            }
            unset($j);
            for($j = 0;$j<$deep;$j++){
                echo "&nbsp;&nbsp;";
            }
            echo "}<br>";
        }else{
            echo $line;
            echo "<br>";
        }
    }
?>