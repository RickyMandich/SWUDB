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
            $last = "prima di new";
            try{
                $card = new Card();
                $last = "new-cid";
                $card->cid = $event->card["cid"];
                $last = "cid-espansione";
                $card->espansione = $event->card["espansione"];
                $last = "espansione-numero";
                $card->numero = $event->card["numero"];
                $last = "numero-aspettoPrimario";
                $card->aspettoPrimario = $event->card["aspettoPrimario"];
                $last = "aspettoPrimario-aspettoSecondario";
                $card->aspettoSecondario = $event->card["aspettoSecondario"];
                $last = "aspettoSecondario-unica";
                $card->unica = $event->card["unica"];
                $last = "unica-nome";
                $card->nome = $event->card["nome"];
                $last = "nome-titolo";
                $card->titolo = $event->card["titolo"];
                $last = "titolo-tipo";
                $card->tipo = $event->card["tipo"];
                $last = "tipo-rarita";
                $card->rarita = $event->card["rarita"];
                $last = "rarita-costo";
                $card->costo = $event->card["costo"];
                $last = "costo-vita";
                $card->vita = $event->card["vita"];
                $last = "vita-potenza"; 
                $card->potenza = $event->card["potenza"];
                $last = "potenza-descrizione";
                $card->descrizione = $event->card["descrizione"];
                $last = "descrizione-tratti";
                $card->tratti = $event->card["tratti"];
                $last = "tratti-arena";
                $card->arena = $event->card["arena"];
                $last = "arena-artista";
                $card->artista = $event->card["artista"];
                $last = "artista-uscita";
                $card->uscita = $this->getUscita($event->card["espansione"]);
                $last = "uscita-frontArt";
                $card->frontArt = $event->card["frontArt"];
                $last = "frontArt-backArt";
                $card->backArt = $event->card["backArt"];
                $last = "backArt-maxCopie";
                $card->maxCopie = 3;
                if(str_contains(strtolower($card->tipo), 'leader')){
                    $card->maxCopie = 1;
                }
                if(str_contains(strtolower($card->tipo), 'base')){
                    $card->maxCopie = 1;
                }
                if(strtoupper($card->espansione) == 'JTL' && $card->numero == 256){
                    $card->maxCopie = 15;
                }
                if(str_contains(strtolower($card->tipo), "segnalino")){
                    $card->maxCopie = 0;
                }
                $last = "maxCopie-save";
                unset($card->creazione);
                $card->save();
            }catch(\Exception $e){
                echo "eccezione ".$e->getMessage()."\n";
                echo $last;
                MessageCreated::dispatch("eccezione ".$e->getMessage());
            }
            try{
                $result = Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()->get(0);
                echo $result->snippet."\n";
                // MessageCreated::dispatch($result->snippet);
            }catch(\Exception $e){
                echo "eccezione ".$e->getMessage()."\n";
                MessageCreated::dispatch("eccezione ".$e->getMessage());
            }
        }
    }

    function getUscita($espansione){
        switch($espansione){
            case "CE24":
                return "2024-08-01";
            case "SOR":
                return "2024-03-08";
            case "SHD":
                return "2024-07-12";
            case "TWI":
                return "2024-11-05";
            case "JTL":
                return "2025-03-14";
            case "LEG":
                return "2025-07-11";
            case "GGTS":
                return "2025-03-15";
            default:
                return "2024-03-08";
        }
    }
}