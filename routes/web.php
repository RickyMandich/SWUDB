<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControllerCarte;

Route::get('/', function () {
    return view('index');
});

Route::get('/carte', [ControllerCarte::class, 'index']);