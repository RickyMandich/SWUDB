@extends('layouts.app')
@section('content')
<?php function printlnd($line, $deep = 0, $name, $link = false){
    if(gettype($line) == 'array' || gettype($line) == 'object'){
        echo "$name(".count($line).")-->{<br>";
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
// var_dump($result);
// printlnd($result, 0, "result");
?>
<style>
    .content {
        flex-grow: 1; /* Occupa tutto lo spazio disponibile */
        overflow: auto; /* Abilita lo scorrimento */
        max-width: 100%; /* Usa tutta la larghezza disponibile */
        height: 86.1vh; /* Altezza massima pari all'altezza dello schermo */
        display: flex;
        flex-direction: column;
    }
    
    .table thead th {
        position: sticky;
        top: 0;
        background-color: #f8f9fa; /* Sfondo per l'intestazione */
        z-index: 1;
    }
</style>
<div class="content table-container">
    <table class="table table-hover table-striped table-bordered">
        <thead class="table-light sticky-top">
            <tr>
                @foreach ($result[0] as $key=> $value)
                <th>{{ $key }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($result as $row)
            <tr>
                @foreach ($row as $value)
                <td>{{ $value }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection