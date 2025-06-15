<?php

use App\Http\Controllers\CardsController;
use App\Http\Controllers\DecksController;
use App\Http\Controllers\JobController;

use App\Events\MessageCreated;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

Route::get('/carte', [CardsController::class, 'index'])->name("carte");

Route::get('/carta/{espansione}/{numero}', [CardsController::class, 'show'])->name("carta");

Route::get('/update', [CardsController::class, 'startImport'])->name("carte.update");

Route::get('/dispatchBatch', [CardsController::class, 'dispatchBatch'])->name("carte.dispatchBatch");

Route::get('/sendBatch', [CardsController::class, 'sendBatch'])->name("carte.sendBatch");

Route::get('/mazzi', [DecksController::class, 'index'])->name("mazzi");

Route::get('/collezione', [DecksController::class, 'collezione'])->name('collezione')->middleware('auth');

Route::post('/collezione/update', [DecksController::class, 'updateCollezione'])->name('collezione.update')->middleware('auth');

Route::get('/mazzo/{user}/{mazzo}', [DecksController::class, 'show'])->name("mazzo");

Route::post('/mazzo/{user}/{mazzo}/save', [DecksController::class, 'store'])->name("mazzo.save");

Route::post('/mazzo/create', [DecksController::class, 'create'])->name("mazzo.create");

Route::delete('/mazzo/{user}/{mazzo}', [DecksController::class, 'destroy'])->name("mazzo.delete")->middleware('auth');

Route::patch('/mazzo/{user}/{mazzo}/visibility', [DecksController::class, 'toggleVisibility'])->name("mazzo.toggle.visibility")->middleware('auth');

Route::get('/mazzo/{user}/{mazzo}/export/txt', [DecksController::class, 'exportTxt'])->name("mazzo.export.txt");

Route::get('/mazzo/{user}/{mazzo}/export/json', [DecksController::class, 'exportJson'])->name("mazzo.export.json");

Route::get('/mazzi/import', [DecksController::class, 'showImport'])->name("mazzi.import")->middleware('auth');

Route::post('/mazzi/import/file', [DecksController::class, 'importFromFile'])->name("mazzi.import.file")->middleware('auth');

Route::post('/mazzi/import/url', [DecksController::class, 'importFromUrl'])->name("mazzi.import.url")->middleware('auth');

Route::get('/test/swudb', [DecksController::class, 'testSwudbConnection'])->name("test.swudb");

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

Route::get("/migrate", function(){
    if(!Auth::admin()){
        return view("errors.403");
    }
    Artisan::call("migrate");
    return "Migrated";
});

Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

Route::get('/users', function(){
    return redirect()->route("query", ["query" => "SELECT * FROM users"]);
});

Route::get('/docs/tos', function(){
    return view("docs.termOfService");
})->name("docs.tos");

Route::get('/docs/privacy', function(){
    return view("docs.privacy");
})->name("docs.privacy");

Route::get('/documentazione', function(){
    return view("documentazione");
})->name("documentazione");

Route::get("/job/AddCard", [JobController::class, 'addCard'])->name("job.addCard");

Route::get("/job/SendMessage", [JobController::class, 'sendMessage'])->name("job.sendMessage");

Route::get("/job/SendThreadMessage", [JobController::class, 'sendThreadMessage'])->name("job.sendThreadMessage");

route::get('compare/{espansione1}-{numero1}/{espansione2}-{numero2}', [CardsController::class, 'compare'])->name("compare");

route::get('test', function(){
    $var = \App\Models\Card::first()->name;
    return view("layouts.app");
})->name("test");