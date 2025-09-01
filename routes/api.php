<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Telegram Bot Routes
Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
Route::get('/telegram/setup-webhook', [TelegramController::class, 'setWebhook']);
