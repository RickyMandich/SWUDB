<?php

namespace App\Listeners;

use App\Events\CardReceived;
use App\Http\Controllers\JobController;

class AddCard{
    /**
     * Handle the event.
     */
    public function handle(CardReceived $event): void{
        JobController::fireAndForgetGet(route("job.addCard"), ["card" => $event->card, "token" => env("JOB_TOKEN")]);
    }
}