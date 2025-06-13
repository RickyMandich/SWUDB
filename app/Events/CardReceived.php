<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event fired when a new card is received/imported into the system
 * Evento lanciato quando una nuova carta viene ricevuta/importata nel sistema
 *
 * This event is triggered during the card import process and can be
 * listened to for additional processing like adding to collections.
 */
class CardReceived{
    use Dispatchable;

    /**
     * Create a new event instance
     * Crea una nuova istanza dell'evento
     *
     * @param mixed $card The card data that was received
     */
    public function __construct(public $card){}
}
