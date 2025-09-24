<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Services\EmailQueueService;
use App\Jobs\SendQueuedEmail;
use App\Mail\EmailVerificationMail;
use App\Models\User;

/**
 * Test suite for the email queue system
 * Suite di test per il sistema di coda email
 */
class EmailQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake the queue and mail for testing
        Queue::fake();
        Mail::fake();
    }

    /**
     * Test that emails are queued instead of sent immediately
     * Testa che le email vengano accodate invece di inviate immediatamente
     */
    public function test_emails_are_queued()
    {
        $user = User::factory()->create();
        $mailable = new EmailVerificationMail($user, 'http://test.com/verify');
        
        EmailQueueService::queue($mailable, $user->email, 'Test context');
        
        // Assert that a job was pushed to the queue
        Queue::assertPushed(SendQueuedEmail::class, function ($job) use ($user) {
            return $job->to === $user->email;
        });
        
        // Assert that no email was sent immediately
        Mail::assertNothingSent();
    }

    /**
     * Test queuing emails to multiple users
     * Testa l'accodamento di email a più utenti
     */
    public function test_queue_to_multiple_users()
    {
        $users = User::factory()->count(5)->create();
        $mailable = new EmailVerificationMail($users->first(), 'http://test.com/verify');
        
        EmailQueueService::queueToUsers($mailable, $users, 'Bulk test');
        
        // Assert that 5 jobs were pushed
        Queue::assertPushed(SendQueuedEmail::class, 5);
        
        // Assert each user gets a job
        foreach ($users as $user) {
            Queue::assertPushed(SendQueuedEmail::class, function ($job) use ($user) {
                return $job->to === $user->email;
            });
        }
    }

    /**
     * Test queuing emails to admin users
     * Testa l'accodamento di email agli utenti admin
     */
    public function test_queue_to_admins()
    {
        // Create regular users and admin users
        User::factory()->count(3)->create(['admin' => false]);
        $admins = User::factory()->count(2)->create(['admin' => true]);
        
        $mailable = new EmailVerificationMail($admins->first(), 'http://test.com/verify');
        
        EmailQueueService::queueToAdmins($mailable, 'Admin notification');
        
        // Assert that only 2 jobs were pushed (for admins only)
        Queue::assertPushed(SendQueuedEmail::class, 2);
        
        // Assert each admin gets a job
        foreach ($admins as $admin) {
            Queue::assertPushed(SendQueuedEmail::class, function ($job) use ($admin) {
                return $job->to === $admin->email;
            });
        }
    }

    /**
     * Test immediate email sending (fallback)
     * Testa l'invio immediato di email (fallback)
     */
    public function test_immediate_email_sending()
    {
        $user = User::factory()->create();
        $mailable = new EmailVerificationMail($user, 'http://test.com/verify');
        
        $result = EmailQueueService::sendImmediate($mailable, $user->email, 'Immediate test');
        
        // Assert that the method returns true (success)
        $this->assertTrue($result);
        
        // Assert that an email was sent immediately
        Mail::assertSent(EmailVerificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
        
        // Assert that no job was queued
        Queue::assertNothingPushed();
    }

    /**
     * Test fire and forget processor trigger
     * Testa l'attivazione del processore fire and forget
     */
    public function test_processor_trigger()
    {
        $user = User::factory()->create();
        $mailable = new EmailVerificationMail($user, 'http://test.com/verify');

        // Clear any existing processor timestamp
        Cache::forget('email_processor_last_run');

        // Mock the JobController to verify it gets called
        $this->mock(\App\Http\Controllers\JobController::class, function ($mock) {
            $mock->shouldReceive('fireAndForgetGet')
                ->once()
                ->with(
                    route('job.processEmailQueue'),
                    ['token' => env('JOB_TOKEN')]
                );
        });

        // Queue an email - this should trigger the processor
        EmailQueueService::queue($mailable, $user->email, 'Processor trigger test');

        // Verify the job was queued
        Queue::assertPushed(SendQueuedEmail::class);
    }

    /**
     * Test job configuration
     * Testa la configurazione del job
     */
    public function test_job_configuration()
    {
        $user = User::factory()->create();
        $mailable = new EmailVerificationMail($user, 'http://test.com/verify');
        
        $job = new SendQueuedEmail($mailable, $user->email, 'Config test');
        
        // Test job properties
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals([30, 60, 120], $job->backoff);
        $this->assertEquals($user->email, $job->to);
        $this->assertEquals('Config test', $job->logContext);
    }

    /**
     * Test that jobs are assigned to the correct queue
     * Testa che i job vengano assegnati alla coda corretta
     */
    public function test_jobs_assigned_to_emails_queue()
    {
        $user = User::factory()->create();
        $mailable = new EmailVerificationMail($user, 'http://test.com/verify');
        
        EmailQueueService::queue($mailable, $user->email, 'Queue test');
        
        Queue::assertPushedOn('emails', SendQueuedEmail::class);
    }

    /**
     * Test handling of empty user collections
     * Testa la gestione di collezioni utenti vuote
     */
    public function test_empty_user_collection_handling()
    {
        $emptyUsers = collect();
        $mailable = new EmailVerificationMail(User::factory()->make(), 'http://test.com/verify');
        
        EmailQueueService::queueToUsers($mailable, $emptyUsers, 'Empty test');
        
        // No jobs should be pushed for empty collection
        Queue::assertNothingPushed();
    }

    /**
     * Test handling of users without email addresses
     * Testa la gestione di utenti senza indirizzi email
     */
    public function test_users_without_email_handling()
    {
        $usersWithoutEmail = User::factory()->count(3)->create(['email' => null]);
        $usersWithEmail = User::factory()->count(2)->create();
        
        $allUsers = $usersWithoutEmail->concat($usersWithEmail);
        $mailable = new EmailVerificationMail($usersWithEmail->first(), 'http://test.com/verify');
        
        EmailQueueService::queueToUsers($mailable, $allUsers, 'Mixed test');
        
        // Only 2 jobs should be pushed (for users with email)
        Queue::assertPushed(SendQueuedEmail::class, 2);
    }
}
