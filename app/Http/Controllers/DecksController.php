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
        /*/return/*/$mazzo =/**/ Deck::where("nome", str_replace("+", " ", $deck))
                    ->where("codUtente", 
                        User::where("name", $user)
                        ->first()
                        ->id)
                    ->first();
        /*/return/*/$cards =/**/ DB::table('compositions')
            ->leftJoin('cards', function (JoinClause $join){
                $join->on('compositions.espansione', '=', 'cards.espansione')
                    ->on('compositions.numero', '=', 'cards.numero');
            })
            ->select('cards.*', 'compositions.copie')
            ->where('compositions.idMazzo', $mazzo->id)
            ->get();
        $carte = new Collection();
        foreach($cards as $card){
            foreach($card as $key => $value){
                $c[$key] = $value;
            }
            $carte->push(new Card($c));
        }
        return view("mazzi.show", ["nome"=>$mazzo->nome, "mazzo" => $carte]);
    }

    public function api($user, $nome, $public){
        return Deck::where("nome", "like", "%$nome%")
                ->where("codUtente", 
                    User::where("name", "like", "%$user%")
                    // ->select("id")
                    ->first()
                    ->id)
                ->where("public", $public)
                ->get();
    }
}