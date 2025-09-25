<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Command to clean up email queue duplicates and issues
 * Comando per pulire duplicati e problemi nella coda email
 */
class EmailQueueCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:cleanup {--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up email queue duplicates and problematic jobs';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Email Queue Cleanup');
        $this->info('==================');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }
        
        // 1. Check for jobs with empty mailable class
        $this->info('1. Checking for jobs with invalid mailable classes...');
        $invalidJobs = DB::table('jobs')
            ->where('queue', 'emails')
            ->get()
            ->filter(function ($job) {
                $payload = json_decode($job->payload, true);
                $command = unserialize($payload['data']['command'] ?? '');
                return empty($command->mailableClass ?? '');
            });
            
        if ($invalidJobs->count() > 0) {
            $this->error("Found {$invalidJobs->count()} jobs with invalid mailable classes");
            
            if (!$dryRun) {
                foreach ($invalidJobs as $job) {
                    DB::table('jobs')->where('id', $job->id)->delete();
                }
                $this->info("✅ Deleted {$invalidJobs->count()} invalid jobs");
            } else {
                $this->info("Would delete {$invalidJobs->count()} invalid jobs");
            }
        } else {
            $this->info("✅ No invalid jobs found");
        }
        
        // 2. Check for duplicate jobs (same email, same context)
        $this->info('2. Checking for duplicate jobs...');
        $allJobs = DB::table('jobs')
            ->where('queue', 'emails')
            ->get();
            
        $duplicates = [];
        $seen = [];
        
        foreach ($allJobs as $job) {
            try {
                $payload = json_decode($job->payload, true);
                $command = unserialize($payload['data']['command'] ?? '');
                
                $key = ($command->to ?? '') . '|' . ($command->logContext ?? '') . '|' . ($command->mailableClass ?? '');
                
                if (isset($seen[$key])) {
                    $duplicates[] = $job->id;
                } else {
                    $seen[$key] = $job->id;
                }
            } catch (\Exception $e) {
                $this->warn("Could not process job {$job->id}: " . $e->getMessage());
                $duplicates[] = $job->id;
            }
        }
        
        if (count($duplicates) > 0) {
            $this->error("Found " . count($duplicates) . " duplicate jobs");
            
            if (!$dryRun) {
                DB::table('jobs')->whereIn('id', $duplicates)->delete();
                $this->info("✅ Deleted " . count($duplicates) . " duplicate jobs");
            } else {
                $this->info("Would delete " . count($duplicates) . " duplicate jobs");
            }
        } else {
            $this->info("✅ No duplicate jobs found");
        }
        
        // 3. Show current queue status
        $this->info('3. Current queue status:');
        $totalJobs = DB::table('jobs')->where('queue', 'emails')->count();
        $failedJobs = DB::table('failed_jobs')->where('queue', 'emails')->count();
        
        $this->info("📊 Active jobs: {$totalJobs}");
        $this->info("❌ Failed jobs: {$failedJobs}");
        
        // 4. Clear old failed jobs (older than 7 days)
        $this->info('4. Cleaning old failed jobs...');
        $oldFailedJobs = DB::table('failed_jobs')
            ->where('queue', 'emails')
            ->where('failed_at', '<', now()->subDays(7))
            ->count();
            
        if ($oldFailedJobs > 0) {
            if (!$dryRun) {
                DB::table('failed_jobs')
                    ->where('queue', 'emails')
                    ->where('failed_at', '<', now()->subDays(7))
                    ->delete();
                $this->info("✅ Deleted {$oldFailedJobs} old failed jobs");
            } else {
                $this->info("Would delete {$oldFailedJobs} old failed jobs");
            }
        } else {
            $this->info("✅ No old failed jobs to clean");
        }
        
        $this->info('');
        $this->info('🎉 Email queue cleanup completed!');
        
        return 0;
    }
}
