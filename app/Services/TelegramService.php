<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    /**
     * Sends a text message
     * Invia un messaggio di testo
     */
    public function sendMessage(int|string|null $chatId, string $text, array $options = []): TelegramActionResult
    {
        return $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $this->prefixed($text)] + $options);
    }

    /**
     * Sends a photo by public URL (Telegram must be able to fetch it)
     * Invia una foto tramite URL pubblico (Telegram deve poterlo scaricare)
     */
    public function sendPhoto(int|string|null $chatId, string $photoUrl, string $caption = '', array $options = []): TelegramActionResult
    {
        return $this->call('sendPhoto', ['chat_id' => $chatId, 'photo' => $photoUrl, 'caption' => $this->prefixed($caption)] + $options);
    }

    /**
     * Edits the text of a message already sent (used for the single progress message of a scan)
     * Modifica il testo di un messaggio già inviato (usato per l'unico messaggio di avanzamento dello scan)
     */
    public function editMessage(int|string|null $chatId, ?int $messageId, string $text): TelegramActionResult
    {
        if ($messageId === null) {
            return new TelegramActionResult(false, null, 'messageId assente: il messaggio iniziale non è stato inviato');
        }

        return $this->call('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $this->prefixed($text)]);
    }

    /**
     * Deletes a message
     * Elimina un messaggio
     */
    public function deleteMessage(int|string|null $chatId, ?int $messageId): TelegramActionResult
    {
        if ($messageId === null) {
            return new TelegramActionResult(false, null, 'messageId assente');
        }

        return $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Calls a Telegram Bot API method and maps {"ok","result","description"} to a TelegramActionResult
     * Chiama un metodo dell'API bot di Telegram e traduce {"ok","result","description"} in un TelegramActionResult
     */
    private function call(string $method, array $payload): TelegramActionResult
    {
        $token = config('services.telegram.bot_token');

        if (! $token || empty($payload['chat_id'])) {
            return new TelegramActionResult(false, null, 'Telegram non configurato (bot_token o chat_id mancanti)');
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        } catch (ConnectionException $e) {
            return new TelegramActionResult(false, null, $e->getMessage());
        }

        $body = $response->json() ?? [];

        return new TelegramActionResult(
            (bool) ($body['ok'] ?? false),
            $body['result']['message_id'] ?? null,
            $body['description'] ?? null,
            $body,
        );
    }

    private function prefixed(string $text): string
    {
        return config('services.telegram.message_prefix', '').$text;
    }
}
