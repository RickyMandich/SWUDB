<?php

namespace App\Listeners;

use App\Events\ThreadMessageCreated;
use App\Http\Controllers\JobController;
use App\Services\ThreadManager;

/**
 * Listener for ThreadMessageCreated event that handles threaded message processing
 * Listener per l'evento ThreadMessageCreated che gestisce l'elaborazione dei messaggi in thread
 *
 * This listener responds to ThreadMessageCreated events by managing thread state
 * and sending messages that replace previous ones within the same execution context.
 * This prevents notification spam during long-running processes like imports.
 */
class SendThreadMessage
{
    /**
     * Handle the ThreadMessageCreated event by managing thread state and sending messages
     * Gestisce l'evento ThreadMessageCreated gestendo lo stato del thread e inviando messaggi
     *
     * The logic works as follows:
     * 1. Update the thread state with the new message
     * 2. For Telegram integration, send a message that can replace the previous one
     * 3. If the thread is complete, mark it for cleanup
     *
     * @param ThreadMessageCreated $event The threaded message event containing thread and message data
     * @return void
     */
    public function handle(ThreadMessageCreated $event): void
    {
        // Update thread state in the manager
        ThreadManager::updateThread($event->threadId, $event->message, $event->isComplete);
        
        // Log debug information if enabled
        if (env("APP_DEBUG")) {
            $debugMessage = "ThreadMessage [{$event->threadId}]: {$event->message}" . 
                           ($event->isComplete ? " [COMPLETE]" : "");
            file_put_contents(__DIR__ . "/debug-sendThreadMessage.log", 
                             $debugMessage . "\n", FILE_APPEND);
        }

        // Prepare the message for Telegram with thread context
        $telegramMessage = $this->formatMessageForTelegram($event);
        
        // Send the message via JobController
        JobController::fireAndForgetGet(route("job.sendThreadMessage"), [
            "threadId" => $event->threadId,
            "message" => $telegramMessage,
            "isComplete" => $event->isComplete ? 1 : 0,
            "token" => env("JOB_TOKEN")
        ]);
    }

    /**
     * Format the message for Telegram with thread context information
     * Formatta il messaggio per Telegram con informazioni di contesto del thread
     *
     * This method adds thread identification and status indicators to help
     * users understand which process the message belongs to and its current state.
     *
     * @param ThreadMessageCreated $event The event containing message data
     * @return string Formatted message ready for Telegram
     */
    private function formatMessageForTelegram(ThreadMessageCreated $event): string
    {
        $threadPrefix = $this->getThreadPrefix($event->threadId);
        $statusIcon = $event->isComplete ? "✅" : "🔄";
        
        return "{$statusIcon} {$threadPrefix}: {$event->message}";
    }

    /**
     * Extract a human-readable prefix from the thread ID
     * Estrae un prefisso leggibile dall'ID del thread
     *
     * This method attempts to create a meaningful prefix from the thread ID
     * to help users identify which process the message belongs to.
     *
     * @param string $threadId The thread identifier
     * @return string Human-readable prefix for the thread
     */
    private function getThreadPrefix(string $threadId): string
    {
        // Extract the prefix part before the first underscore
        $parts = explode('_', $threadId);
        $prefix = $parts[0] ?? 'Process';
        
        // Convert common prefixes to Italian
        $translations = [
            'import' => 'Importazione',
            'update' => 'Aggiornamento',
            'batch' => 'Elaborazione',
            'sync' => 'Sincronizzazione',
            'thread' => 'Processo'
        ];
        
        return $translations[strtolower($prefix)] ?? ucfirst($prefix);
    }
}
