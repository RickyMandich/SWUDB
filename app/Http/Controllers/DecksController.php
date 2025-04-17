<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Composition;
use App\Models\Deck;

use Illuminate\Http\Request;

class DecksController extends Controller{
    public function index(){
        $decks = [];
        if(auth()->check()){
            $decksUser = Deck::where("codUtente", auth()->user()->id)->get();
            foreach($decksUser as $deck){
                array_push($decks, $deck);
            }
            $decksPublic = Deck::where("public", 1)->get();
            foreach($decksPublic as $deck){
                array_push($decks, $deck);
            }
            $result = [];
            foreach($decks as $deck){
                $cards = Composition::where("idMazzo", $deck->id)->get();
                $deckCards = [];
                foreach($cards as $card){
                    $card = Card::select(['aspettoPrimario', 'aspettoSecondario', 'unica', 'tipo', 'rarita', 'costo', 'vita', 'potenza', 'descrizione', 'tratti', 'arena', 'artista', 'nome', 'titolo', 'espansione', 'numero'])->where("espansione", $card->espansione)->where("numero", $card->numero)->first()->toArray();
                    unset($card["nome"]);
                    unset($card["titolo"]);
                    unset($card["espansione"]);
                    unset($card["numero"]);
                    unset($card["id"]);
                    foreach($card as $key => $value){
                        if($key != "snippet"){
                            unset($card[$key]);
                            $card[$key] = $value;
                        }
                    }
                    array_push($deckCards, $card);
                }
                $result[$deck->nome] = $deckCards;
            }
            return view("mazzi.index", ["result" => $result]);
        }
        return redirect()->route("login")->with("warning", "Devi essere loggato per visualizzare questa pagina");
    }
}