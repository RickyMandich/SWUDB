<?php

use App\Http\Controllers\CardsController;
use App\Http\Controllers\DecksController;
use App\Http\Controllers\UsersController;

use App\Events\MessageCreated;

use App\Models\Deck;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

Route::get('/', function(){return view('index');})->name("index");

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
})->name("query");

Route::get('/carte/{espansione}', [CardsController::class, 'index'])->name("carte.set");

Route::get('/carte', [CardsController::class, 'indexAll'])->name("carte");

Route::get('/carta/{espansione}/{numero}', [CardsController::class, 'show'])->name("carta");

Route::get('/carte/update', [CardsController::class, 'create'])->name("carte.update");

Route::get('/mazzi', [DecksController::class, 'index'])->name("mazzi");

Route::get('mazzo/{user}/{mazzo}', [DecksController::class, 'show'])->name("mazzo");

Route::post('mazzo/{user}/{mazzo}/save', [DecksController::class, 'store'])->name("mazzo.save");

Route::post('mazzo/create', [DecksController::class, 'create'])->name("mazzo.create");

Route::get("/api/carta/{espansione}/{numero}", [CardsController::class, 'api'])->name("api.carta");

Route::get("/api/carte/{espansione}", [CardsController::class, 'apis'])->name("api.carte");

Route::get("/api/mazzi/{user}/{nome}/{public}", [DecksController::class, 'api'])->name("api.mazzi");

Route::get("/message/{message}", function($message){
    MessageCreated::dispatch($message);
})->name("message");

Route::fallback(function () {
    return view('errors.404');
});

Auth::routes();

Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

Route::get('/test', function(){
    return Deck::select("nome as mazzo", "codUtente", "public", "id")->distinct()->orderBy("id")->get();
})->name("test");

Route::get('/users', function(){
    return redirect()->route("query", ["query" => "SELECT * FROM users"]);
});

Route::get('/docs/tos', function(){
    return view("docs.termOfService");
})->name("docs.tos");

Route::get('/docs/privacy', function(){
    return view("docs.privacy");
})->name("docs.privacy");