<?php

namespace App\Services;

use App\Jobs\SendQueuedEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Events\MessageCreated;

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
}
