<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable class for sending new expansion notification emails to admins
 * Classe Mailable per inviare email di notifica nuove espansioni agli admin
 *
 * This email is sent to all admin users when a new expansion is created
 * in the system, providing them with expansion details and management links.
 */
class NewExpansionEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $expansion;

    /**
     * Create a new message instance
     * Crea una nuova istanza del messaggio
     *
     * @param array $expansion Array of expansion data with links
     */
    public function __construct($expansion)
    {
        $this->expansion = $expansion;
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
            subject: 'SWUDB - Nuova espansione rilevata: ' . ($this->expansion['espansione'] ?? 'N/A'),
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
            view: 'emails.new-expansion',
            with: [
                'expansion' => $this->expansion,
                'buttonText' => 'Gestisci Espansioni',
                'buttonUrl' => route('admin.expansions')
            ]
        );
    }

    /**
     * Get the attachments for the message
     * Ottiene gli allegati per il messaggio
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
