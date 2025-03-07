<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SentArtisanCommand{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public string $command){}
}
