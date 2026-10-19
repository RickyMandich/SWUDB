<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::prefix('cards')->group(function () {
        Route::get('/search', [Api\CardController::class, 'search']);
        Route::get('/{expansion}/{number}', [Api\CardController::class, 'show']);
    });
    Route::get('/decks/{userName}/{deckName}', [Api\DeckController::class, 'show']);
});
