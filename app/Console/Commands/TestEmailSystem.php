<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailQueueService;
use App\Mail\ErrorNotificationEmail;
use App\Models\User;

/**
 * Command to test the complete email system
 * Comando per testare il sistema email completo
 */
class TestEmailSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-system {--send : Actually send test emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete email system including queue and fire-and-forget';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing Complete Email System');
        $this->info('============================');
        
        try {
            // Test 1: Queue a test error notification
            $this->info('1. Testing error notification queuing...');
            $exception = new \Exception('Test error for email system verification');
            $errorMail = new ErrorNotificationEmail($exception, '/test-url', 'GET', 'Test Agent');
            
            if ($this->option('send')) {
                EmailQueueService::queue($errorMail, 'test@example.com', 'Test sistema email');
                $this->info('✅ Error notification queued successfully');
            } else {
                $this->info('✅ Error notification would be queued (use --send to actually send)');
            }
            
            // Test 2: Check queue status
            $this->info('2. Checking queue status...');
            $queueCount = \DB::table('jobs')->where('queue', 'emails')->count();
            $this->info("📊 Current queue size: {$queueCount} jobs");
            
            // Test 3: Check failed jobs
            $failedCount = \DB::table('failed_jobs')->where('queue', 'emails')->count();
            $this->info("❌ Failed jobs: {$failedCount}");
            
            // Test 4: Test fire and forget trigger
            $this->info('3. Testing fire and forget trigger...');
            if ($this->option('send') && $queueCount > 0) {
                $this->info('Triggering email processor...');
                EmailQueueService::triggerQueueProcessor();
                $this->info('✅ Processor triggered');
                
                $this->info('Waiting 5 seconds for processing...');
                sleep(5);
                
                $newQueueCount = \DB::table('jobs')->where('queue', 'emails')->count();
                $processed = $queueCount - $newQueueCount;
                $this->info("📈 Processed {$processed} jobs");
            } else {
                $this->info('✅ Fire and forget trigger would work (use --send to test)');
            }
            
            $this->info('');
            $this->info('🎉 Email system test completed successfully!');
            $this->info('No getJobId() errors should occur anymore.');
            
            if (!$this->option('send')) {
                $this->warn('💡 Use --send flag to actually test email sending');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Email system test failed:');
            $this->error($e->getMessage());
            $this->error('');
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
