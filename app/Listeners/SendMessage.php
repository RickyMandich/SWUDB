<?php

namespace App\Listeners;

use App\Events\MessageCreated;

use App\Http\Controllers\JobController;

use Illuminate\Support\Facades\Http;

class SendMessage{

    /**
     * Handle the event.
     */
    public function handle(MessageCreated $event): void{
        file_put_contents(__DIR__ . '/debug-job.log', "sending message: $event->message alle " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        JobController::fireAndForget(route("job.sendMessage"), ["message" => $event->message, "token" => env("JOB_TOKEN")]);
    }
}
