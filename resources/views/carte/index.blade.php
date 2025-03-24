<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Carte</title>
    </head>
    <?php
    // $content = $content["*items"];
    function printlnd($line, $deep = 0, $name){
        if(gettype($line) == 'array' || gettype($line) == 'object'){
            echo "|$name|-->{<br>";
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
        <?php //printlnd($telegram, 0, 'telegram'); ?>
        <form action="/carte">
            <label for="nome">insersci il nome della carta</label>
            <input type="text" name="nome" id="nome" value="<?php echo "$nome" ?>">
            <input type="submit" value="cerca">
        </form>
        <form action="/carte">
            <input type="submit" value="cancella parametri di ricerca">
        </form>
        trovati {{ count($content) }} risultati
        <table border>
            <tr>
                @foreach($header as $attributo)
                    <th>{{ $attributo }}</th>
                @endforeach
            </tr>
            @if ($empty)
            @else
            @foreach($content as $carta)
            <tr>
                @foreach ($header as $attributo)
                    <td>{{ $carta[$attributo] }}</td>
                @endforeach
            </tr>
            @endforeach
            @endif
        </table>
    </body>
</html>
<style>
    form{
        display: inline-block;
    }
    td, th{
        text-align: center;
    }
</style>
<script>
    function refreshPage() {
        setTimeout(function() {
            location.reload();
        }, 10000);
    }

    //refreshPage();
</script>