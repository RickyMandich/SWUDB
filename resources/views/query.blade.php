<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Query</title>
</head>
<body>
    <?php function printlnd($line, $deep = 0, $name, $link = false){
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
                echo "$name=>$line";
            }catch(Error $e){
                echo "Errore: " . $e->getMessage();
                echo gettype($line);
            }
            echo "<br>";
        }
    }
    ?>
    <form action="query">
        <label for="query">
            inserisci la query
        </label>
        <input type="text" name="query" id="inputQuery" value="{{ $query }}">
    </form>
    <?php $header = [];
    $j=0;?>
    @foreach ($result as $row)
    <?php $i=0;?>
        @foreach ($row as $key=>$column)
            @foreach ($result as $line)
                @if (!isset($line->$key))
                    <?php $line->$key = "";?>
                @endif
            @endforeach
            @if(!isset($header[$key]))
                <?php $header[$key] = "";?>
            @endif
        @endforeach
        <?php $i++;?>
    @endforeach
    <table border="">
        <tr>
            @foreach ($header as $key=>$value)
                <th>
                    {{ $key }}
                </th>
            @endforeach
        </tr>
        @foreach ($result as $row)
            <tr>
                @foreach ($row as $column)
                    <td>
                        {{ $column }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</body>
</html>
<style>
    input{
        display: block;
        width: 100%;
    }
</style>