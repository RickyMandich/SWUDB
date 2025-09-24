<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailQueueService;
use App\Mail\EmailVerificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Command to test the email queue system
 * Comando per testare il sistema di coda email
 *
 * This command allows testing the email queue functionality
 * without affecting real users or sending actual emails.
 */
class TestEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-queue 
                            {--count=5 : Number of test emails to queue}
                            {--delay=0 : Delay in seconds between emails}
                            {--to= : Specific email address to send to}
                            {--dry-run : Show what would be done without actually queuing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the email queue system with sample emails';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        $delay = (int) $this->option('delay');
        $specificEmail = $this->option('to');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No emails will be queued');
        }
        
        $this->info("Testing email queue system...");
        $this->line("Count: {$count}");
        $this->line("Delay: {$delay} seconds");
        $this->line("Target: " . ($specificEmail ?: 'Test users'));
        $this->line('');
        
        if ($specificEmail) {
            $this->testSpecificEmail($specificEmail, $count, $delay, $dryRun);
        } else {
            $this->testWithTestUsers($count, $delay, $dryRun);
        }
        
        if (!$dryRun) {
            $this->info('');
            $this->info('Test emails queued successfully!');
            $this->line('Use "php artisan email:queue-status" to check queue status');
            $this->line('Use "php artisan email:process-queue" to process the queue');
        }
        
        return 0;
    }
    
    /**
     * Test with a specific email address
     * Testa con un indirizzo email specifico
     *
     * @param string $email
     * @param int $count
     * @param int $delay
     * @param bool $dryRun
     */
    protected function testSpecificEmail(string $email, int $count, int $delay, bool $dryRun)
    {
        $this->info("Sending {$count} test emails to: {$email}");
        
        for ($i = 1; $i <= $count; $i++) {
            $testUser = new User([
                'name' => "Test User {$i}",
                'email' => $email
            ]);
            
            $mailable = new EmailVerificationMail($testUser, 'http://test.com/verify/' . $i);
            
            if ($dryRun) {
                $this->line("Would queue email {$i} to {$email}");
            } else {
                EmailQueueService::queue(
                    $mailable,
                    $email,
                    "Test email {$i} of {$count}",
                    $delay * ($i - 1)
                );
                
                $this->line("Queued email {$i} to {$email}");
            }
        }
    }
    
    /**
     * Test with existing test users or create them
     * Testa con utenti di test esistenti o li crea
     *
     * @param int $count
     * @param int $delay
     * @param bool $dryRun
     */
    protected function testWithTestUsers(int $count, int $delay, bool $dryRun)
    {
        // Look for existing test users
        $testUsers = User::where('email', 'like', 'test%@unlimiteddb.test')->limit($count)->get();
        
        if ($testUsers->count() < $count) {
            $needed = $count - $testUsers->count();
            $this->info("Creating {$needed} test users...");
            
            for ($i = $testUsers->count() + 1; $i <= $count; $i++) {
                if (!$dryRun) {
                    $testUser = User::create([
                        'name' => "Test User {$i}",
                        'email' => "test{$i}@unlimiteddb.test",
                        'password' => bcrypt('password'),
                        'email_verified_at' => null,
                        'admin' => false
                    ]);
                    $testUsers->push($testUser);
                } else {
                    $this->line("Would create test user: test{$i}@unlimiteddb.test");
                }
            }
        }
        
        if ($dryRun) {
            $this->info("Would queue {$count} emails to test users");
            return;
        }
        
        $this->info("Queuing emails to {$count} test users...");
        
        foreach ($testUsers as $index => $user) {
            $mailable = new EmailVerificationMail($user, 'http://test.com/verify/' . $user->id);
            
            EmailQueueService::queue(
                $mailable,
                $user->email,
                "Test email " . ($index + 1) . " of {$count}",
                $delay * $index
            );
            
            $this->line("Queued email to {$user->email}");
        }
    }
    
    /**
     * Clean up test users
     * Pulisce gli utenti di test
     */
    protected function cleanupTestUsers()
    {
        if ($this->confirm('Clean up test users?')) {
            $deleted = User::where('email', 'like', 'test%@unlimiteddb.test')->delete();
            $this->info("Deleted {$deleted} test users");
        }
    }
}
