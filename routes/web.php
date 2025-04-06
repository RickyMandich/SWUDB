<?php

use App\Http\Controllers\CardsController;

use App\Events\MessageCreated;

use App\Http\Controllers\DecksController;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

Route::get('/', function(){return view('index');});

Route::get("query", function(Request $request){
    if(!Auth::admin()){
        return view("errors.403");
    }
    $get = $request->all();
    if(isset($get["query"])){
        $query = $get["query"];
    }else{
        $query = "SELECT * FROM cards limit 10";
    }
    return view("query", ["result" => DB::select($query), "query"=>$query]);
});

Route::get('/carte', [CardsController::class, 'index']);

Route::get('/carte/update', [CardsController::class, 'create']);

Route::get('/mazzi', [DecksController::class, 'index']);

Route::get("/api/carte/{espansione}/{numero}", [CardsController::class, 'api']);

Route::get('/carte/{espansione}/{numero}', [CardsController::class, 'show']);

Route::get("/message/{message}", function($message){
    MessageCreated::dispatch($message);
});

Route::fallback(function () {
    return view('errors.404');
});
Auth::routes();

Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
