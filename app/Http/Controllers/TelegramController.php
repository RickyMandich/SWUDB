<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    private $telegram;

    public function __construct()
    {
        $this->telegram = new Api(config('telegram.bot_token'));
    }

    // Webhook endpoint per ricevere update da Telegram
    public function webhook(Request $request)
    {
        try {
            $update = $this->telegram->getWebhookUpdate();
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
        
        // Chiama la tua logica esistente (sostituisce la chiamata HTTP)
        $this->triggerUpdate();
        
        // Invia messaggio di conferma all'utente
        $this->sendMessage($chatId, "Scan avviato con successo! 🔍");
    }

    // Metodo che sostituisce la chiamata HTTP al tuo endpoint /update
    private function triggerUpdate()
    {
        // Invece di fare una chiamata HTTP, chiama direttamente il tuo controller/service
        // Esempio: 
        // app(UpdateService::class)->performUpdate();
        
        // Oppure se vuoi mantenere la chiamata HTTP:
        // Http::get(url('/update'));
        
        // Per ora mettiamo un placeholder
        Log::info("Update triggered");
    }

    // Metodo helper per inviare messaggi
    private function sendMessage($chatId, $text)
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    // Metodo per configurare il webhook (da chiamare una sola volta)
    public function setWebhook()
    {
        try {
            $webhook_url = config('telegram.webhook_url') . '/telegram/webhook';
            $response = $this->telegram->setWebhook(['url' => $webhook_url]);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook configurato con successo',
                'url' => $webhook_url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore nella configurazione webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}