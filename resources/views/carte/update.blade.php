@extends('layouts.app')
@section('title', 'Aggiornamento carte')
@section('content')
    <?php use App\Http\Controllers\CardsController;?>
    @if(isset($count))
        @if($count === "In elaborazione...")
            <div class="alert alert-info" id="scanningAlert">
                <h4><i class="fas fa-spinner fa-spin" id="scanSpinner"></i> <span id="scanStatus">Scansione in corso</span></h4>
                <p><strong id="scanMessage">{{ $message ?? 'Processo di scansione avviato in background' }}</strong></p>
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

            @if(isset($threadId))
                <script>
                    let checkInterval;
                    let threadId = '{{ $threadId }}';

                    function checkScanStatus() {
                        fetch(`{{ route('carte.checkScanStatus', ':threadId') }}`.replace(':threadId', threadId))
                            .then(response => response.json())
                            .then(data => {
                                // Update the message if we have a new one
                                if (data.latestMessage) {
                                    document.getElementById('scanMessage').textContent = data.latestMessage;
                                }

                                // If scan is complete, stop spinner and update UI
                                if (data.isComplete) {
                                    clearInterval(checkInterval);

                                    // Stop spinner
                                    const spinner = document.getElementById('scanSpinner');
                                    spinner.classList.remove('fa-spin');
                                    spinner.classList.remove('fa-spinner');
                                    spinner.classList.add('fa-check-circle');

                                    // Update status
                                    document.getElementById('scanStatus').textContent = 'Scansione completata';

                                    // Change alert type
                                    const alert = document.getElementById('scanningAlert');
                                    alert.classList.remove('alert-info');
                                    alert.classList.add('alert-success');

                                    // Add refresh button only if it doesn't exist
                                    if (!document.querySelector('.refresh-btn')) {
                                        const refreshBtn = document.createElement('button');
                                        refreshBtn.className = 'btn btn-primary mt-2 refresh-btn';
                                        refreshBtn.textContent = 'Aggiorna pagina per vedere i risultati';
                                        refreshBtn.onclick = () => window.location.reload();
                                        alert.appendChild(refreshBtn);
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Errore controllo stato scansione:', error);
                            });
                    }

                    // Check status every 5 seconds
                    checkInterval = setInterval(checkScanStatus, 5000);

                    // Initial check after 2 seconds
                    setTimeout(checkScanStatus, 2000);
                </script>
            @endif
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
    @if(env('APP_DEBUG') && Auth::check() && Auth::user()->admin)
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

        <div class="mt-4">
            <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#debugInfo" aria-expanded="false">
                <i class="fas fa-bug"></i> Debug Info (Admin)
            </button>
            <div class="collapse mt-2" id="debugInfo">
                <div class="card">
                    <div class="card-body">
                        @if(isset($data))
                            <?php printlnd($data, 0, "data", false) ?>
                        @else
                            <?php printlnd($output, 0, "output", false) ?>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection