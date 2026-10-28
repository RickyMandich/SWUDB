<?php

namespace App\Http\Controllers;

use App\Jobs\ImportCardsFromSwuApiJob;
use App\Models\Card;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TelegramController extends Controller
{
    public function handle(Request $request, TelegramService $telegram): Response
    {
        $secret = (string) config('services.telegram.webhook_secret');
        abort_unless(
            $secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403
        );

        $text = trim($request->input('message.text', ''));
        $chatId = $request->input('message.chat.id');
        [$command, $argument] = array_pad(explode(' ', $text, 2), 2, null);

        match ($command) {
            '/scan' => $this->handleScan($chatId, $telegram),
            '/search' => $this->handleSearch($chatId, $argument, $telegram),
            default => $telegram->sendMessage($chatId, 'Comandi disponibili: /scan, /search <nome carta>'),
        };

        return response()->noContent(); // Telegram si aspetta solo un 200, non legge il body
    }

    private function isAdminChat(int|string $chatId): bool
    {
        return (string) $chatId === (string) config('services.telegram.admin_chat_id');
    }

    private function handleScan(int|string $chatId, TelegramService $telegram): void
    {
        if (! $this->isAdminChat($chatId)) {
            $telegram->sendMessage($chatId, 'Comando riservato agli admin.');

            return;
        }
        ImportCardsFromSwuApiJob::dispatch();
        $telegram->sendMessage($chatId, 'Scan avviato, ti aggiorno qui.');
    }

    private function handleSearch(int|string $chatId, ?string $query, TelegramService $telegram): void
    {
        if (! $query) {
            $telegram->sendMessage($chatId, 'Uso: /search <nome carta>');

            return;
        }

        $card = Card::originals()->where('name', 'like', "%{$query}%")->first();

        if (! $card) {
            $telegram->sendMessage($chatId, "Nessuna carta trovata per {$query}.");

            return;
        }

        // Telegram vuole un URL assoluto e pubblico, non il path relativo salvato in database
        $imageUrl = $card->front_art_path ? asset('storage/'.$card->front_art_path) : null;
        $result = $imageUrl
            ? $telegram->sendPhoto($chatId, $imageUrl, $card->name)
            : $telegram->sendMessage($chatId, $card->name);

        if (! $result->successful) {
            $telegram->sendMessage($chatId, $card->name.' — '.route('cards.show', ['expansion' => $card->expansion, 'number' => $card->number]));
        }
    }
}
