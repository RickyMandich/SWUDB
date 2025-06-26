<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Mailable class for sending error notification emails to administrators
 * Classe Mailable per inviare email di notifica errori agli amministratori
 *
 * This email is sent to all admin users when an error occurs on the platform
 * to provide immediate notification of system issues.
 */
class ErrorNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $exception;
    public $errorMessage;
    public $errorFile;
    public $errorLine;
    public $requestUrl;
    public $requestMethod;
    public $userAgent;
    public $timestamp;
    public $systemError;

    /**
     * Create a new error notification email instance
     * Crea una nuova istanza dell'email di notifica errore
     *
     * @param Throwable $exception The exception that occurred
     * @param string|null $requestUrl The URL where the error occurred
     * @param string|null $requestMethod The HTTP method used
     * @param string|null $userAgent The user agent string
     * @param \App\Models\SystemError|null $systemError The saved system error record
     */
    public function __construct(Throwable $exception, ?string $requestUrl = null, ?string $requestMethod = null, ?string $userAgent = null, $systemError = null)
    {
        $this->exception = $exception;
        $this->errorMessage = $exception->getMessage();
        $this->errorFile = $exception->getFile();
        $this->errorLine = $exception->getLine();
        $this->requestUrl = $requestUrl;
        $this->requestMethod = $requestMethod;
        $this->userAgent = $userAgent;
        $this->timestamp = now()->format('d/m/Y H:i:s');
        $this->systemError = $systemError;
    }

    /**
     * Get the message envelope with error subject
     * Ottiene la busta del messaggio con oggetto errore
     *
     * @return Envelope The email envelope with error notification subject
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SWUDB] Errore Sistema - ' . class_basename($this->exception),
        );
    }

    /**
     * Get the message content definition with error template
     * Ottiene la definizione del contenuto con template errore
     *
     * @return Content The email content configuration
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.error-notification',
            with: [
                'errorMessage' => $this->errorMessage,
                'errorFile' => $this->errorFile,
                'errorLine' => $this->errorLine,
                'requestUrl' => $this->requestUrl,
                'requestMethod' => $this->requestMethod,
                'userAgent' => $this->userAgent,
                'timestamp' => $this->timestamp,
                'exceptionClass' => get_class($this->exception),
                'systemError' => $this->systemError,
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
