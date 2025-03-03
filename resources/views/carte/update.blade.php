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
            echo "$name=>$line(" . gettype($line) . ")";
        }catch(Error $e){
            echo "Errore: " . $e->getMessage();
            echo gettype($line);
        }
        echo "<br>";
    }
}?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Update</title>
    </head>
    <body>
        <?php printlnd($keys, 0, "keys"); ?>
        <?php //printlnd($data, 0, "data"); ?>
        @foreach ($result as $card)
        ho inserito {{$card["nome"]}}, {{$card["titolo"]}} ({{$card["espansione"]}}-{{$card["numero"]}})
        <br>
        @endforeach
    </body>
</html>