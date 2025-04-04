<?php

use App\Http\Controllers\ControllerCarte;

use App\Events\MessageCreated;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

Route::get('/', function(){return view('index');});

Route::get("query", function(Request $request){
    $get = $request->all();
    if(isset($get["query"])){
        $query = $get["query"];
    }else{
        $query = "SELECT * FROM cards limit 10";
    }
    return view("query", ["result" => DB::select($query), "query"=>$query]);
});

Route::get('/carte', [ControllerCarte::class, 'index']);

Route::get('/carte/update', [ControllerCarte::class, 'create']);

Route::get("/api/carte/{espansione}/{numero}", [ControllerCarte::class, 'api']);

Route::get('/carte/{espansione}/{numero}', [ControllerCarte::class, 'show']);

Route::get("/message/{message}", function($message){
    MessageCreated::dispatch($message);
});

Route::fallback(function () {
    return view('errors.404');
});
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
