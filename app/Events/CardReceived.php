<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CardReceived{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public $card){}
}
