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
        echo "prima dall'if\n";
        if(Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()->isEmpty()){
            echo "dentro l'if ".$event->card["snippet"]."\n";
            Card::insert($event->card);
            $card = Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()[0];
            // MessageCreated::dispatch("ho inserito {$card["snippet"]}");
        }
        echo "dopo l'if\n";
    }
}