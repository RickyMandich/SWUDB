<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class TelegramController extends Controller
{
    private $telegram;

    public function __construct()
    {
        $this->telegram = new BotApi(config('telegram.bot_token'));
    }

    // Webhook endpoint per ricevere update da Telegram
    public function webhook(Request $request)
    {
        try {
            $input = $request->getContent();
            $update = Update::fromResponse(json_decode($input, true));
            $this->handleUpdate($update);
            
            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage());
            return response('Error', 500);
        }
    }

    // Gestisce gli update ricevuti
    private function handleUpdate(Update $update)
    {
        if ($update->getMessage() && $update->getMessage()->isCommand()) {
            $message = $update->getMessage();
            $command = $message->getText();
            $username = $message->getFrom()->getUsername();

            switch ($command) {
                case '/scan':
                    $this->executeScanCommand($username, $message->getChat()->getId());
                    break;
                
                default:
                    $this->sendMessage($message->getChat()->getId(), 'Comando non riconosciuto');
                    break;
            }
        }
    }

    // Equivalente del tuo metodo executeScanCommand Java
    private function executeScanCommand($username, $chatId)
    {
        Log::info("Scan command received by: " . $username);

        try {
            // Invia messaggio di avvio
            $this->sendMessage($chatId, "🔍 Avvio scansione in corso...");

            // Chiama la tua logica esistente (sostituisce la chiamata HTTP)
            $this->triggerUpdate();

            // Invia messaggio di conferma all'utente
            $this->sendMessage($chatId, "✅ Scansione completata con successo!");
        } catch (\Exception $e) {
            Log::error("Error in executeScanCommand: " . $e->getMessage());
            $this->sendMessage($chatId, "❌ Errore durante la scansione: " . $e->getMessage());
        }
    }

    // Metodo che sostituisce la chiamata HTTP al tuo endpoint /update
    private function triggerUpdate()
    {
        try {
            Log::info("Triggering update from Telegram bot");

            // Chiama direttamente il metodo startImport del CardsController
            $cardsController = new \App\Http\Controllers\CardsController();
            $cardsController->startImport();

            Log::info("Update completed successfully");
        } catch (\Exception $e) {
            Log::error("Error during update: " . $e->getMessage());
            throw $e;
        }
    }

    // Metodo helper per inviare messaggi
    private function sendMessage($chatId, $text)
    {
        $this->telegram->sendMessage($chatId, $text);
    }

    // Metodo per configurare il webhook (da chiamare una sola volta)
    public function setWebhook()
    {
        try {
            $webhook_url = config('telegram.webhook_url') . '/api/telegram/webhook';
            $response = $this->telegram->setWebhook($webhook_url);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook configurato con successo',
                'url' => $webhook_url,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore nella configurazione webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}