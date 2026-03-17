<?php

namespace App\Mail;

use App\Models\TestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $testResult;

    /**
     * Create a new message instance.
     */
    public function __construct(TestResult $testResult)
    {
        $this->testResult = $testResult;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('🚨 TEST FALLITO: ' . $this->testResult->test_name)
                    ->markdown('emails.tests.failed');
    }
}
