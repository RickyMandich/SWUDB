# Sistema di Coda Email - UnlimitedDB

## Panoramica

Il sistema di coda email è stato implementato per gestire l'invio delle email con rate limiting automatico, prevenendo l'overflow del provider email e garantendo la consegna affidabile dei messaggi.

## Problema Risolto

Il provider email (Resend) ha un limite di **2 email al secondo**. Superare questo limite causa errori e blocchi nell'invio. Il nuovo sistema:

- ✅ Limita automaticamente l'invio a 2 email/secondo
- ✅ Gestisce code separate per diversi tipi di email
- ✅ Implementa retry automatico per email fallite
- ✅ Fornisce logging dettagliato e monitoraggio
- ✅ Previene perdita di email durante picchi di traffico

## Componenti del Sistema

### 1. EmailQueueService (`app/Services/EmailQueueService.php`)

Servizio principale per accodare email:

```php
// Invia singola email
EmailQueueService::queue($mailable, $email, 'Contesto opzionale');

// Invia a più utenti con batching automatico
EmailQueueService::queueToUsers($mailable, $users, 'Contesto', 5);

// Invia a tutti gli admin
EmailQueueService::queueToAdmins($mailable, 'Contesto');

// Invio immediato (solo per emergenze)
EmailQueueService::sendImmediate($mailable, $email, 'Contesto');
```

### 2. SendQueuedEmail Job (`app/Jobs/SendQueuedEmail.php`)

Job che gestisce l'invio effettivo con:
- Rate limiting (2 email/secondo)
- Retry automatico (3 tentativi)
- Logging dettagliato
- Gestione errori

### 3. Processore Fire and Forget

Il sistema utilizza il meccanismo fire and forget esistente tramite JobController:

- **Avvio Automatico**: Il processore si avvia automaticamente quando vengono accodate email
- **Gestione Asincrona**: Utilizza `JobController::fireAndForgetGet()` per elaborazione non bloccante
- **Rate Limiting**: Integrato nel processore (2 email/secondo)
- **Auto-restart**: Si riavvia automaticamente se ci sono job in coda

#### Monitorare lo Stato
```bash
# Controlla stato della coda
php artisan email:status

# Avvia manualmente il processore
php artisan email:status --trigger

# Cancella job falliti
php artisan email:status --clear-failed
```

## Configurazione

### Rate Limiter (AppServiceProvider)

```php
RateLimiter::for('emails', function ($job) {
    return Limit::perMinute(120); // 2 al secondo = 120 al minuto
});
```

### Variabili Ambiente

```env
QUEUE_CONNECTION=database
DB_QUEUE_TABLE=jobs
DB_QUEUE_RETRY_AFTER=90
```

## Migrazione dal Sistema Precedente

### Prima (Problematico)
```php
// Invio sincrono con possibili errori
foreach($users as $user) {
    try {
        Mail::to($user->email)->send(new NewCardsEmail($data));
    } catch(\Error $e) {
        sleep(1); // Soluzione temporanea
        Mail::to($user->email)->send(new NewCardsEmail($data));
    }
}
```

### Dopo (Ottimizzato)
```php
// Invio asincrono con rate limiting automatico
EmailQueueService::queueToUsers(
    new NewCardsEmail($data), 
    $users, 
    'Notifica nuove carte',
    5 // delay tra batch
);
```

## Punti di Utilizzo Aggiornati

1. **CardsController** - Notifiche nuove carte
2. **EmailVerificationController** - Email di verifica
3. **bootstrap/app.php** - Notifiche errori admin
4. **GUIDA_AVANZATA.md** - Documentazione aggiornata

## Monitoraggio e Debugging

### Log Files
- **Laravel Log**: `storage/logs/laravel.log`
- **Telegram**: Messaggi automatici via MessageCreated
- **Database**: Tabelle `jobs` e `failed_jobs`

### Metriche Chiave
- Pending jobs: `SELECT COUNT(*) FROM jobs WHERE queue = 'emails'`
- Failed jobs: `SELECT COUNT(*) FROM failed_jobs WHERE queue = 'emails'`
- Rate limit: Cache key `email_rate_limit:YYYY-MM-DD HH:mm:ss`

## Gestione Errori

### Retry Logic
- **3 tentativi** con backoff: 30s, 60s, 120s
- **Timeout**: 120 secondi per job
- **Failed jobs**: Salvati in database per analisi

### Fallback
- Email critiche possono usare `sendImmediate()`
- Notifiche Telegram per tutti gli errori
- Log dettagliati per debugging

## Comandi Disponibili

```bash
# Monitorare stato coda
php artisan email:status

# Avviare manualmente il processore
php artisan email:status --trigger

# Gestire job falliti
php artisan email:status --clear-failed
```

## Integrazione Fire and Forget

Il sistema si integra perfettamente con l'architettura esistente:

- **Rotta**: `/job/ProcessEmailQueue` (protetta da token)
- **Trigger**: Automatico quando si accodano email
- **Throttling**: Processore si avvia max ogni 30 secondi
- **Restart**: Automatico se ci sono job in coda dopo elaborazione

## Best Practices

1. **Usa sempre EmailQueueService** invece di Mail::to()->send()
2. **Fornisci contesto** per il logging
3. **Monitora regolarmente** con `email:status`
4. **Il processore si avvia automaticamente** - non serve gestione manuale
5. **Gestisci failed jobs** periodicamente

## Deployment

Per il deployment in produzione:

1. ✅ Assicurati che le tabelle `jobs` e `failed_jobs` esistano
2. ✅ Il sistema si avvia automaticamente quando servono email
3. ✅ Nessuna configurazione aggiuntiva richiesta
4. ✅ Monitora con `php artisan email:status`

### Vantaggi Fire and Forget

1. **Zero configurazione**: Nessun worker da mantenere attivo
2. **Auto-scaling**: Si avvia solo quando necessario
3. **Fault tolerance**: Restart automatico in caso di problemi
4. **Integrazione nativa**: Usa l'architettura esistente del progetto

## Troubleshooting

### Email non inviate
```bash
# Controlla coda
php artisan email:status

# Avvia processore manualmente
php artisan email:status --trigger
```

### Rate limit raggiunto
- Il sistema gestisce automaticamente
- Controlla con `email:queue-status`
- Le email vengono ritardate, non perse

### Job falliti
```bash
# Vedi dettagli
php artisan email:status

# Cancella job falliti
php artisan email:status --clear-failed
```

Questo sistema garantisce invio affidabile e scalabile delle email rispettando i limiti del provider.
