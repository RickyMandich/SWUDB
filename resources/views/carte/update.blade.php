@extends('layouts.app')
@section('title', 'Aggiornamento carte')
@section('content')
    <?php use App\Http\Controllers\CardsController;?>
    @if(isset($count))
        @if($count === "In elaborazione...")
            <div class="alert alert-info">
                <h4><i class="fas fa-spinner fa-spin"></i> Scansione in corso</h4>
                <p><strong>{{ $message ?? 'Processo di scansione avviato in background' }}</strong></p>
                @if(isset($apiUsed) && $apiUsed)
                    <p>
                        <strong>Metodo:</strong>
                        <span class="badge bg-primary">API Star Wars Unlimited</span>
                    </p>
                @endif
                <p class="mb-0">
                    <small class="text-muted">
                        Riceverai notifiche sui progressi tramite Telegram.
                        Il processo continuerà in background anche se chiudi questa pagina.
                    </small>
                </p>
            </div>
        @else
            <div class="alert alert-success">
                <h4>Aggiornamento completato</h4>
                <p><strong>{{ $count }}</strong> carte aggiunte</p>
                @if(isset($apiUsed))
                    <p>
                        <strong>Metodo utilizzato:</strong>
                        @if($apiUsed)
                            <span class="badge bg-primary">API Star Wars Unlimited</span>
                        @else
                            <span class="badge bg-secondary">File JSON (fallback)</span>
                        @endif
                    </p>
                @endif
            </div>
        @endif
    @endif
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
    @if(isset($data))
        <?php printlnd($data, 0, "data", false) ?>
    @else
        <?php printlnd($output, 0, "output", false) ?>
    @endif
@endsection