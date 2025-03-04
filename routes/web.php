<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControllerCarte;

Route::get('/', function () {
    return view('index');
});

Route::get('/carte', [ControllerCarte::class, 'index']);

Route::get('/carte/update', [ControllerCarte::class, 'create']);

//Route::get("/carte/delete", [ControllerCarte::class, 'deleteSome']);