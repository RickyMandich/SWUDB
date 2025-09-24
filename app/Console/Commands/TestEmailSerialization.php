<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendQueuedEmail;
use App\Mail\ErrorNotificationEmail;
use App\Mail\EmailVerificationMail;
use App\Models\User;

/**
 * Command to test email serialization
 * Comando per testare la serializzazione email
 */
class TestEmailSerialization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-serialization';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email job serialization to prevent PDO errors';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing Email Job Serialization');
        $this->info('================================');
        
        try {
            // Test ErrorNotificationEmail
            $this->info('Testing ErrorNotificationEmail...');
            $exception = new \Exception('Test error message');
            $errorMail = new ErrorNotificationEmail($exception, '/test-url', 'GET', 'Test Agent');
            $errorJob = new SendQueuedEmail($errorMail, 'test@example.com', 'Test error context');
            
            // Try to serialize the job
            $serialized = serialize($errorJob);
            $this->info('✅ ErrorNotificationEmail serialization successful');
            
            // Test deserialization
            $unserialized = unserialize($serialized);
            $this->info('✅ ErrorNotificationEmail deserialization successful');
            
            // Test EmailVerificationMail
            $this->info('Testing EmailVerificationMail...');
            $user = User::first();
            if ($user) {
                $verificationMail = new EmailVerificationMail($user, 'http://test.com/verify');
                $verificationJob = new SendQueuedEmail($verificationMail, $user->email, 'Test verification context');
                
                // Try to serialize the job
                $serialized = serialize($verificationJob);
                $this->info('✅ EmailVerificationMail serialization successful');
                
                // Test deserialization
                $unserialized = unserialize($serialized);
                $this->info('✅ EmailVerificationMail deserialization successful');
            } else {
                $this->warn('⚠️ No users found, skipping EmailVerificationMail test');
            }
            
            $this->info('');
            $this->info('🎉 All serialization tests passed!');
            $this->info('The PDO serialization issue should be resolved.');
            
        } catch (\Exception $e) {
            $this->error('❌ Serialization test failed:');
            $this->error($e->getMessage());
            $this->error('');
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
