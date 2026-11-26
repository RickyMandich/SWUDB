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

it('deleteMessage senza message id non fa nulla', function () {
    Http::fake();

    $result = app(TelegramService::class)->deleteMessage('123', null);

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});

// Prova dal vivo (opt-in, saltata di default): invia un messaggio vero alla chat configurata nel .env e lo cancella
// sempre, anche se un'asserzione fallisce, cos\u00ec non resta nulla da eliminare a mano.
// Si esegue con: TELEGRAM_LIVE_TEST=1 php artisan test --filter="messaggio vero"
it('invia un messaggio vero e lo cancella', function () {
    $service = app(TelegramService::class);
    $chatId = config('services.telegram.admin_chat_id') ?? env('TELEGRAM_ADMIN_CHAT_ID');
    $messageId = null;

    try {
        $sent = $service->sendMessage($chatId, 'Test automatico: questo messaggio si cancella da solo');
        $messageId = $sent->messageId;

        $this->assertTrue($sent->successful, 'Telegram ha risposto: '.($sent->errorDescription ?? 'nessuna descrizione (bot token o chat id mancanti nel .env?)'));
        expect($messageId)->toBeInt();
    } finally {
        if ($messageId !== null) {
            $deleted = $service->deleteMessage($chatId, $messageId);
            $this->assertTrue($deleted->successful, "Il messaggio {$messageId} non è stato cancellato, eliminalo a mano: ".($deleted->errorDescription ?? ''));
        }
    }
})->skip(fn () => ! env('TELEGRAM_LIVE_TEST'), 'Prova dal vivo: serve TELEGRAM_LIVE_TEST=1 e le credenziali Telegram vere nel .env');
