# Sistema di Messaggi in Thread (Threaded Messaging System)

## Panoramica

Il sistema di messaggi in thread è stato implementato per evitare lo spam di notifiche durante processi lunghi come l'importazione delle carte. Invece di ricevere una notifica per ogni singolo step, i messaggi successivi sostituiscono quelli precedenti all'interno dello stesso contesto di esecuzione.

## Come Funziona

### Concetti Chiave

1. **Thread ID**: Ogni processo ottiene un identificatore univoco che raggruppa tutti i messaggi correlati
2. **Sostituzione Messaggi**: I messaggi successivi nello stesso thread sostituiscono quelli precedenti
3. **Completamento Thread**: I thread possono essere marcati come completati per la pulizia automatica

### Componenti

#### Eventi
- `ThreadMessageCreated`: Evento per messaggi in thread che sostituiscono quelli precedenti
- `MessageCreated`: Evento originale per messaggi singoli (mantenuto per compatibilità)

#### Servizi
- `ThreadManager`: Gestisce lo stato dei thread attivi e la pulizia di quelli completati

#### Listener
- `SendThreadMessage`: Gestisce l'invio di messaggi in thread via Telegram

## Utilizzo

### Esempio Base

```php
use App\Events\ThreadMessageCreated;
use App\Services\ThreadManager;

// Genera un ID thread univoco
$threadId = ThreadManager::generateThreadId('import');

// Invia il primo messaggio
ThreadMessageCreated::dispatch($threadId, "Avvio processo...");

// I messaggi successivi sostituiranno il precedente
ThreadMessageCreated::dispatch($threadId, "Elaborazione al 50%...");

// Marca il thread come completato
ThreadMessageCreated::dispatch($threadId, "Processo completato!", true);
```

### Esempio nel CardsController

Il sistema è già integrato nel processo di importazione delle carte:

```php
// In startImport()
$threadId = ThreadManager::generateThreadId('import');
ThreadMessageCreated::dispatch($threadId, "Avvio importazione di " . count($toInsert) . " nuove carte");

// In sendBatch()
ThreadMessageCreated::dispatch($threadId, "Elaborazione batch $next di " . ceil(count($data) / 5));

// Al completamento
ThreadMessageCreated::dispatch($threadId, "Importazione completata! Elaborate " . count($data) . " carte", true);
```

## API ThreadManager

### Metodi Principali

```php
// Genera un nuovo thread ID
$threadId = ThreadManager::generateThreadId('prefisso');

// Aggiorna un thread
ThreadManager::updateThread($threadId, $message, $isComplete);

// Ottieni informazioni su un thread
$threadData = ThreadManager::getThread($threadId);

// Verifica se un thread è attivo
$isActive = ThreadManager::isThreadActive($threadId);

// Marca un thread come completato
ThreadManager::completeThread($threadId, $finalMessage);

// Pulizia thread completati
ThreadManager::cleanupCompletedThreads();

// Statistiche per debug
$stats = ThreadManager::getThreadStats();
```

## Test

### Route di Test

Visita `/thread-message-test` per vedere una dimostrazione del sistema:

```php
Route::get("/thread-message-test", function(){
    $threadId = \App\Services\ThreadManager::generateThreadId('test');
    
    \App\Events\ThreadMessageCreated::dispatch($threadId, "Avvio processo di test...");
    sleep(1);
    \App\Events\ThreadMessageCreated::dispatch($threadId, "Elaborazione al 25%...");
    sleep(1);
    \App\Events\ThreadMessageCreated::dispatch($threadId, "Elaborazione al 50%...");
    sleep(1);
    \App\Events\ThreadMessageCreated::dispatch($threadId, "Elaborazione al 75%...");
    sleep(1);
    \App\Events\ThreadMessageCreated::dispatch($threadId, "Processo completato!", true);
    
    return "Test completato. Controlla Telegram per i messaggi.";
});
```

## Configurazione

### Event Service Provider

Il sistema è registrato in `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    ThreadMessageCreated::class => [
        SendThreadMessage::class,
    ],
];
```

### Route

La route per l'endpoint Telegram è registrata in `routes/web.php`:

```php
Route::get("/job/SendThreadMessage", [JobController::class, 'sendThreadMessage'])->name("job.sendThreadMessage");
```

## Vantaggi

1. **Riduzione Spam**: Evita notifiche multiple durante processi lunghi
2. **Contesto Chiaro**: I messaggi sono raggruppati per processo
3. **Gestione Memoria**: Pulizia automatica dei thread completati
4. **Compatibilità**: Il sistema originale rimane intatto
5. **Flessibilità**: Può essere usato per qualsiasi processo lungo

## Limitazioni Attuali

1. **Telegram**: Attualmente invia ancora messaggi separati (potrebbe essere migliorato con editMessageText)
2. **Persistenza**: I thread sono memorizzati in memoria (per processi più lunghi potrebbe servire un database)
3. **Distribuzione**: Non funziona attraverso più server (per ora è single-instance)

## Possibili Miglioramenti Futuri

1. **Edit Message**: Usare l'API Telegram editMessageText per sostituire realmente i messaggi
2. **Database Storage**: Memorizzare i thread in database per persistenza
3. **WebSocket**: Aggiungere notifiche real-time nel browser
4. **Progress Bar**: Interfaccia grafica per mostrare il progresso
