<?php

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

it('non fa richieste HTTP se il bot token manca', function () {
    config(['services.telegram.bot_token' => null]);
    Http::fake();

    $result = app(TelegramService::class)->sendMessage('123', 'ciao');

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});

it('restituisce il message id della risposta di Telegram', function () {
    config(['services.telegram.bot_token' => 'TOKEN']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);

    $result = app(TelegramService::class)->sendMessage('123', 'ciao');

    expect($result->successful)->toBeTrue()->and($result->messageId)->toBe(42);
});

it('editMessage senza message id non fa nulla', function () {
    Http::fake();

    $result = app(TelegramService::class)->editMessage('123', null, 'testo');

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});