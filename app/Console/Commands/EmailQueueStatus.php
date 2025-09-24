<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Command to check the status of the email queue
 * Comando per controllare lo stato della coda email
 *
 * This command provides information about pending, failed, and processed emails
 * in the queue system.
 */
class EmailQueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:queue-status 
                            {--clear-failed : Clear all failed email jobs}
                            {--retry-failed : Retry all failed email jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of the email queue and manage failed jobs';

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
        
        // Check rate limiting status
        $this->checkRateLimitStatus();
        
        // Handle options
        if ($this->option('clear-failed')) {
            $this->clearFailedJobs();
        }
        
        if ($this->option('retry-failed')) {
            $this->retryFailedJobs();
        }
        
        // Show recent failed jobs if any
        if ($failedJobs > 0) {
            $this->showRecentFailedJobs();
        }
        
        return 0;
    }
    
    /**
     * Check rate limiting status
     * Controlla lo stato del rate limiting
     */
    protected function checkRateLimitStatus()
    {
        $this->line('');
        $this->info('Rate Limiting Status:');
        
        $currentSecond = now()->format('Y-m-d H:i:s');
        $cacheKey = 'email_rate_limit:' . $currentSecond;
        $currentCount = Cache::get($cacheKey, 0);
        
        $this->line("Current second: {$currentSecond}");
        $this->line("Emails sent this second: {$currentCount}/2");
        
        if ($currentCount >= 2) {
            $this->warn('Rate limit reached for current second');
        } else {
            $this->info('Rate limit OK');
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
                
            $this->info("Cleared {$count} failed email jobs");
        }
    }
    
    /**
     * Retry all failed email jobs
     * Riprova tutti i job email falliti
     */
    protected function retryFailedJobs()
    {
        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'emails')
            ->get();
            
        if ($failedJobs->isEmpty()) {
            $this->info('No failed email jobs to retry');
            return;
        }
        
        $count = $failedJobs->count();
        
        if ($this->confirm("Retry {$count} failed email jobs?")) {
            foreach ($failedJobs as $job) {
                $this->call('queue:retry', ['id' => $job->uuid]);
            }
            
            $this->info("Retried {$count} failed email jobs");
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
            ->limit(5)
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
