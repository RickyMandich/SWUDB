<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailLogService;

/**
 * Command to clean up old email log files
 * Comando per pulire i file di log email vecchi
 */
class EmailLogCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:cleanup-logs 
                            {--days=30 : Number of days to keep logs}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old email log files (default: older than 30 days)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("Email Log Cleanup");
        $this->info("================");
        $this->line("Keeping logs from last {$days} days");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files will be deleted");
        }
        
        $this->line('');
        
        try {
            if ($dryRun) {
                $deletedCount = $this->simulateCleanup($days);
            } else {
                $deletedCount = EmailLogService::cleanupOldLogs($days);
            }
            
            if ($deletedCount > 0) {
                $action = $dryRun ? 'would be deleted' : 'deleted';
                $this->info("✅ {$deletedCount} old log files {$action}");
            } else {
                $this->info("✅ No old log files found to clean up");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error during cleanup: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Simulate cleanup without actually deleting files
     * Simula la pulizia senza eliminare effettivamente i file
     *
     * @param int $days
     * @return int
     */
    private function simulateCleanup(int $days): int
    {
        $mailLogDir = storage_path('logs/mail');
        
        if (!is_dir($mailLogDir)) {
            return 0;
        }
        
        $cutoffDate = now()->subDays($days);
        $wouldDeleteCount = 0;
        
        $files = glob($mailLogDir . '/*.log');
        
        foreach ($files as $file) {
            $fileTime = filemtime($file);
            
            if ($fileTime < $cutoffDate->timestamp) {
                $fileName = basename($file);
                $fileDate = date('Y-m-d H:i:s', $fileTime);
                $this->line("Would delete: {$fileName} (modified: {$fileDate})");
                $wouldDeleteCount++;
            }
        }
        
        return $wouldDeleteCount;
    }
}
