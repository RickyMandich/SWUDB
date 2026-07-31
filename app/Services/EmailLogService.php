<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing email-specific logging
 * Servizio per gestire il logging specifico delle email
 *
 * This service provides dedicated logging functionality for email operations,
 * creating timestamped log files in the storage/logs/mail directory.
 */
class EmailLogService
{
    /**
     * Create a new email log file for the current session
     * Crea un nuovo file di log email per la sessione corrente
     *
     * @param string $operation The operation type (queue, send, process, etc.)
     * @return string The log file path
     */
    public static function createLogFile(string $operation = 'general'): string
    {
        $timestamp = now()->format('Y_m_d_H_i');
        $filename = "email_{$operation}_{$timestamp}.log";
        $logPath = storage_path("logs/mail/{$filename}");

        // Ensure logs/mail directory exists
        $logDir = dirname($logPath);
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        // Write initial log header
        self::writeToFile($logPath, "=== INIZIO SESSIONE EMAIL - " . strtoupper($operation) . " ===");
        self::writeToFile($logPath, "Timestamp: " . now()->format('d/m/Y H:i:s'));
        self::writeToFile($logPath, "Operazione: {$operation}");
        self::writeToFile($logPath, "Versione: " . self::getAppVersion());
        self::writeToFile($logPath, "Provider: " . config('mail.default', 'unknown'));
        self::writeToFile($logPath, "Rate Limit: 2 email/secondo");
        self::writeToFile($logPath, "=====================================");

        return $logPath;
    }

