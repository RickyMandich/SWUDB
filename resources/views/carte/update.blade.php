<?php
use App\Http\Controllers\ControllerCarte;
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
        @if ($empty)
            non ci sono nuove carte<br>
        @else
            ti ho elencato le carte che ho inserito<br>
        @endif
        @foreach ($result as $card)
        <?php echo "ho inserito {$card["nome"]}, {$card["titolo"]} ({$card["id"]})<br>"?>
        @endforeach
    </body>
</html>