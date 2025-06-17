<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Service for managing threaded message execution contexts
 * Servizio per gestire i contesti di esecuzione dei messaggi in thread
 *
 * This service tracks active message threads and their current state,
 * allowing subsequent messages to replace previous ones within the same
 * execution context (like import processes or batch operations).
 *
 * Uses Laravel Cache for persistence across HTTP requests.
 */
class ThreadManager
{
    /**
     * Cache key prefix for thread storage
     * Prefisso chiave cache per memorizzazione thread
     */
    private const CACHE_PREFIX = 'thread_manager_';

    /**
     * Cache TTL in seconds (1 hour)
     * TTL cache in secondi (1 ora)
     */
    private const CACHE_TTL = 3600;

    /**
     * Start a new message thread or update an existing one
     * Avvia un nuovo thread di messaggi o aggiorna uno esistente
     *
     * @param string $threadId Unique identifier for the thread
     * @param string $message Initial or updated message content
     * @param bool $isComplete Whether this message marks the thread as complete
     * @param int|null $telegramMessageId Optional Telegram message ID for editing
     * @return void
     */
    public static function updateThread(string $threadId, string $message, bool $isComplete = false, ?int $telegramMessageId = null): void
    {
        $cacheKey = self::CACHE_PREFIX . $threadId;
        $existingThread = Cache::get($cacheKey);

        $threadData = [
            'message' => $message,
            'timestamp' => time(),
            'isComplete' => $isComplete,
            'telegramMessageId' => $telegramMessageId ?? ($existingThread['telegramMessageId'] ?? null)
        ];

        Cache::put($cacheKey, $threadData, self::CACHE_TTL);

        // Clean up completed threads after a short delay to allow final message delivery
        if ($isComplete) {
            // In a real application, you might want to use a queue job for cleanup
            // For now, we'll mark it as complete and clean up old completed threads
            self::cleanupCompletedThreads();
        }
    }

    /**
     * Get the Telegram message ID for a thread
     * Ottiene l'ID del messaggio Telegram per un thread
     *
     * @param string $threadId The thread identifier
     * @return int|null The Telegram message ID or null if not set
     */
    public static function getTelegramMessageId(string $threadId): ?int
    {
        $cacheKey = self::CACHE_PREFIX . $threadId;
        $threadData = Cache::get($cacheKey);
        return $threadData['telegramMessageId'] ?? null;
    }

    /**
     * Set the Telegram message ID for a thread
     * Imposta l'ID del messaggio Telegram per un thread
     *
     * @param string $threadId The thread identifier
     * @param int $telegramMessageId The Telegram message ID
     * @return void
     */
    public static function setTelegramMessageId(string $threadId, int $telegramMessageId): void
    {
        $cacheKey = self::CACHE_PREFIX . $threadId;
        $threadData = Cache::get($cacheKey);

        if ($threadData) {
            $threadData['telegramMessageId'] = $telegramMessageId;
            Cache::put($cacheKey, $threadData, self::CACHE_TTL);
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
        $cacheKey = self::CACHE_PREFIX . $threadId;
        return Cache::get($cacheKey);
    }

    /**
     * Get all active threads
     * Ottiene tutti i thread attivi
     *
     * @return array<string, array> All active thread data
     */
    public static function getAllThreads(): array
    {
        // This is a simplified implementation - in a real scenario you'd want
        // to scan cache keys with the prefix, but for now we'll return empty
        // since we don't have a direct way to list all cache keys
        return [];
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
     * Check if a thread is complete
     * Controlla se un thread è completato
     *
     * @param string $threadId The thread identifier
     * @return bool True if thread is completed or doesn't exist
     */
    public static function isThreadComplete(string $threadId): bool
    {
        $thread = self::getThread($threadId);
        return $thread === null || $thread['isComplete'];
    }

    /**
     * Get the latest message for a thread
     * Ottiene l'ultimo messaggio per un thread
     *
     * @param string $threadId The thread identifier
     * @return string|null The latest message or null if thread doesn't exist
     */
    public static function getLatestMessage(string $threadId): ?string
    {
        $thread = self::getThread($threadId);
        return $thread['message'] ?? null;
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
        $cacheKey = self::CACHE_PREFIX . $threadId;
        $threadData = Cache::get($cacheKey);

        if ($threadData) {
            $threadData['isComplete'] = true;
            if ($finalMessage !== null) {
                $threadData['message'] = $finalMessage;
            }
            $threadData['timestamp'] = time();
            Cache::put($cacheKey, $threadData, self::CACHE_TTL);
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
        // For cache-based implementation, we rely on TTL for cleanup
        // In a more advanced implementation, you could scan cache keys
        // and manually remove old completed threads
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
        // For cache-based implementation, this would require scanning
        // all cache keys with our prefix and removing them
        // For now, we'll leave this as a placeholder
    }

    /**
     * Get thread statistics for debugging
     * Ottiene statistiche sui thread per il debug
     *
     * @return array Statistics about active and completed threads
     */
    public static function getThreadStats(): array
    {
        // For cache-based implementation, this is simplified
        // In a real scenario, you'd scan cache keys to get actual stats
        return [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'note' => 'Cache-based implementation - stats not available'
        ];
    }
}
