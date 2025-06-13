<?php

namespace App\Listeners;

use App\Events\MessageCreated;

use App\Http\Controllers\JobController;


/**
 * Listener for MessageCreated event that handles message processing
 * Listener per l'evento MessageCreated che gestisce l'elaborazione dei messaggi
 *
 * This listener responds to MessageCreated events by logging debug information
 * and dispatching a background job to process the message.
 */
class SendMessage{

    /**
     * Handle the MessageCreated event by logging and dispatching message job
     * Gestisce l'evento MessageCreated registrando e inviando il job del messaggio
     *
     * @param MessageCreated $event The message created event containing message data
     * @return void
     */
    public function handle(MessageCreated $event): void{
        file_put_contents(__DIR__ . "/debug-sendMessage.log", route("job.sendMessage") . "\n\n", FILE_APPEND);
        JobController::fireAndForgetGet(route("job.sendMessage"), ["message" => $event->message, "token" => env("JOB_TOKEN")]);
    }
}
