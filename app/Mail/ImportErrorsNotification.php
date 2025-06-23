<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notification for import errors and issues (admin only)
 * Notifica email per errori e problemi di importazione (solo admin)
 */
class ImportErrorsNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $stats;

    /**
     * Create a new message instance.
     * Crea una nuova istanza del messaggio
     *
     * @param array $stats Import statistics including errors
     */
    public function __construct($stats)
    {
        $this->stats = $stats;
    }

    /**
     * Get the message envelope.
     * Ottiene l'envelope del messaggio
     */
    public function envelope(): Envelope
    {
        $errorCount = $this->stats['cards_failed'] ?? 0;
        $duplicateCount = $this->stats['cards_duplicate'] ?? 0;
        
        return new Envelope(
            subject: "SWUDB Admin - Rapporto errori importazione ({$errorCount} errori, {$duplicateCount} duplicate)",
        );
    }

    /**
     * Get the message content definition.
     * Ottiene la definizione del contenuto del messaggio
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.import-errors-notification',
            with: [
                'stats' => $this->stats,
                'errors' => $this->stats['errors'] ?? [],
                'errorCount' => $this->stats['cards_failed'] ?? 0,
                'duplicateCount' => $this->stats['cards_duplicate'] ?? 0,
                'totalProcessed' => $this->stats['new_cards_found'] ?? 0,
                'successCount' => $this->stats['cards_added'] ?? 0,
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
