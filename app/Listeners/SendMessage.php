<?php

namespace App\Listeners;

use App\Events\MessageCreated;

use App\Http\Controllers\JobController;


class SendMessage{

    /**
     * Handle the event.
     */
    public function handle(MessageCreated $event): void{
        file_put_contents(__DIR__ . "/debug-sendMessage.log", route("job.sendMessage") . "\n\n", FILE_APPEND);
        JobController::fireAndForgetGet(route("job.sendMessage"), ["message" => $event->message, "token" => env("JOB_TOKEN")]);
    }
}
