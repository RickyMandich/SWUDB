<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event fired when a threaded message is created or updated
 * Evento lanciato quando viene creato o aggiornato un messaggio in thread
 *
 * This event allows messages within the same execution thread to replace
 * previous messages instead of accumulating multiple notifications.
 * Similar to progress indicators where each update replaces the previous one.
 */
class ThreadMessageCreated{
    use Dispatchable;

    /**
     * Create a new threaded message event instance
     * Crea una nuova istanza dell'evento messaggio in thread
     *
     * @param string $threadId Unique identifier for the execution thread
     * @param string $message The message content to be logged or displayed
     * @param bool $isComplete Whether this message marks the thread as complete
     */
    public function __construct(
        public string $threadId,
        public string $message,
        public bool $isComplete = false
    ){}
}
