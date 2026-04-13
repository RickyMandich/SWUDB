<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

// Telegram Bot Routes
Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);
Route::get('/telegram/setup-webhook', [TelegramController::class, 'setWebhook']);
