<?php

namespace App\Listeners;

use App\Events\CardReceived;
use App\Events\MessageCreated;

use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Card;

class AddCard implements ShouldQueue{
    /**
     * Handle the event.
     */
    public function handle(CardReceived $event): void{
        if(Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()->isEmpty()){
            Card::insert($event->card);
            $card = Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()[0];
            echo "ho inserito {$card["snippet"]}";
            MessageCreated::dispatch("ho inserito {$card["snippet"]}");
        }
    }
}