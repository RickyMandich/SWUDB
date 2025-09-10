@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark text-white">
                <div class="card-header">
                    <h4>Test Sistema Telegram Thread Messaging</h4>
                </div>
                <div class="card-body">
                    <p>Questo è un test per verificare il funzionamento del sistema di thread messaging per Telegram.</p>
                    
                    <div class="mb-3">
                        <h5>Sistema Attuale:</h5>
                        <ul>
                            <li><strong>TelegramController</strong>: Gestisce i comandi Telegram e utilizza thread messaging</li>
                            <li><strong>JobController</strong>: Gestisce l'invio effettivo dei messaggi con modifica</li>
                            <li><strong>ThreadManager</strong>: Gestisce lo stato dei thread e gli ID dei messaggi</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <h5>Flusso del Comando /scan:</h5>
                        <ol>
                            <li>Utente invia <code>/scan</code> su Telegram</li>
                            <li>TelegramController riceve il comando</li>
                            <li>Genera un thread ID univoco</li>
                            <li>Invia messaggio iniziale usando thread messaging</li>
                            <li>Avvia CardsController.startImport() con il thread ID</li>
                            <li>CardsController usa lo stesso thread ID per tutti i messaggi di progresso</li>
                            <li>I messaggi vengono modificati invece di crearne di nuovi</li>
                        </ol>
                    </div>

                    <div class="mb-3">
                        <h5>Test Manuale:</h5>
                        <div class="alert alert-info">
                            <strong>Per testare il sistema:</strong><br>
                            1. Configura il webhook Telegram: <a href="{{ url('/api/telegram/setup-webhook') }}" target="_blank" class="text-white">Setup Webhook</a><br>
                            2. Invia il comando <code>/scan</code> al bot Telegram<br>
                            3. Osserva che il messaggio viene modificato invece di crearne di nuovi
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5>Test Simulazione Thread:</h5>
                        <button id="testThread" class="btn btn-primary">Simula Thread Messaging</button>
                        <div id="threadResult" class="mt-3"></div>
                    </div>

                    <div class="mb-3">
                        <h5>Configurazione Telegram:</h5>
                        <ul>
                            <li><strong>Bot Token:</strong> {{ config('telegram.bot_token') ? 'Configurato' : 'Non configurato' }}</li>
                            <li><strong>Webhook URL:</strong> {{ config('telegram.webhook_url') ?? 'Non configurato' }}</li>
                            <li><strong>Chat ID:</strong> {{ env('TELEGRAM_CHAT_ID') ?? 'Non configurato' }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('testThread').addEventListener('click', function() {
    const resultDiv = document.getElementById('threadResult');
    resultDiv.innerHTML = '<div class="alert alert-info">Avvio test thread messaging...</div>';
    
    // Simula una serie di messaggi di progresso
    const messages = [
        'Avvio scansione API...',
        'Recupero lista carte dall\'API...',
        'Scansione API completata: 1250 carte trovate',
        'Trovate 15 nuove carte da elaborare',
        'Elaborate 5/15 carte',
        'Elaborate 10/15 carte',
        'Elaborate 15/15 carte',
        'Completata elaborazione: 15 carte pronte per importazione',
        'Inizio inserimento di 15 carte nel database',
        '✅ Scansione completata! Avviato inserimento di 15 carte'
    ];
    
    let currentMessage = 0;
    const threadId = 'test_' + Date.now();
    
    function sendNextMessage() {
        if (currentMessage < messages.length) {
            const message = messages[currentMessage];
            const isComplete = currentMessage === messages.length - 1;
            
            // Simula l'invio del messaggio usando GET con parametri
            const params = new URLSearchParams({
                threadId: threadId,
                message: message,
                isComplete: isComplete ? 1 : 0,
                token: '{{ env("JOB_TOKEN") }}'
            });

            fetch('{{ route("job.sendThreadMessage") }}?' + params.toString(), {
                method: 'GET'
            }).then(response => {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>Messaggio ${currentMessage + 1}/${messages.length}:</strong><br>
                        ${message}
                        ${isComplete ? '<br><em>Thread completato!</em>' : ''}
                    </div>
                `;
                
                currentMessage++;
                if (!isComplete) {
                    setTimeout(sendNextMessage, 2000); // Attendi 2 secondi tra i messaggi
                }
            }).catch(error => {
                resultDiv.innerHTML = `<div class="alert alert-danger">Errore: ${error.message}</div>`;
            });
        }
    }
    
    sendNextMessage();
});
</script>
@endsection
