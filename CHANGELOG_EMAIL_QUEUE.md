# Changelog - Sistema di Coda Email

## Panoramica delle Modifiche

È stato implementato un sistema completo di coda email con rate limiting per prevenire l'overflow del provider email (limite: 2 email/secondo).

## File Creati

### 1. Core System
- `app/Jobs/SendQueuedEmail.php` - Job per invio email con rate limiting
- `app/Services/EmailQueueService.php` - Servizio principale per gestione coda email
- `app/Http/Middleware/EmailRateLimitMiddleware.php` - Middleware per prevenire abusi

### 2. Commands
- `app/Console/Commands/ProcessEmailQueue.php` - Comando per processare la coda
- `app/Console/Commands/EmailQueueStatus.php` - Comando per monitorare stato coda
- `app/Console/Commands/TestEmailQueue.php` - Comando per testare il sistema

### 3. Documentation & Tests
- `docs/EMAIL_QUEUE_SYSTEM.md` - Documentazione completa del sistema
- `tests/Feature/EmailQueueTest.php` - Test suite per il sistema di coda
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

- `bootstrap/app.php`
  - Sostituito invio email admin sincrono con `EmailQueueService::queueToAdmins()`
  - Registrato middleware `email.rate.limit`

- `routes/web.php`
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
- ✅ Retry automatico (3 tentativi: 30s, 60s, 120s)
- ✅ Timeout configurabile (120 secondi)
- ✅ Logging dettagliato

### 3. Batch Processing
- ✅ Invio a gruppi di utenti con delay configurabile
- ✅ Gestione automatica utenti senza email
- ✅ Batching intelligente (10 utenti per batch)

### 4. Monitoring & Debugging
- ✅ Comandi per stato coda e gestione job falliti
- ✅ Logging su file e Telegram
- ✅ Metriche dettagliate in database

### 5. Abuse Prevention
- ✅ Middleware per limitare azioni che scatenano email
- ✅ Rate limiting per utente/IP
- ✅ Logging tentativi sospetti

## Comandi Disponibili

```bash
# Processare la coda
php artisan email:process-queue --daemon

# Monitorare stato
php artisan email:queue-status

# Gestire job falliti
php artisan email:queue-status --retry-failed
php artisan email:queue-status --clear-failed

# Testare il sistema
php artisan email:test-queue --count=10 --to=test@example.com
php artisan email:test-queue --dry-run
```

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

### Prossimi Passi per Produzione

1. Avviare worker coda: `php artisan email:process-queue --daemon`
2. Configurare supervisor/systemd per mantenere worker attivo
3. Monitorare con `php artisan email:queue-status`
4. Testare con `php artisan email:test-queue --dry-run`

## Compatibilità

- ✅ Mantiene compatibilità con codice esistente
- ✅ Fallback per email critiche
- ✅ Logging esistente preservato
- ✅ Configurazione provider email invariata

Il sistema è pronto per il deployment e risolve completamente il problema dell'overflow email.
