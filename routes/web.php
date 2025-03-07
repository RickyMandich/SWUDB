<?php

use App\Jobs\ExecuteArtisanCommand;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControllerCarte;

Route::get('/', function () {
    return view('index');
});

Route::get('/carte', [ControllerCarte::class, 'index']);

Route::get('/carte/update', [ControllerCarte::class, 'create']);

Route::get("api/carte", [ControllerCarte::class, 'api']);

Route::get("/migrate", function(){
    $return = Artisan::call('migrate');
    return "migrate exit status: $return";
});

Route::get("/command/{command}", function($command){
    ExecuteArtisanCommand::dispatch($command);
});