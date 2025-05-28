<?php

namespace App\Listeners;

use App\Events\MessageCreated;

use App\Http\Controllers\JobController;


class SendMessage{

    /**
     * Handle the event.
     */
    public function handle(MessageCreated $event): void{
        JobController::fireAndForgetGet(route("job.sendMessage"), ["message" => $event->message, "token" => env("JOB_TOKEN")]);
    }
}
