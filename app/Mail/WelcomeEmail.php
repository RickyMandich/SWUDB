<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Benventuto $this->name",
            from: new Address('noreply@mailing.unlimitedDB.net', 'UnlimitedDB'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: ['name' => $this->name],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}