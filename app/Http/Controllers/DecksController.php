<?php

namespace App\Http\Controllers;

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
            //$cards = 
        }
    }
}