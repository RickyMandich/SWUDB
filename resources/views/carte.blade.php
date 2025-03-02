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
                echo "$name=>$line(" . gettype($line) . ")";
            }catch(Error $e){
                echo "Errore: " . $e->getMessage();
                echo gettype($line);
            }
            echo "<br>";
        }
    }
    ?>
    <body>
        <form action="/carte">
            <label for="nome">insersci il nome della carta</label>
            <input type="text" name="nome" id="nome" value="<?php echo "$nome" ?>">
            <input type="submit" value="cerca">
        </form>
        <form action="/carte">
            <input type="submit" value="cancella parametri di ricerca">
        </form>
        @if ($empty)
            <div>nessuna carta corrisponde ai criteri di ricerca</div>
        @else
            <table>
                <tr>
                    @foreach($model[0] as $attributo)
                        <th>{{ $attributo }}</th>
                    @endforeach
                </tr>
                @foreach($model as $carta)
                    <tr>
                        @foreach ($carta as $attributo)
                        <td>{{ $attributo }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        @endif
    </body>
</html>
<style>
    form{
        display: inline-block;
    }
</style>