<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

/**
 * Mailable class for sending new cards notification emails
 * Classe Mailable per inviare email di notifica nuove carte
 *
 * This email is sent to all registered users when new cards are imported
 * into the system, providing them with a list of newly available cards.
 */
class NewCardsEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $cards;

    /**
     * Create a new message instance
     * Crea una nuova istanza del messaggio
     *
     * @param array $cards Array of card data with links
     */
    public function __construct($cards)
    {
        $this->cards = $cards;
    }

    /**
     * Get the message envelope with subject and sender information
     * Ottiene la busta del messaggio con oggetto e informazioni mittente
     *
     * @return Envelope The email envelope configuration
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nuove carte disponibili',
        );
    }

    /**
     * Get the message content definition with view and data
     * Ottiene la definizione del contenuto del messaggio con vista e dati
     *
     * @return Content The email content configuration
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-cards',
            with: [
                'cards' => $this->cards,
            ]
        );
    }

    /**
     * Get the attachments for the message (none for this email type)
     * Ottiene gli allegati per il messaggio (nessuno per questo tipo di email)
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment> Empty array as no attachments
     */
    public function attachments(): array
    {
        return [];
    }
}
