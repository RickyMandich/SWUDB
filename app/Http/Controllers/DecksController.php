<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Composition;
use App\Models\Deck;

use App\Models\User;
use DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
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
            return view("mazzi.index", ["decks" => $decks]);
        }
        return redirect()->route("login")->with("warning", "Devi essere loggato per visualizzare questa pagina");
    }

    public function show($user, $deck){
        if(User::where("name", $user)->first() == null){
            return view("errors.406");
        }else if(Deck::where("nome", str_replace("+", " ", $deck))->first() == null){
            return view("errors.405");
        }else{
            $mazzo = Deck::where("nome", str_replace("+", " ", $deck))
                        ->where("codUtente", 
                            User::where("name", $user)
                            ->first()
                            ->id)
                        ->first();
            $cards = DB::table('compositions')
                ->leftJoin('cards', function (JoinClause $join){
                    $join->on('compositions.espansione', '=', 'cards.espansione')
                        ->on('compositions.numero', '=', 'cards.numero');
                })
                ->select('cards.*', 'compositions.copie')
                ->where('compositions.idMazzo', $mazzo->id)
                ->get();
            foreach($cards as $card){
                $card->snippet = "$card->espansione-$card->numero - ".$card->nome.(strlen($card->titolo) > 0 ? ", ". strtoupper($card->titolo) : "");
            }
            $carte = Card::get();
            return view("mazzi.show", [
                "nome" => $mazzo->nome,
                "mazzo" => $cards,
                "user" => $user,
                "deck" => $deck,
                "carte" => $carte
            ]);
        }
    }

    public function api($user, $nome, $public){
        return Deck::where("nome", "like", "%$nome%")
                ->where("codUtente", 
                    User::where("name", "like", "%$user%")
                    ->first()
                    ->id)
                ->where("public", $public)
                ->get();
    }
}