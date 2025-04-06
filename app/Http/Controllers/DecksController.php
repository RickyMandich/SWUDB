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
        }
        $stateDeck = $decks;
        $decksPublic = Deck::where("public", 1)->get();
        foreach($decksPublic as $deck){
            array_push($decks, $deck);
        }
        $result = [];
        foreach($decks as $deck){
            $cards = Composition::where("idMazzo", $deck->id)->get();
            foreach($cards as $card){
                $card = Card::where("espansione", $card->espansione)->where("numero", $card->numero)->first()->toArray();
                // return $card;
                array_push($result, $card);
            }
        }
        return view("mazzi.index", ["result" => $result, "decks" => $stateDeck]);
    }
}