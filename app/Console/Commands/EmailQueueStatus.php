<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\JobController;
use App\Services\EmailLogService;

/**
 * Command to check the status of the email queue
 * Comando per controllare lo stato della coda email
 */
class EmailQueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:status
                            {--trigger : Trigger the email queue processor}
                            {--clear-failed : Clear all failed email jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check email queue status and manage basic operations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Email Queue Status Report');
        $this->line('==========================');

        // Check pending jobs in emails queue
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'emails')
            ->count();

        $this->info("Pending email jobs: {$pendingJobs}");

        // Check failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'emails')
            ->count();

        $this->info("Failed email jobs: {$failedJobs}");

        // Check processor status
        $lastProcessorRun = Cache::get('email_processor_last_run', 0);
        $timeSinceLastRun = time() - $lastProcessorRun;

        if ($lastProcessorRun > 0) {
            $this->info("Last processor run: {$timeSinceLastRun} seconds ago");
        } else {
            $this->warn("Processor has never run");
        }

        // Check rate limiting status
        $currentSecond = now()->format('Y-m-d H:i:s');
        $cacheKey = 'email_rate_limit:' . $currentSecond;
        $currentCount = Cache::get($cacheKey, 0);

        $this->line('');
        $this->info('Rate Limiting Status:');
        $this->line("Emails sent this second: {$currentCount}/2");

        // Handle options
        if ($this->option('trigger')) {
            $this->triggerProcessor();
        }

        if ($this->option('clear-failed')) {
            $this->clearFailedJobs();
        }

        // Show recent failed jobs if any
        if ($failedJobs > 0) {
            $this->showRecentFailedJobs();
        }

        return 0;
    }
    
    /**
     * Trigger the email queue processor
     * Avvia il processore coda email
     */
    protected function triggerProcessor()
    {
        $this->info('Triggering email queue processor...');

        try {
            EmailLogService::logProcessor('Processore avviato manualmente da comando artisan');

            JobController::fireAndForgetGet(
                route('job.processEmailQueue'),
                ['token' => env('JOB_TOKEN')]
            );

            $this->info('Email queue processor triggered successfully');

        } catch (\Exception $e) {
            EmailLogService::logError('Manual Trigger', $e);
            $this->error('Failed to trigger processor: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear all failed email jobs
     * Cancella tutti i job email falliti
     */
    protected function clearFailedJobs()
    {
        $count = DB::table('failed_jobs')
            ->where('queue', 'emails')
            ->count();
            
        if ($count === 0) {
            $this->info('No failed email jobs to clear');
            return;
        }
        
        if ($this->confirm("Clear {$count} failed email jobs?")) {
            DB::table('failed_jobs')
                ->where('queue', 'emails')
                ->delete();

            EmailLogService::logQueue("Cancellati {$count} job falliti tramite comando artisan");
            $this->info("Cleared {$count} failed email jobs");
        }
    }
    

    
    /**
     * Show recent failed jobs
     * Mostra i job falliti recenti
     */
    protected function showRecentFailedJobs()
    {
        $this->line('');
        $this->info('Recent Failed Email Jobs:');
        
        $recentFailed = DB::table('failed_jobs')
            ->where('queue', 'emails')
            ->orderBy('failed_at', 'desc')
            ->limit(3)
            ->get(['uuid', 'exception', 'failed_at']);
            
        if ($recentFailed->isEmpty()) {
            $this->line('No recent failed jobs');
            return;
        }
        
        foreach ($recentFailed as $job) {
            $this->line("UUID: {$job->uuid}");
            $this->line("Failed at: {$job->failed_at}");
            
            // Extract error message from exception
            $exception = $job->exception;
            if (preg_match('/Exception: (.+?) in/', $exception, $matches)) {
                $this->error("Error: {$matches[1]}");
            }
            
            $this->line('---');
        }
    }
}
