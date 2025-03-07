<?php

namespace App\Listeners;

use App\Events\SentArtisanCommand;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;

class ExecuteArtisanCommand implements ShouldQueue{
    /**
     * Handle the event.
     */
    public function handle(SentArtisanCommand $event): void{
        Artisan::call($event->command);
    }
}
