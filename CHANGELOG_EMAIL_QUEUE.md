# Changelog - Sistema di Coda Email

## Panoramica delle Modifiche

È stato implementato un sistema completo di coda email con rate limiting per prevenire l'overflow del provider email (limite: 2 email/secondo).

## File Creati

### 1. Core System
- `app/Jobs/SendQueuedEmail.php` - Job per invio email con serializzazione sicura
- `app/Services/EmailQueueService.php` - Servizio principale per gestione coda email
- `app/Services/EmailLogService.php` - Servizio dedicato per logging email
- `app/Http/Middleware/EmailRateLimitMiddleware.php` - Middleware per prevenire abusi

### 2. Commands
- `app/Console/Commands/EmailQueueStatus.php` - Comando per monitorare stato coda
- `app/Console/Commands/EmailLogCleanup.php` - Comando per pulizia log vecchi
- `app/Console/Commands/TestEmailSerialization.php` - Comando per testare serializzazione

### 3. Logging System
- `storage/logs/mail/` - Cartella dedicata per log email
- `config/logging.php` - Canale di logging 'mail' aggiunto

### 4. Documentation & Tests
- `docs/EMAIL_QUEUE_SYSTEM.md` - Documentazione completa del sistema
- `tests/Feature/EmailQueueTest.php` - Test suite per il sistema di coda (inclusi test logging)
- `CHANGELOG_EMAIL_QUEUE.md` - Questo file di changelog

## File Modificati

### 1. Controllers
- `app/Http/Controllers/CardsController.php`
  - Sostituito invio sincrono con `EmailQueueService::queueToUsers()`
  - Rimosso try/catch con sleep(1) problematico

- `app/Http/Controllers/EmailVerificationController.php`
  - Sostituito `Mail::to()->send()` con `EmailQueueService::queue()`

### 2. Configuration
- `app/Providers/AppServiceProvider.php`
  - Aggiunto rate limiter per coda email (120/minuto = 2/secondo)

- `app/Http/Controllers/JobController.php`
  - Aggiunto metodo `processEmailQueue()` per elaborazione fire and forget
  - Implementato rate limiting e gestione retry

- `bootstrap/app.php`
  - Sostituito invio email admin sincrono con `EmailQueueService::queueToAdmins()`
  - Registrato middleware `email.rate.limit`

- `routes/web.php`
  - Aggiunta rotta `/job/ProcessEmailQueue` per processore
  - Applicato middleware rate limiting alle rotte di invio email:
    - `/email/resend` (3 tentativi/10 minuti)
    - `/profilo/resend-verification` (2 tentativi/30 minuti)
    - `/users/{id}/resend-verification` (5 tentativi/60 minuti)

### 3. Documentation
- `GUIDA_AVANZATA.md`
  - Aggiunta sezione "Sistema di Coda Email"
  - Aggiornato esempio di gestione errori

## Funzionalità Implementate

### 1. Rate Limiting Automatico
- ✅ Massimo 2 email al secondo
- ✅ Cache-based con chiavi temporali
- ✅ Sleep automatico quando limite raggiunto

### 2. Queue Management
- ✅ Coda dedicata 'emails'
- ✅ Serializzazione sicura Mailable (risolve errore PDO)
- ✅ Retry automatico (3 tentativi: 30s, 60s, 120s)
- ✅ Timeout configurabile (120 secondi)
- ✅ Logging dettagliato

### 3. Batch Processing
- ✅ Invio a gruppi di utenti con delay configurabile
- ✅ Gestione automatica utenti senza email
- ✅ Batching intelligente (10 utenti per batch)

### 4. Fire and Forget Integration
- ✅ Processore si avvia automaticamente quando servono email
- ✅ Integrazione nativa con JobController esistente
- ✅ Throttling intelligente (max ogni 30 secondi)
- ✅ Auto-restart se ci sono job in coda

