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
        return view('carte', ["model" => $model, "empty" => $empty, "nome" => $get["nome"]]);
    }
}
