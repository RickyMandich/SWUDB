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
        $model = [];
        $model = DB::select("describe cards");//table("cards")->whereLike("nome", "%".$get["nome"]."%")->get();
        return view('carte', ["model" =>$model]);
    }
}
