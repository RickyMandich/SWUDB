<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ControllerCarte extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        $get = $request->all();
        $model = [
            
        ];
        return view('carte', $model);
    }
}
