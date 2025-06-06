@extends('layouts.app')
@section('title', 'Aggiornamento carte')
@section('content')
    <?php use App\Http\Controllers\CardsController;?>
    aggiornamento eseguito
    <br>
    {{ $count }} carte aggiunte
    <br>
    <?php function printlnd($line, $deep = 0, $name, $link = false){
        if(gettype($line) == 'array' || gettype($line) == 'object'){
            if(array_key_exists("cid", $line)){
                ?>
                <a href="{{ route('carta', ['espansione' => $line["espansione"], 'numero' => $line["numero"]]) }}">{{$name}}</a>-->{<br>
                <?php
            }else{
                echo "$name-->{<br>";
            }
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
    }?>
    <?php printlnd($data, 0, "data", false) ?>
@endsection