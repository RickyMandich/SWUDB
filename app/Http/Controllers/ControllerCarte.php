<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ControllerCarte extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        $get = $request->all();
        if(!isset($get["nome"])){
            $get["nome"] = "";
        }
        $model = DB::table("cards")->whereLike("nome", "%".$get["nome"]."%")->get();
        $empty = $model->isEmpty();
        $resultHeader = DB::select("DESCRIBE cards");
        $header = [];
        foreach($resultHeader as $key => $value){
            $header[] = $value->Field;
        }
        return view('carte/index', ["content" => $model, "empty" => $empty, "nome" => $get["nome"], "header" => $header]);
    }

    function getUscita($espansione){
        switch($espansione){
            case "CE24":
                return "2024 08 01";
            case "SOR":
                return "2024 03 08";
            case "SHD":
                return "2024 07 12";
            case "TWI":
                return "2024 11 05";
            case "JTL":
                return "2025 03 14";
            case "GGTS":
                return "2025 03 15";
            default:
                return "2024 03 08";
        }
    }

    public function create(){
        $url = 'http://swudb.altervista.org/collezione.json';
        $json = file_get_contents($url);
        $data = json_decode($json, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $result = [];
            foreach ($data as &$card) {
                $card["tratti"] = implode(" * ", $card["tratti"]);
                $card["uscita"] = $this->getUscita($card["espansione"]);
                if(!isset($card["descrizione"])){
                    $card["descrizione"] = "";
                }
                if(!isset($card["titolo"])){
                    $card["titolo"] = "";
                }
                if(DB::table('cards')->where('espansione', "=", $card["espansione"])->where('numero', "=", $card["numero"])->get()->isEmpty()){
                    DB::table('cards')->insert($card);
                    array_push($result, $card);
                }
            }
        }

        return view('carte/update', ["result" => $result]);
    }
}
