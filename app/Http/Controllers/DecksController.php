<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Composition;
use App\Models\Deck;
use App\Models\User;

use DB;

use Illuminate\Database\Query\JoinClause;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

class DecksController extends Controller{
    public function index(){
        $decks = [];
        if(auth()->check()){
            $decksUser = Deck::where("codUtente", auth()->user()->id)->get();
            foreach($decksUser as $deck){
                $deck->utente = User::where("id", $deck->codUtente)->first()->name;
                $decks[$deck->id] = $deck;
            }
        }
        $decksPublic = Deck::where("public", 1)->where("codUtente", "!=", Auth::user()!= null ? Auth::user()->id : -1)->orderBy("codUtente")->get();
        foreach($decksPublic as $deck){
            $deck->utente = User::where("id", $deck->codUtente)->first()->name;
            $deck->dirtyName = "$deck->nome di $deck->utente";
            $decks[$deck->id] = $deck;
        }
        return view("mazzi.index", ["decks" => $decks]);
    }

    public function show($user, $deck){
        if(User::where("name", $user)->first() == null){
            return view("errors.406");
        }else if(Deck::where("nome", str_replace("+", " ", $deck))->first() == null){
            return view("errors.405");
        }else{
            $proprietario = Auth::check() ? Auth()->user()->id == User::where("name", $user)->first()->id:false;
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
            $copie = 0;
            foreach($cards as $card){
                $card->snippet = "$card->espansione-$card->numero - ".$card->nome.(strlen($card->titolo) > 0 ? ", ". strtoupper($card->titolo) : "");
                $copie += $card->copie;
            }
            $carte = Card::select("espansione", "numero", "nome", "titolo", "maxCopie")->get();
            return view("mazzi.show", [
                "nome" => $mazzo->nome,
                "mazzo" => $cards,
                "user" => $user,
                "proprietario" => $proprietario,
                "deck" => $deck,
                "carte" => $carte,
                "size" => $copie,
            ]);
        }
    }

    public function store(Request $request, $user, $deck){
        if(User::where("name", $user)->first() == null){
            return view("errors.406");
        }else if(Deck::where("nome", str_replace("+", " ", $deck))->first() == null){
            return view("errors.405");
        }else if($request->input("carte") != null){
            $mazzo = Deck::where("nome", str_replace("+", " ", $deck))
                        ->where("codUtente", 
                            User::where("name", $user)
                            ->first()
                            ->id)
                        ->first();
            foreach($request->input("carte") as $card => $value){
                try{
                    $value = explode("-", $value);
                    $card = explode("-", $card);
                    $operazione = $value[0];
                    $copie = $value[1];
                    $espansione = $card[0];
                    $numero = $card[1];
                    $composizione = Composition::where("idMazzo", $mazzo->id)
                    ->where("espansione", $espansione)
                    ->where("numero", $numero)
                    ->first();
                    $vars = [
                        "operazione" => $operazione,
                        "copie" => $copie,
                        "espansione" => $espansione,
                        "numero" => $numero,
                        "composizione" => $composizione
                    ];
                    if($operazione == "A"){
                        if($composizione == null){
                            $vars["if"] = "addToNull";
                            $composizione = new Composition();
                            $composizione->idMazzo = $mazzo->id;
                            $composizione->espansione = $espansione;
                            $composizione->numero = $numero;
                            $composizione->copie = $copie;
                            $composizione->id = $mazzo->id."-".$espansione."-".$numero;
                            $composizione->save();
                        }else{
                            $vars["if"] = "addToValue";
                            $composizione->copie += $copie;
                            $composizione->save();
                        }
                    }else if($operazione == "R"){
                        if($composizione == null){
                            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("warning", "Non puoi rimuovere una carta che non hai nel mazzo");
                        }else{
                            if($composizione->copie - $copie <= 0){
                                $vars["if"] = "removeFromNull";
                                $composizione->delete();
                            }else{
                                $vars["if"] = "removeFromValue";
                                $composizione->copie -= $copie;
                                $composizione->save();
                            }
                        }
                    }
                }catch(\Exception $e){
                    $vars["error"] = "Errore durante il salvataggio del mazzo: ".$e->getMessage();
                    return $vars;
                }finally{
                    $vars["msg"] = "sono arrivato alla fine";
                }
            };
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("success", "Mazzo salvato con successo");
        }else{
            return redirect()->route("mazzo", ["user" => $user, "mazzo" => $deck])->with("warning", "Non hai aggiunto o rimosso nessuna carta");
        }
    }

    public function create(Request $request){
        if(auth()->check()){
                if(Deck::where("nome", $request->input("nome"))->where("codUtente", Auth::user()->id)->first() == null){
                $mazzo = new Deck();
                $mazzo->nome = $request->input("nome");
                $mazzo->public = $request->input("public") == true;
                $mazzo->codUtente = Auth::user()->id;
                $mazzo->save();
                return redirect()->route("mazzo", ["user" => Auth::user()->name, "mazzo" => str_replace(" ", "+", $mazzo->nome)])->with("success", "Mazzo creato con successo");
            }else{
                return redirect()->route("mazzo", ["user" => Auth::user()->name, "mazzo" => str_replace(" ", "+", $request->input("nome"))])->with("warning", "Questo mazzo esiste già");
            }
        }
        return redirect()->route("login")->with("warning", "Devi essere loggato per visualizzare questa pagina");
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