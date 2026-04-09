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

    public $tries = 2; // Reduced to prevent duplicates
    public $backoff = [60]; // Single retry after 60s
    public $timeout = 120;

    protected $mailableClass;
    protected $mailableData;
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
        // Store mailable class and serializable data instead of the object
        $this->mailableClass = get_class($mailable);
        $this->mailableData = $this->extractMailableData($mailable);
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
        $jobId = $this->job ? $this->job->getJobId() : 'fire-and-forget';

        // Log job start
        EmailLogService::logSend("Inizio invio email a: {$this->to} (Job ID: {$jobId})");

        try {
            // Recreate the mailable from stored data
            $mailable = $this->recreateMailable();

            // Send the email
            Mail::to($this->to)->send($mailable);

            // Log successful send
            $this->logSuccess();

        } catch (\Exception $e) {
            $this->logError($e);

            // Check if it's a rate limiting error - don't retry these
            if (str_contains($e->getMessage(), 'Too many requests') ||
                str_contains($e->getMessage(), 'rate limit')) {

                EmailLogService::logError('Rate Limit Hit', $e, [
                    'to' => $this->to,
                    'context' => $this->logContext,
                    'action' => 'Adding aggressive delay and retrying'
                ]);

                // Add aggressive delay when rate limit is hit
                sleep(5);

                // Set a cache flag to slow down the entire system temporarily
                \Cache::put('email_rate_limit_hit', true, 60); // 1 minute cooldown

                // Don't retry rate limit errors - just log and delete to prevent queue buildup
                return;
            }

            // Re-throw other exceptions to trigger retry mechanism
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
        $jobId = $this->job ? $this->job->getJobId() : 'fire-and-forget';

        $message = "Email inviata con successo a: {$this->to}";
        if ($this->logContext) {
            $message .= " - Contesto: {$this->logContext}";
        }

        // Log to dedicated email log
        EmailLogService::logSend("✅ {$message} (Job ID: {$jobId}, Mailable: {$this->mailableClass})");

        // Send Telegram notification
        MessageCreated::dispatch($message);

        // Backup to Laravel log
        Log::info('Email sent successfully', [
            'to' => $this->to,
            'mailable' => $this->mailableClass,
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
        $jobId = $this->job ? $this->job->getJobId() : 'fire-and-forget';
        $attempt = $this->job ? $this->attempts() : 1;
        
        $errorMessage = is_array($e->getMessage()) ? json_encode($e->getMessage()) : (string) $e->getMessage();
        $message = "Errore invio email a: {$this->to} - {$errorMessage}";
        if ($this->logContext) {
            $message .= " - Contesto: {$this->logContext}";
        }

        // Log to dedicated email error log
        EmailLogService::logError('Email Send', $e, [
            'to' => $this->to,
            'mailable' => $this->mailableClass,
            'context' => $this->logContext,
            'job_id' => $jobId,
            'attempt' => $attempt
        ]);

        // Registra l'errore nel database degli errori di sistema se possibile
        try {
            \App\Models\SystemError::create([
                'exception_class' => get_class($e),
                'message' => "Job Email Send Error: " . $errorMessage,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_url' => 'Background Job (Email Queue)',
                'request_method' => 'JOB',
                'user_agent' => 'SWUDB-Bot/1.0',
                'status' => 'new'
            ]);
        } catch (\Exception $dbError) {
            // Silently fail if database is not accessible
            Log::warning('Failed to log email queue error to database', ['error' => $dbError->getMessage()]);
        }

        // Send Telegram notification
        MessageCreated::dispatch($message);

        // Backup to Laravel log
        Log::error('Email send failed', [
            'to' => $this->to,
            'mailable' => $this->mailableClass,
            'context' => $this->logContext,
            'error' => $errorMessage,
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
        $jobId = $this->job ? $this->job->getJobId() : 'fire-and-forget';
        
        $errorMessage = is_array($exception->getMessage()) ? json_encode($exception->getMessage()) : (string) $exception->getMessage();
        $message = "Invio email fallito definitivamente a: {$this->to} dopo {$this->tries} tentativi - {$errorMessage}";
        if ($this->logContext) {
            $message .= " - Contesto: {$this->logContext}";
        }

        // Log to dedicated email error log
        EmailLogService::logError('Email Job Failed Permanently', $exception, [
            'to' => $this->to,
            'mailable' => $this->mailableClass,
            'context' => $this->logContext,
            'job_id' => $jobId,
            'max_tries' => $this->tries
        ]);
        
        // Registra nel database errori se possibile
        try {
            \App\Models\SystemError::create([
                'exception_class' => get_class($exception),
                'message' => "Job Email Permanent Failure: " . $errorMessage,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'request_url' => 'Background Job (Email Queue)',
                'request_method' => 'JOB_FAILED',
                'user_agent' => 'SWUDB-Bot/1.0',
                'status' => 'new'
            ]);
        } catch (\Exception $dbError) {
            // Silently fail
        }

        // Send Telegram notification
        MessageCreated::dispatch($message);

        // Backup to Laravel log
        Log::critical('Email job failed permanently', [
            'to' => $this->to,
            'mailable' => $this->mailableClass,
            'context' => $this->logContext,
            'error' => $errorMessage,
            'job_id' => $jobId
        ]);
    }

    /**
     * Extract serializable data from mailable
     * Estrae dati serializzabili dal mailable
     *
     * @param mixed $mailable
     * @return array
     */
    protected function extractMailableData($mailable): array
    {
        $data = [];

        // Handle different mailable types
        if (method_exists($mailable, 'build')) {
            // For standard mailables, try to extract public properties
            $reflection = new \ReflectionClass($mailable);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

            foreach ($properties as $property) {
                $value = $property->getValue($mailable);
                // Only store serializable values
                if (is_scalar($value) || is_array($value) || is_null($value)) {
                    $data[$property->getName()] = $value;
                }
            }
        }

        // Store specific data based on mailable class
        if ($mailable instanceof \App\Mail\EmailVerificationMail) {
            $data['user_id'] = $mailable->user->id ?? null;
            $data['verification_url'] = $mailable->verificationUrl ?? null;
        } elseif ($mailable instanceof \App\Mail\ErrorNotificationEmail) {
            $data['error_message'] = $mailable->errorMessage ?? null;
            $data['error_file'] = $mailable->errorFile ?? null;
            $data['error_line'] = $mailable->errorLine ?? null;
            $data['request_url'] = $mailable->requestUrl ?? null;
            $data['request_method'] = $mailable->requestMethod ?? null;
            $data['user_agent'] = $mailable->userAgent ?? null;
            $data['timestamp'] = $mailable->timestamp ?? null;
            $data['exception_class'] = get_class($mailable->exception ?? new \Exception());
        } elseif ($mailable instanceof \App\Mail\NewCardsNotification) {
            $data['new_cards'] = $mailable->newCards ?? [];
        } elseif ($mailable instanceof \App\Mail\NewCardsEmail) {
            $data['cards'] = $mailable->cards ?? [];
        } elseif ($mailable instanceof \App\Mail\NewExpansionEmail) {
            $data['expansion'] = $mailable->expansion ?? [];
        } elseif ($mailable instanceof \App\Mail\ImportErrorsNotification) {
            $data['stats'] = $mailable->stats ?? [];
        } elseif ($mailable instanceof \App\Mail\AdministratorAnnouncement) {
            $data['subject'] = $mailable->subject ?? '';
            $data['messageBody'] = $mailable->messageBody ?? '';
        }

        // Debug logging for mailable class detection
        EmailLogService::logQueue("Mailable estratto: " . get_class($mailable) . " - Dati: " . json_encode(array_keys($data)));

        return $data;
    }

    /**
     * Recreate mailable from stored data
     * Ricrea il mailable dai dati memorizzati
     *
     * @return mixed
     */
    protected function recreateMailable()
    {
        // Validate mailable class
        if (empty($this->mailableClass) || !class_exists($this->mailableClass)) {
            throw new \Exception("Invalid mailable class: '{$this->mailableClass}'");
        }

        switch ($this->mailableClass) {
            case 'App\\Mail\\EmailVerificationMail':
                $user = \App\Models\User::find($this->mailableData['user_id'] ?? null);
                $verificationUrl = $this->mailableData['verification_url'] ?? '';
                return new \App\Mail\EmailVerificationMail($user, $verificationUrl);

            case 'App\\Mail\\ErrorNotificationEmail':
                // Create a generic exception from stored data
                $errorMessage = $this->mailableData['error_message'] ?? 'Unknown error';
                
                // Assicura che il messaggio sia una stringa per evitare "Array to string conversion"
                if (is_array($errorMessage)) {
                    $errorMessage = json_encode($errorMessage);
                }
                
                $exception = new \Exception((string) $errorMessage);

                return new \App\Mail\ErrorNotificationEmail(
                    $exception,
                    $this->mailableData['request_url'] ?? null,
                    $this->mailableData['request_method'] ?? null,
                    $this->mailableData['user_agent'] ?? null,
                    null // systemError
                );

            case 'App\\Mail\\NewCardsNotification':
                $newCards = $this->mailableData['new_cards'] ?? [];
                return new \App\Mail\NewCardsNotification($newCards);

            case 'App\\Mail\\NewCardsEmail':
                $cards = $this->mailableData['cards'] ?? [];
                return new \App\Mail\NewCardsEmail($cards);

            case 'App\\Mail\\NewExpansionEmail':
                $expansion = $this->mailableData['expansion'] ?? [];
                return new \App\Mail\NewExpansionEmail($expansion);

            case 'App\\Mail\\ImportErrorsNotification':
                $stats = $this->mailableData['stats'] ?? [];
                return new \App\Mail\ImportErrorsNotification($stats);

            case 'App\\Mail\\AdministratorAnnouncement':
                return new \App\Mail\AdministratorAnnouncement(
                    $this->mailableData['subject'] ?? '',
                    $this->mailableData['messageBody'] ?? ''
                );

            default:
                // Fallback: try to create with reflection
                $reflection = new \ReflectionClass($this->mailableClass);

                // Try to create with no arguments first
                if ($reflection->getConstructor() === null || $reflection->getConstructor()->getNumberOfRequiredParameters() === 0) {
                    $mailable = $reflection->newInstance();

                    // Set public properties from stored data
                    foreach ($this->mailableData as $property => $value) {
                        if ($reflection->hasProperty($property) && $reflection->getProperty($property)->isPublic()) {
                            $mailable->$property = $value;
                        }
                    }

                    return $mailable;
                }

                throw new \Exception("Cannot recreate mailable of class {$this->mailableClass}");
        }
    }
}
