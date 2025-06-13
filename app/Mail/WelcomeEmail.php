<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

/**
 * Mailable class for sending welcome emails to new users
 * Classe Mailable per inviare email di benvenuto ai nuovi utenti
 *
 * This email is sent to users upon registration to welcome them
 * to the Star Wars Unlimited database platform.
 */
class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    /**
     * Create a new welcome email instance
     * Crea una nuova istanza dell'email di benvenuto
     *
     * @param string $name The user's name for personalization
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get the message envelope with personalized subject
     * Ottiene la busta del messaggio con oggetto personalizzato
     *
     * @return Envelope The email envelope with welcome subject
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Benvenuto $this->name",
        );
    }

    /**
     * Get the message content definition with welcome template
     * Ottiene la definizione del contenuto con template di benvenuto
     *
     * @return Content The email content configuration
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: ['name' => $this->name],
        );
    }

    /**
     * Get the attachments for the message (none for welcome emails)
     * Ottiene gli allegati per il messaggio (nessuno per email di benvenuto)
     *
     * @return array Empty array as no attachments needed
     */
    public function attachments(): array
    {
        return [];
    }
}