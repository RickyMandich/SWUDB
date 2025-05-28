<?php

namespace App\Listeners;

use App\Events\CardReceived;
use App\Events\MessageCreated;

use App\Models\Card;

class AddCard{
    /**
     * Handle the event.
     */
    public function handle(CardReceived $event): void{
        JobController::fireAndForget(route("job.addCard"), ["card" => $event->card, "token" => env("JOB_TOKEN")]);
    }
}