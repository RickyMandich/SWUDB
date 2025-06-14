<?php

namespace App\Services;

/**
 * Service for managing threaded message execution contexts
 * Servizio per gestire i contesti di esecuzione dei messaggi in thread
 *
 * This service tracks active message threads and their current state,
 * allowing subsequent messages to replace previous ones within the same
 * execution context (like import processes or batch operations).
 */
class ThreadManager
{
    /**
     * Storage for active thread states
     * Memorizzazione degli stati dei thread attivi
     *
     * @var array<string, array{message: string, timestamp: int, isComplete: bool}>
     */
    private static array $threads = [];

    /**
     * Start a new message thread or update an existing one
     * Avvia un nuovo thread di messaggi o aggiorna uno esistente
     *
     * @param string $threadId Unique identifier for the thread
     * @param string $message Initial or updated message content
     * @param bool $isComplete Whether this message marks the thread as complete
     * @return void
     */
    public static function updateThread(string $threadId, string $message, bool $isComplete = false): void
    {
        self::$threads[$threadId] = [
            'message' => $message,
            'timestamp' => time(),
            'isComplete' => $isComplete
        ];

        // Clean up completed threads after a short delay to allow final message delivery
        if ($isComplete) {
            // In a real application, you might want to use a queue job for cleanup
            // For now, we'll mark it as complete and clean up old completed threads
            self::cleanupCompletedThreads();
        }
    }

    /**
     * Get the current message for a specific thread
     * Ottiene il messaggio corrente per un thread specifico
     *
     * @param string $threadId The thread identifier
     * @return array|null Thread data or null if thread doesn't exist
     */
    public static function getThread(string $threadId): ?array
    {
        return self::$threads[$threadId] ?? null;
    }

    /**
     * Get all active threads
     * Ottiene tutti i thread attivi
     *
     * @return array<string, array> All active thread data
     */
    public static function getAllThreads(): array
    {
        return self::$threads;
    }

    /**
     * Check if a thread exists and is active
     * Controlla se un thread esiste ed è attivo
     *
     * @param string $threadId The thread identifier
     * @return bool True if thread exists and is not completed
     */
    public static function isThreadActive(string $threadId): bool
    {
        $thread = self::getThread($threadId);
        return $thread !== null && !$thread['isComplete'];
    }

    /**
     * Mark a thread as complete
     * Marca un thread come completato
     *
     * @param string $threadId The thread identifier
     * @param string|null $finalMessage Optional final message for the thread
     * @return void
     */
    public static function completeThread(string $threadId, ?string $finalMessage = null): void
    {
        if (isset(self::$threads[$threadId])) {
            self::$threads[$threadId]['isComplete'] = true;
            if ($finalMessage !== null) {
                self::$threads[$threadId]['message'] = $finalMessage;
            }
            self::$threads[$threadId]['timestamp'] = time();
        }
    }

    /**
     * Remove old completed threads to prevent memory leaks
     * Rimuove i thread completati vecchi per prevenire perdite di memoria
     *
     * @param int $maxAge Maximum age in seconds for completed threads (default: 300 = 5 minutes)
     * @return void
     */
    public static function cleanupCompletedThreads(int $maxAge = 300): void
    {
        $currentTime = time();
        
        foreach (self::$threads as $threadId => $threadData) {
            if ($threadData['isComplete'] && ($currentTime - $threadData['timestamp']) > $maxAge) {
                unset(self::$threads[$threadId]);
            }
        }
    }

    /**
     * Generate a unique thread ID for a new execution context
     * Genera un ID thread univoco per un nuovo contesto di esecuzione
     *
     * @param string $prefix Optional prefix for the thread ID (e.g., 'import', 'update')
     * @return string Unique thread identifier
     */
    public static function generateThreadId(string $prefix = 'thread'): string
    {
        return $prefix . '_' . uniqid() . '_' . time();
    }

    /**
     * Clear all threads (useful for testing or reset operations)
     * Cancella tutti i thread (utile per test o operazioni di reset)
     *
     * @return void
     */
    public static function clearAllThreads(): void
    {
        self::$threads = [];
    }

    /**
     * Get thread statistics for debugging
     * Ottiene statistiche sui thread per il debug
     *
     * @return array Statistics about active and completed threads
     */
    public static function getThreadStats(): array
    {
        $active = 0;
        $completed = 0;
        
        foreach (self::$threads as $threadData) {
            if ($threadData['isComplete']) {
                $completed++;
            } else {
                $active++;
            }
        }
        
        return [
            'total' => count(self::$threads),
            'active' => $active,
            'completed' => $completed
        ];
    }
}
