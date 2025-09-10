# Upgrade Sistema Telegram Thread Messaging

## Obiettivo
Convertire il TelegramController per utilizzare un funzionamento analogo al JobController per l'invio di messaggi Telegram, implementando il sistema di thread messaging che modifica lo stesso messaggio invece di inviarne di nuovi durante le scansioni.

## Modifiche Effettuate

### 1. TelegramController - Aggiornamenti Principali

#### Nuove Dipendenze
- Aggiunto `use Illuminate\Support\Facades\Http;`
- Aggiunto `use App\Services\ThreadManager;`

#### Nuove Proprietà
- Aggiunta proprietà `$currentThreadId` per tracciare il thread attivo

#### Nuovo Metodo: `sendThreadMessage()`
```php
private function sendThreadMessage($chatId, $message, $isComplete = false)
```
- Implementa la logica di thread messaging analoga al JobController
- Controlla se esiste un messaggio esistente da modificare
- Se esiste, modifica il messaggio esistente usando `editMessageText`
- Se non esiste, invia un nuovo messaggio e memorizza l'ID
- Utilizza ThreadManager per gestire lo stato del thread
- Fallback al messaggio normale in caso di errore

#### Modifiche al Metodo `executeScanCommand()`
- Genera un thread ID univoco usando `ThreadManager::generateThreadId('scan')`
- Utilizza `sendThreadMessage()` invece di `sendMessage()` per i messaggi di progresso
- Rimuove il messaggio finale di completamento (gestito dal CardsController)
- Migliora la gestione degli errori con fallback

#### Modifiche al Metodo `triggerUpdate()`
- Passa il thread ID al `CardsController::startImport()`
- Aggiorna il messaggio di progresso usando thread messaging
- Rimuove la variabile `$result` non utilizzata

### 2. CardsController - Aggiornamenti

#### Metodo `startImport()`
- Aggiunto parametro opzionale `$externalThreadId = null`
- Utilizza il thread ID esterno se fornito, altrimenti ne genera uno nuovo
- Mantiene compatibilità con chiamate esistenti

### 3. Sistema di Thread Messaging

#### Flusso Completo
1. **TelegramController** riceve comando `/scan`
2. Genera thread ID univoco
3. Invia messaggio iniziale usando thread messaging
4. Passa thread ID a CardsController
5. **CardsController** utilizza lo stesso thread ID per tutti i messaggi di progresso
6. **JobController** gestisce l'invio effettivo con modifica dei messaggi
7. **ThreadManager** mantiene lo stato e gli ID dei messaggi Telegram

#### Vantaggi
- **Nessun spam**: Un solo messaggio viene modificato invece di crearne di nuovi
- **Progresso in tempo reale**: L'utente vede l'aggiornamento del progresso
- **Consistenza**: Stesso sistema per tutti i processi di scansione
- **Fallback robusto**: Se il thread messaging fallisce, utilizza messaggi normali

### 4. Test e Verifica

#### Pagina di Test
- Creata `resources/views/test/telegram.blade.php`
- Route: `/test/telegram` (richiede autenticazione)
- Simula il flusso completo di thread messaging
- Mostra configurazione Telegram
- Test manuale del sistema

#### Configurazione Richiesta
- `TELEGRAM_BOT_TOKEN`: Token del bot Telegram
- `TELEGRAM_CHAT_ID`: ID della chat per le notifiche
- `TELEGRAM_WEBHOOK_URL`: URL per il webhook
- `JOB_TOKEN`: Token per le chiamate ai job

## Come Testare

### 1. Test Automatico
1. Accedi al sito come utente autenticato
2. Vai su `/test/telegram`
3. Clicca "Simula Thread Messaging"
4. Osserva la simulazione del flusso di messaggi

### 2. Test Reale con Telegram
1. Configura il webhook: `/api/telegram/setup-webhook`
2. Invia `/scan` al bot Telegram
3. Osserva che il messaggio viene modificato invece di crearne di nuovi

## Struttura del Sistema

```
TelegramController (riceve comandi)
    ↓ (genera thread ID)
    ↓ (sendThreadMessage)
JobController::sendThreadMessage (invio effettivo)
    ↓ (utilizza ThreadManager)
ThreadManager (gestisce stato e ID messaggi)
    ↓ (coordina con)
CardsController (processo di scansione)
    ↓ (utilizza stesso thread ID)
ThreadMessageCreated Event → SendThreadMessage Listener
    ↓ (chiama)
JobController::sendThreadMessage (modifica stesso messaggio)
```

## Compatibilità

- **Backward Compatible**: Il sistema esistente continua a funzionare
- **Graduale**: I nuovi processi utilizzano thread messaging, quelli esistenti funzionano normalmente
- **Fallback**: Se il thread messaging fallisce, utilizza il sistema normale

## File Modificati

1. `app/Http/Controllers/TelegramController.php` - Aggiunto thread messaging
2. `app/Http/Controllers/CardsController.php` - Supporto thread ID esterno
3. `app/Listeners/SendThreadMessage.php` - Correzione formattazione messaggi scan
4. `routes/web.php` - Aggiunta route di test
5. `resources/views/test/telegram.blade.php` - Pagina di test (nuovo file)

## Correzioni Applicate

### Problema: Messaggi Duplicati
**Causa**: Il listener `SendThreadMessage` aggiungeva automaticamente prefissi come "✅ Scan:" ai messaggi.

**Soluzione**: Modificato il listener per preservare la formattazione originale per i thread `scan_*` provenienti dal TelegramController.

### Problema: Messaggio Iniziale Duplicato
**Causa**: Sia TelegramController che CardsController.scanAPI inviavano messaggi iniziali.

**Soluzione**: CardsController.scanAPI ora controlla se il thread esiste già e non invia messaggi duplicati.

## Note Tecniche

- Il sistema utilizza le API di Telegram per `editMessageText`
- ThreadManager utilizza Laravel Cache per persistenza
- Timeout e gestione errori robusti
- Debug logging per troubleshooting
- Compatibile con il sistema esistente di JobController
