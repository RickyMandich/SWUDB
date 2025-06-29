<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;

/**
 * Mailable class for sending email verification emails
 * Classe Mailable per inviare email di verifica
 *
 * This email is sent to users to verify their email address
 * with a verification token link.
 */
class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $verificationUrl;

    /**
     * Create a new email verification instance
     * Crea una nuova istanza dell'email di verifica
     *
     * @param User $user The user to verify
     * @param string $verificationUrl The verification URL with token
     */
    public function __construct(User $user, $verificationUrl)
    {
        $this->user = $user;
        $this->verificationUrl = $verificationUrl;
    }

    /**
     * Get the message envelope with verification subject
     * Ottiene la busta del messaggio con oggetto di verifica
     *
     * @return Envelope The email envelope with verification subject
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Verifica il tuo indirizzo email - UnlimitedDB",
        );
    }

    /**
     * Get the message content definition with verification template
     * Ottiene la definizione del contenuto con template di verifica
     *
     * @return Content The email content configuration
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->verificationUrl
            ],
        );
    }

    /**
     * Get the attachments for the message (none for verification emails)
     * Ottiene gli allegati per il messaggio (nessuno per email di verifica)
     *
     * @return array Empty array as no attachments needed
     */
    public function attachments(): array
    {
        return [];
    }
}