    /**
     * Write a message to a specific email log file
     * Scrive un messaggio in un file di log email specifico
     *
     * @param string $logFile The log file path
     * @param string $message The message to log
     * @param string $level The log level (INFO, ERROR, WARNING, DEBUG)
     * @return void
     */
    public static function writeToFile(string $logFile, string $message, string $level = 'INFO'): void
    {
        $timestamp = now()->format('H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

        // Ensure directory exists
        $logDir = dirname($logFile);
        if (!File::exists($logDir)) {
            File::makeDirectory($logDir, 0755, true);
        }

        // file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to Laravel log for backup
        Log::info("EMAIL: {$message}");
    }

    /**
     * Log email queue operations
     * Registra operazioni di coda email
     *
     * @param string $message The message to log
     * @param string $level The log level
     * @param string|null $logFile Optional specific log file
     * @return void
     */
    public static function logQueue(string $message, string $level = 'INFO', ?string $logFile = null): void
    {
        if (!$logFile) {
            $logFile = self::getOrCreateQueueLogFile();
        }

        self::writeToFile($logFile, $message, $level);
    }

    /**
     * Log email sending operations
     * Registra operazioni di invio email
     *
     * @param string $message The message to log
     * @param string $level The log level
     * @param string|null $logFile Optional specific log file
     * @return void
     */
    public static function logSend(string $message, string $level = 'INFO', ?string $logFile = null): void
    {
        if (!$logFile) {
            $logFile = self::getOrCreateSendLogFile();
        }

        self::writeToFile($logFile, $message, $level);
    }

    /**
     * Log email processor operations
     * Registra operazioni del processore email
     *
     * @param string $message The message to log
     * @param string $level The log level
     * @param string|null $logFile Optional specific log file
     * @return void
     */
    public static function logProcessor(string $message, string $level = 'INFO', ?string $logFile = null): void
    {
        if (!$logFile) {
            $logFile = self::getOrCreateProcessorLogFile();
        }

        self::writeToFile($logFile, $message, $level);
    }

    /**
     * Log email errors with detailed information
     * Registra errori email con informazioni dettagliate
     *
     * @param string $operation The operation that failed
     * @param \Exception $exception The exception that occurred
     * @param array $context Additional context information
     * @return void
     */
    public static function logError(string $operation, \Exception $exception, array $context = []): void
    {
        $errorLogFile = self::getOrCreateErrorLogFile();

        self::writeToFile($errorLogFile, "=== ERRORE EMAIL - {$operation} ===", 'ERROR');
        self::writeToFile($errorLogFile, "Messaggio: " . $exception->getMessage(), 'ERROR');
        self::writeToFile($errorLogFile, "File: " . $exception->getFile(), 'ERROR');
        self::writeToFile($errorLogFile, "Linea: " . $exception->getLine(), 'ERROR');

        if (!empty($context)) {
            self::writeToFile($errorLogFile, "Contesto: " . json_encode($context, JSON_PRETTY_PRINT), 'ERROR');
        }

        self::writeToFile($errorLogFile, "Stack Trace:", 'ERROR');
        self::writeToFile($errorLogFile, $exception->getTraceAsString(), 'ERROR');
        self::writeToFile($errorLogFile, "=====================================", 'ERROR');
    }

    /**
     * Log email statistics and metrics
     * Registra statistiche e metriche email
     *
     * @param array $stats The statistics to log
     * @return void
     */
    public static function logStats(array $stats): void
    {
        $statsLogFile = self::getOrCreateStatsLogFile();

        self::writeToFile($statsLogFile, "=== STATISTICHE EMAIL ===");
        foreach ($stats as $key => $value) {
            self::writeToFile($statsLogFile, "{$key}: {$value}");
        }
        self::writeToFile($statsLogFile, "========================");
    }

    /**
     * Get or create the queue log file for today
     * Ottiene o crea il file di log coda per oggi
     *
     * @return string
     */
    private static function getOrCreateQueueLogFile(): string
    {
        $date = now()->format('Y_m_d');
        $logFile = storage_path("logs/mail/queue_{$date}.log");

        if (!File::exists($logFile)) {
            self::writeToFile($logFile, "=== LOG CODA EMAIL - " . now()->format('d/m/Y') . " ===");
        }

        return $logFile;
    }

    /**
     * Get or create the send log file for today
     * Ottiene o crea il file di log invio per oggi
     *
     * @return string
     */
    private static function getOrCreateSendLogFile(): string
    {
        $date = now()->format('Y_m_d');
        $logFile = storage_path("logs/mail/send_{$date}.log");

        if (!File::exists($logFile)) {
            self::writeToFile($logFile, "=== LOG INVIO EMAIL - " . now()->format('d/m/Y') . " ===");
        }

        return $logFile;
    }

    /**
     * Get or create the processor log file for today
     * Ottiene o crea il file di log processore per oggi
     *
     * @return string
     */
    private static function getOrCreateProcessorLogFile(): string
    {
        $date = now()->format('Y_m_d');
        $logFile = storage_path("logs/mail/processor_{$date}.log");

        if (!File::exists($logFile)) {
            self::writeToFile($logFile, "=== LOG PROCESSORE EMAIL - " . now()->format('d/m/Y') . " ===");
        }

        return $logFile;
    }

    /**
     * Get or create the error log file for today
     * Ottiene o crea il file di log errori per oggi
     *
     * @return string
     */
    private static function getOrCreateErrorLogFile(): string
    {
        $date = now()->format('Y_m_d');
        $logFile = storage_path("logs/mail/errors_{$date}.log");

        if (!File::exists($logFile)) {
            self::writeToFile($logFile, "=== LOG ERRORI EMAIL - " . now()->format('d/m/Y') . " ===");
        }

        return $logFile;
    }

    /**
     * Get or create the stats log file for today
     * Ottiene o crea il file di log statistiche per oggi
     *
     * @return string
     */
    private static function getOrCreateStatsLogFile(): string
    {
        $date = now()->format('Y_m_d');
        $logFile = storage_path("logs/mail/stats_{$date}.log");

        if (!File::exists($logFile)) {
            self::writeToFile($logFile, "=== LOG STATISTICHE EMAIL - " . now()->format('d/m/Y') . " ===");
        }

        return $logFile;
    }

    /**
     * Get the application version string
     * Ottiene la stringa versione dell'applicazione
     *
     * @return string
     */
    private static function getAppVersion(): string
    {
        $primary = env('APP_VERSION_PRIMARY', '1');
        $secondary = env('APP_VERSION_SECONDARY', '0');
        $tertiary = env('APP_VERSION_TERTIARY', '0');

        return "{$primary}.{$secondary}.{$tertiary}";
    }

    /**
     * Clean up old log files (older than specified days)
     * Pulisce i file di log vecchi (più vecchi dei giorni specificati)
     *
     * @param int $days Number of days to keep
     * @return int Number of files deleted
     */
    public static function cleanupOldLogs(int $days = 30): int
    {
        $mailLogDir = storage_path('logs/mail');

        if (!File::exists($mailLogDir)) {
            return 0;
        }

        $cutoffDate = now()->subDays($days);
        $deletedCount = 0;

        $files = File::files($mailLogDir);

        foreach ($files as $file) {
            $fileTime = File::lastModified($file->getPathname());

            if ($fileTime < $cutoffDate->timestamp) {
                File::delete($file->getPathname());
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            self::logQueue("Pulizia log completata: {$deletedCount} file eliminati");
        }

        return $deletedCount;
    }
}
