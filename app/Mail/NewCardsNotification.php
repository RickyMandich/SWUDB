<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notification for new cards added to the database
 * Notifica email per nuove carte aggiunte al database
 */
class NewCardsNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $newCards;

    /**
     * Create a new message instance.
     * Crea una nuova istanza del messaggio
     *
     * @param array $newCards Array of newly added cards
     */
    public function __construct($newCards)
    {
        $this->newCards = $newCards;
    }

    /**
     * Get the message envelope.
     * Ottiene l'envelope del messaggio
     */
    public function envelope(): Envelope
    {
        $count = count($this->newCards);
        return new Envelope(
            subject: "SWUDB - {$count} nuove carte aggiunte!",
        );
    }

    /**
     * Get the message content definition.
     * Ottiene la definizione del contenuto del messaggio
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-cards-notification',
            with: [
                'newCards' => $this->newCards,
                'count' => count($this->newCards),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     * Ottiene gli allegati per il messaggio
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
