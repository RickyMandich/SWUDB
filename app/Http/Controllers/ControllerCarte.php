<?php

namespace App\Http\Controllers;

use App\Models\Card;
use Illuminate\Http\Request;

class ControllerCarte extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        $get = $request->all();
        $model = [];
        $model = Card::where('nome', 'like', '%' . $get["nome"] . '%')->get();
        return view('carte', $model);
    }
}
