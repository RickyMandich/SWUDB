<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Composition;
use Illuminate\Http\Request;

use App\Models\Deck;

class DecksController extends Controller{
    public function index(){
        $decks = Deck::where("public", 1)->get();
        if(auth()){
            $decksUser = Deck::where("codUtente", auth()->user()->id)->get();
            foreach($decksUser as $deck){
                $decks->push($deck);
            }
        }
        $result = [];
        foreach($decks as $deck){
            $cards = Composition::where("idMazzo", $deck->id)->get();
            foreach($cards as $card){
                array_push($result, Card::where("espansione", $card->espansione)->where("numero", $card->numero)->first());
            }
        }
        return view("mazzi.index", ["result" => $result]);
    }
}