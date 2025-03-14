<?php

use App\Http\Controllers\ControllerCarte;

use App\Events\MessageCreated;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

Route::get("query", function(Request $request){
    $get = $request->all();
    $query = $get["query"];
    return view("query", ["result" => DB::select($query), "query"=>$query]);
});

Route::get('/carte', [ControllerCarte::class, 'index']);

Route::get('/carte/update', [ControllerCarte::class, 'create']);

Route::get("api/carte", [ControllerCarte::class, 'api']);

Route::get("/migrate", function(){
    $return = Artisan::call('migrate');
    return "migrate exit status: $return";
});

Route::get("/test/{message}", function($message){
    MessageCreated::dispatch($message);
});