### 5. Sistema di Logging Avanzato
- ✅ Cartella dedicata `storage/logs/mail/` con file separati per operazione
- ✅ Log dettagliati con timestamp, contesto e stack trace errori
- ✅ Pulizia automatica file vecchi (30 giorni)
- ✅ Comando per monitoraggio stato coda
- ✅ Logging su file dedicati, Laravel log e Telegram
- ✅ Metriche dettagliate in database

### 5. Abuse Prevention
- ✅ Middleware per limitare azioni che scatenano email
- ✅ Rate limiting per utente/IP
- ✅ Logging tentativi sospetti

## Comandi Disponibili

```bash
# Monitorare stato coda
php artisan email:status

# Avviare processore manualmente (se necessario)
php artisan email:status --trigger

# Gestire job falliti
php artisan email:status --clear-failed

# Pulire log vecchi
php artisan email:cleanup-logs --days=30
php artisan email:cleanup-logs --dry-run  # Simulazione

# Testare serializzazione (debug)
php artisan email:test-serialization
```

## Correzione Bug PDO Serialization

### Problema Risolto
Il sistema risolveva l'errore critico **"Serialization of 'PDO' is not allowed"** che impediva il funzionamento della coda email.

### Causa
I Mailable contenevano riferimenti al database (PDO) che non possono essere serializzati quando Laravel salva i job nella coda database.

### Soluzione Implementata
1. **Estrazione dati**: I Mailable vengono scomposti in dati serializzabili nel costruttore del job
2. **Ricostruzione**: I Mailable vengono ricreati nel metodo `handle()` usando i dati estratti
3. **Supporto completo**: Gestisce tutti i tipi di Mailable del progetto:
   - `ErrorNotificationEmail`
   - `EmailVerificationMail`
   - `NewCardsNotification`
   - `NewCardsEmail`
   - `ImportErrorsNotification`

### Metodi Chiave
- `extractMailableData()`: Estrae dati serializzabili dal Mailable
- `recreateMailable()`: Ricrea il Mailable dai dati estratti
- Fallback sicuro per Mailable non supportati

## Integrazione Fire and Forget

Il sistema utilizza l'architettura esistente del progetto:

- **Rotta**: `/job/ProcessEmailQueue` (protetta da JOB_TOKEN)
- **Trigger**: Automatico quando si accodano email
- **Throttling**: Max ogni 30 secondi per evitare sovrapposizioni
- **Auto-restart**: Si riavvia se ci sono job in coda dopo elaborazione

## API del Servizio

```php
use App\Services\EmailQueueService;

// Singola email
EmailQueueService::queue($mailable, $email, 'Contesto');

// Multipli utenti
EmailQueueService::queueToUsers($mailable, $users, 'Contesto', $delay);

// Tutti gli admin
EmailQueueService::queueToAdmins($mailable, 'Contesto');

// Invio immediato (emergenze)
EmailQueueService::sendImmediate($mailable, $email, 'Contesto');
```

## Benefici

1. **Prevenzione Errori**: Elimina errori da overflow email provider
2. **Scalabilità**: Gestisce picchi di traffico senza perdere email
3. **Affidabilità**: Retry automatico e gestione fallimenti
4. **Monitoraggio**: Visibilità completa su stato e performance
5. **Sicurezza**: Prevenzione abusi con rate limiting
6. **Manutenibilità**: Codice pulito e ben documentato

## Deployment

1. ✅ Tabelle `jobs` e `failed_jobs` già esistenti
2. ✅ Configurazione code già attiva (`QUEUE_CONNECTION=database`)
3. ✅ Tutti i file creati e modificati
4. ✅ Test suite implementata

### Vantaggi Fire and Forget

1. **Zero configurazione**: Nessun worker da mantenere attivo
2. **Auto-scaling**: Si avvia solo quando necessario
3. **Fault tolerance**: Restart automatico in caso di problemi
4. **Integrazione nativa**: Usa JobController esistente
5. **Monitoraggio**: `php artisan email:status`

## Compatibilità

- ✅ Mantiene compatibilità con codice esistente
- ✅ Fallback per email critiche
- ✅ Logging esistente preservato
- ✅ Configurazione provider email invariata

Il sistema è pronto per il deployment e risolve completamente il problema dell'overflow email.
