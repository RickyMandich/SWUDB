<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Carte</title>
    </head>
    <?php
    function printlnd($line, $deep = 0, $name){
        if(gettype($line) == 'array' || gettype($line) == 'object'){
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
            try{
                echo $line;
            }catch(Error $e){
                echo "Errore: " . $e->getMessage();
                echo gettype($line);
            }
            echo "<br>";
        }
    }
    ?>
    <body>
        <?php printlnd($model, 0, "model"); ?>
    </body>
</html>