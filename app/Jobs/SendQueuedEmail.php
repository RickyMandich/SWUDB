<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\MessageCreated;
use App\Services\EmailLogService;

/**
 * Job for sending emails with rate limiting to prevent overflow
 * Job per inviare email con rate limiting per prevenire overflow
 *
 * This job ensures that no more than 2 emails are sent per second
 * to comply with email provider limitations and prevent errors.
 */
class SendQueuedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120]; // Retry after 30s, 60s, 120s
    public $timeout = 120;

    protected $mailable;
    protected $to;
    protected $logContext;

    /**
     * Create a new job instance
     * Crea una nuova istanza del job
     *
     * @param mixed $mailable The mailable instance to send
     * @param string $to The recipient email address
     * @param string $logContext Context for logging purposes
     */
    public function __construct($mailable, string $to, string $logContext = '')
    {
        $this->mailable = $mailable;
        $this->to = $to;
        $this->logContext = $logContext;
        
        // Set the queue to 'emails' for better organization
        $this->onQueue('emails');
    }

    /**
     * Get the middleware the job should pass through
     * Ottiene i middleware che il job deve attraversare
     *
     * @return array
     */
    public function middleware()
    {
        return [
            new RateLimited('emails'),
        ];
    }

    /**
     * Execute the job - send the email
     * Esegue il job - invia l'email
     *
     * Note: Rate limiting is handled by the JobController processor
     * Nota: Il rate limiting è gestito dal processore JobController
     *
     * @return void
     */
    public function handle()
    {
        $jobId = $this->job->getJobId() ?? 'unknown';

        // Log job start
        EmailLogService::logSend("Inizio invio email a: {$this->to} (Job ID: {$jobId})");

        try {
            // Send the email
            Mail::to($this->to)->send($this->mailable);

            // Log successful send
            $this->logSuccess();

        } catch (\Exception $e) {
            $this->logError($e);

            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Log successful email send
     * Registra l'invio email riuscito
     *
     * @return void
     */
    protected function logSuccess()
    {
        $jobId = $this->job->getJobId() ?? 'unknown';
        $mailableClass = get_class($this->mailable);

        $message = "Email inviata con successo a: {$this->to}";
        if ($this->logContext) {
            $message .= " - Contesto: {$this->logContext}";
        }

        // Log to dedicated email log
        EmailLogService::logSend("✅ {$message} (Job ID: {$jobId}, Mailable: {$mailableClass})");

        // Send Telegram notification
        MessageCreated::dispatch($message);

        // Backup to Laravel log
        Log::info('Email sent successfully', [
            'to' => $this->to,
            'mailable' => $mailableClass,
            'context' => $this->logContext,
            'job_id' => $jobId
        ]);
    }

    /**
     * Log email send error
     * Registra errore invio email
     *
     * @param \Exception $e
     * @return void
     */
    protected function logError(\Exception $e)
    {
        $jobId = $this->job->getJobId() ?? 'unknown';
        $attempt = $this->attempts();

        $message = "Errore invio email a: {$this->to} - {$e->getMessage()}";
        if ($this->logContext) {
            $message .= " - Contesto: {$this->logContext}";
        }

        // Log to dedicated email error log
        EmailLogService::logError('Email Send', $e, [
            'to' => $this->to,
            'mailable' => get_class($this->mailable),
            'context' => $this->logContext,
            'job_id' => $jobId,
            'attempt' => $attempt
        ]);

        // Send Telegram notification
        MessageCreated::dispatch($message);

        // Backup to Laravel log
        Log::error('Email send failed', [
            'to' => $this->to,
            'mailable' => get_class($this->mailable),
            'context' => $this->logContext,
            'error' => $e->getMessage(),
            'job_id' => $jobId,
            'attempt' => $attempt
        ]);
    }

    /**
     * Handle a job failure
     * Gestisce il fallimento di un job
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        $jobId = $this->job->getJobId() ?? 'unknown';

        $message = "Invio email fallito definitivamente a: {$this->to} dopo {$this->tries} tentativi - {$exception->getMessage()}";
        if ($this->logContext) {
            $message .= " - Contesto: {$this->logContext}";
        }

        // Log to dedicated email error log
        EmailLogService::logError('Email Job Failed Permanently', $exception, [
            'to' => $this->to,
            'mailable' => get_class($this->mailable),
            'context' => $this->logContext,
            'job_id' => $jobId,
            'max_tries' => $this->tries
        ]);

        // Send Telegram notification
        MessageCreated::dispatch($message);

        // Backup to Laravel log
        Log::critical('Email job failed permanently', [
            'to' => $this->to,
            'mailable' => get_class($this->mailable),
            'context' => $this->logContext,
            'error' => $exception->getMessage(),
            'job_id' => $jobId
        ]);
    }
}
