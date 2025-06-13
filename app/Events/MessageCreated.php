<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event fired when a system message is created for logging or notification
 * Evento lanciato quando viene creato un messaggio di sistema per logging o notifica
 *
 * This event is used throughout the application to dispatch status messages,
 * particularly during import processes and background operations.
 */
class MessageCreated{
    use Dispatchable;

    /**
     * Create a new event instance
     * Crea una nuova istanza dell'evento
     *
     * @param string $message The message content to be logged or displayed
     */
    public function __construct(public $message){}
}
