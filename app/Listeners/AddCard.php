<?php

namespace App\Listeners;

use App\Events\CardReceived;
use App\Http\Controllers\JobController;

/**
 * Listener for CardReceived event that triggers card addition job
 * Listener per l'evento CardReceived che attiva il job di aggiunta carta
 *
 * This listener responds to CardReceived events by dispatching a background
 * job to add the card to the database through the JobController.
 */
class AddCard{
    /**
     * Handle the CardReceived event by dispatching an add card job
     * Gestisce l'evento CardReceived inviando un job di aggiunta carta
     *
     * @param CardReceived $event The card received event containing card data
     * @return void
     */
    public function handle(CardReceived $event): void{
        JobController::fireAndForgetGet(route("job.addCard"), ["card" => $event->card, "token" => env("JOB_TOKEN")]);
    }
}