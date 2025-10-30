<?php

namespace App\Services;

use App\Jobs\SendQueuedEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Events\MessageCreated;
use App\Services\EmailLogService;

/**
 * Service for managing email queue operations with rate limiting
 * Servizio per gestire le operazioni di coda email con rate limiting
 *
 * This service provides methods to queue emails safely with rate limiting
 * to prevent email provider overflow and errors.
 */
class EmailQueueService
{
    /**
     * Queue an email for sending with rate limiting
     * Mette in coda un'email per l'invio con rate limiting
     *
     * @param mixed $mailable The mailable instance to send
     * @param string|array $to The recipient email address(es)
     * @param string $logContext Optional context for logging
     * @param int $delay Optional delay in seconds before sending
     * @return void
     */
    public static function queue($mailable, $to, string $logContext = '', int $delay = 0)
    {
        // Handle multiple recipients
        if (is_array($to)) {
            foreach ($to as $recipient) {
                self::queueSingle($mailable, $recipient, $logContext, $delay);
            }
            return;
        }

        self::queueSingle($mailable, $to, $logContext, $delay);
    }

    /**
     * Queue a single email
     * Mette in coda una singola email
     *
     * @param mixed $mailable The mailable instance to send
     * @param string $to The recipient email address
     * @param string $logContext Optional context for logging
     * @param int $delay Optional delay in seconds before sending
     * @return void
     */
    protected static function queueSingle($mailable, string $to, string $logContext = '', int $delay = 0)
    {
        try {
            $job = new SendQueuedEmail($mailable, $to, $logContext);

            if ($delay > 0) {
                $job->delay(now()->addSeconds($delay));
            }

            dispatch($job);

            // Trigger email queue processor via fire and forget
            // Avvia il processore coda email via fire and forget
            self::triggerQueueProcessor();

            // Log to dedicated email queue log
            EmailLogService::logQueue("Email accodata per: {$to} - Mailable: " . get_class($mailable) .
                ($logContext ? " - Contesto: {$logContext}" : "") .
                ($delay > 0 ? " - Delay: {$delay}s" : ""));

            Log::info('Email queued successfully', [
                'to' => $to,
                'mailable' => get_class($mailable),
                'context' => $logContext,
                'delay' => $delay
            ]);

        } catch (\Exception $e) {
            $message = "Errore nell'accodamento email per: {$to} - {$e->getMessage()}";
            if ($logContext) {
                $message .= " - Contesto: {$logContext}";
            }

            // Log to dedicated email error log
            EmailLogService::logError('Email Queue', $e, [
                'to' => $to,
                'mailable' => get_class($mailable),
                'context' => $logContext
            ]);

            MessageCreated::dispatch($message);

            Log::error('Failed to queue email', [
                'to' => $to,
                'mailable' => get_class($mailable),
                'context' => $logContext,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger the email queue processor via fire and forget
     * Avvia il processore coda email via fire and forget
     *
     * @return void
     */
    protected static function triggerQueueProcessor()
    {
        try {
            // Check if processor is already running by looking for recent activity
            $lastProcessorRun = \Cache::get('email_processor_last_run', 0);
            $now = time();

            // Only trigger if processor hasn't run in the last 30 seconds
            if (($now - $lastProcessorRun) > 30) {
                \Cache::put('email_processor_last_run', $now, 60);

                \App\Http\Controllers\JobController::fireAndForgetGet(
                    route('job.processEmailQueue'),
                    ['token' => env('JOB_TOKEN')]
                );

                EmailLogService::logProcessor('Processore coda email avviato automaticamente');
                Log::info('Email queue processor triggered');
            }

        } catch (\Exception $e) {
            EmailLogService::logError('Trigger Queue Processor', $e);
            Log::error('Failed to trigger email queue processor', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send email immediately (fallback for critical emails)
     * Invia email immediatamente (fallback per email critiche)
     *
     * @param mixed $mailable The mailable instance to send
     * @param string $to The recipient email address
     * @param string $logContext Optional context for logging
     * @return bool Success status
     */
    public static function sendImmediate($mailable, string $to, string $logContext = ''): bool
    {
        try {
            Mail::to($to)->send($mailable);
            
            $message = "Email inviata immediatamente a: {$to}";
            if ($logContext) {
                $message .= " - Contesto: {$logContext}";
            }
            
            MessageCreated::dispatch($message);
            
            Log::info('Email sent immediately', [
                'to' => $to,
                'mailable' => get_class($mailable),
                'context' => $logContext
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $message = "Errore invio email immediato a: {$to} - {$e->getMessage()}";
            if ($logContext) {
                $message .= " - Contesto: {$logContext}";
            }
            
            MessageCreated::dispatch($message);
            
            Log::error('Immediate email send failed', [
                'to' => $to,
                'mailable' => get_class($mailable),
                'context' => $logContext,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Queue emails to multiple users with automatic batching
     * Mette in coda email a più utenti con batching automatico
     *
     * @param mixed $mailable The mailable instance to send
     * @param \Illuminate\Support\Collection $users Collection of users with email attribute
     * @param string $logContext Optional context for logging
     * @param int $batchDelay Delay in seconds between batches
     * @return void
     */
    public static function queueToUsers($mailable, $users, string $logContext = '', int $batchDelay = 0)
    {
        $delay = 0;
        $batchSize = 10; // Send in batches of 10 to spread load
        
        foreach ($users->chunk($batchSize) as $batch) {
            foreach ($batch as $user) {
                if (!empty($user->email)) {
                    self::queueSingle($mailable, $user->email, $logContext, $delay);
                }
            }
            
            // Add delay between batches if specified
            if ($batchDelay > 0) {
                $delay += $batchDelay;
            }
        }
        
        $totalEmails = $users->where('email', '!=', null)->count();
        $message = "Accodate {$totalEmails} email";
        if ($logContext) {
            $message .= " - Contesto: {$logContext}";
        }

        EmailLogService::logQueue("Email accodate per {$totalEmails} utenti - Mailable: " . get_class($mailable) .
            ($logContext ? " - Contesto: {$logContext}" : "") . " - Batch size: 10");

        MessageCreated::dispatch($message);
        
        Log::info('Bulk emails queued', [
            'total_emails' => $totalEmails,
            'mailable' => get_class($mailable),
            'context' => $logContext,
            'batch_size' => $batchSize,
            'batch_delay' => $batchDelay
        ]);
    }

    /**
     * Queue emails to admin users
     * Mette in coda email agli utenti admin
     *
     * @param mixed $mailable The mailable instance to send
     * @param string $logContext Optional context for logging
     * @return void
     */
    public static function queueToAdmins($mailable, string $logContext = '')
    {
        try {
            $admins = \App\Models\User::getAdmins();
            
            if ($admins->isEmpty()) {
                Log::warning('No admin users found for email notification', [
                    'mailable' => get_class($mailable),
                    'context' => $logContext
                ]);
                return;
            }
            
            self::queueToUsers($mailable, $admins, $logContext);
            
        } catch (\Exception $e) {
            $message = "Errore nell'accodamento email admin - {$e->getMessage()}";
            if ($logContext) {
                $message .= " - Contesto: {$logContext}";
            }
            
            MessageCreated::dispatch($message);
            
            Log::error('Failed to queue admin emails', [
                'mailable' => get_class($mailable),
                'context' => $logContext,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Queue multiple notification types with coordinated delays
     * Mette in coda più tipi di notifiche con delay coordinati
     *
     * This method ensures that multiple notification types (expansions, cards, etc.)
     * are queued with proper delays to prevent rate limiting issues.
     *
     * @param array $notifications Array of notification configurations
     * Each notification should have: type, mailable, recipients, context
     * @return void
     */
    public static function queueBulkNotifications(array $notifications)
    {
        if (empty($notifications)) {
            return;
        }

        $totalDelay = 5; // Start with 5 second initial delay
        $batchSize = 3; // Further reduced batch size for maximum safety
        $batchDelay = 15; // Increased to 15 seconds between batches

        EmailLogService::logQueue("Inizio accodamento bulk di " . count($notifications) . " tipi di notifiche con delay iniziale di {$totalDelay}s");

        foreach ($notifications as $notification) {
            if (!isset($notification['mailable'], $notification['recipients'], $notification['context'])) {
                Log::warning('Invalid notification configuration skipped', $notification);
                continue;
            }

            $mailable = $notification['mailable'];
            $recipients = $notification['recipients'];
            $context = $notification['context'];
            $type = $notification['type'] ?? 'unknown';

            // Convert to collection if needed
            if (!($recipients instanceof \Illuminate\Support\Collection)) {
                $recipients = collect($recipients);
            }

            // Filter out users without email
            $validRecipients = $recipients->filter(function($user) {
                return !empty($user->email);
            });

            if ($validRecipients->isEmpty()) {
                Log::warning("No valid recipients for notification type: {$type}");
                continue;
            }

            EmailLogService::logQueue("Accodamento {$type}: {$validRecipients->count()} destinatari con delay iniziale {$totalDelay}s");

            // Queue emails for this notification type with current delay
            $currentDelay = $totalDelay;
            $emailDelay = 2; // 2 seconds between individual emails

            foreach ($validRecipients->chunk($batchSize) as $batch) {
                foreach ($batch as $user) {
                    self::queueSingle($mailable, $user->email, $context, $currentDelay);
                    $currentDelay += $emailDelay; // Add delay between individual emails
                }

                // Add extra delay between batches (on top of individual delays)
                $currentDelay += $batchDelay;
            }

            // Update total delay for next notification type
            // Calculate based on individual emails + batch delays
            $emailCount = $validRecipients->count();
            $batchCount = ceil($emailCount / $batchSize);
            $individualDelays = $emailCount * $emailDelay;
            $batchDelays = $batchCount * $batchDelay;
            $totalDelay += $individualDelays + $batchDelays;

            EmailLogService::logQueue("Completato {$type}: {$batchCount} batch, prossimo delay: {$totalDelay}s");
        }

        EmailLogService::logQueue("Bulk accodamento completato - delay totale finale: {$totalDelay}s");
        Log::info('Bulk notifications queued successfully', [
            'notification_types' => count($notifications),
            'total_delay' => $totalDelay
        ]);
    }
}
