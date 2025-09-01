<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;

class TelegramController extends Controller
{
    private $telegram;
    private $logFile;

    public function __construct()
    {
        $this->telegram = new BotApi(config('telegram.bot_token'));
        $this->initializeLogging();
    }

    /**
     * Inizializza il sistema di logging per il bot
     */
    private function initializeLogging()
    {
        // Crea la cartella bot se non esiste
        $botLogDir = storage_path('logs/bot');
        if (!File::exists($botLogDir)) {
            File::makeDirectory($botLogDir, 0755, true);
        }

        // Crea un file di log unico per questa sessione
        $timestamp = now()->format('Y-m-d_H-i-s');
        $this->logFile = $botLogDir . '/telegram_' . $timestamp . '.log';

        $this->logToBot("=== AVVIO SESSIONE BOT TELEGRAM ===");
        $this->logToBot("Timestamp: " . now()->format('Y-m-d H:i:s'));
        $this->logToBot("Token configurato: " . (config('telegram.bot_token') ? 'SI' : 'NO'));
        $this->logToBot("Webhook URL: " . config('telegram.webhook_url'));
        $this->logToBot("=====================================");
    }

    /**
     * Scrive un messaggio nel file di log dedicato del bot
     */
    private function logToBot($message, $level = 'INFO')
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        File::append($this->logFile, $logEntry);

        // Log anche nel sistema Laravel per backup
        Log::info("BOT: {$message}");
    }

    // Webhook endpoint per ricevere update da Telegram
    public function webhook(Request $request)
    {
        $this->logToBot("=== WEBHOOK RICEVUTO ===");

        try {
            $input = $request->getContent();
            $this->logToBot("Raw input ricevuto: " . $input);

            $decodedInput = json_decode($input, true);
            $this->logToBot("Input decodificato: " . json_encode($decodedInput, JSON_PRETTY_PRINT));

            $update = Update::fromResponse($decodedInput);
            $this->logToBot("Update object creato con successo");

            $this->handleUpdate($update);

            $this->logToBot("Webhook processato con successo");
            return response('OK', 200);
        } catch (\Exception $e) {
            $this->logToBot("ERRORE nel webhook: " . $e->getMessage(), 'ERROR');
            $this->logToBot("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            return response('Error', 500);
        }
    }

    // Gestisce gli update ricevuti
    private function handleUpdate(Update $update)
    {
        $this->logToBot("=== GESTIONE UPDATE ===");

        if ($update->getMessage()) {
            $message = $update->getMessage();
            $this->logToBot("Messaggio ricevuto da: " . ($message->getFrom()->getUsername() ?? 'utente senza username'));
            $this->logToBot("Chat ID: " . $message->getChat()->getId());
            $this->logToBot("Testo messaggio: " . $message->getText());

            // Verifica se è un comando (inizia con /)
            if (strpos($message->getText(), '/') === 0) {
                $this->logToBot("Comando rilevato!");
                $command = $message->getText();
                $username = $message->getFrom()->getUsername() ?? 'utente_senza_username';

                switch ($command) {
                    case '/scan':
                        $this->logToBot("Comando /scan riconosciuto");
                        $this->executeScanCommand($username, $message->getChat()->getId());
                        break;

                    default:
                        $this->logToBot("Comando non riconosciuto: " . $command);
                        $this->sendMessage($message->getChat()->getId(), 'Comando non riconosciuto');
                        break;
                }
            } else {
                $this->logToBot("Messaggio normale (non comando) ricevuto");
            }
        } else {
            $this->logToBot("Update ricevuto senza messaggio");
        }
    }

    // Equivalente del tuo metodo executeScanCommand Java
    private function executeScanCommand($username, $chatId)
    {
        $this->logToBot("=== ESECUZIONE COMANDO SCAN ===");
        $this->logToBot("Username: " . $username);
        $this->logToBot("Chat ID: " . $chatId);

        try {
            // Invia messaggio di avvio
            $this->logToBot("Invio messaggio di avvio scansione...");
            $this->sendMessage($chatId, "🔍 Avvio scansione in corso...");
            $this->logToBot("Messaggio di avvio inviato con successo");

            // Chiama la tua logica esistente (sostituisce la chiamata HTTP)
            $this->logToBot("Avvio triggerUpdate()...");
            $this->triggerUpdate();
            $this->logToBot("triggerUpdate() completato");

            // Invia messaggio di conferma all'utente
            $this->logToBot("Invio messaggio di completamento...");
            $this->sendMessage($chatId, "✅ Scansione completata con successo!");
            $this->logToBot("Messaggio di completamento inviato con successo");

            $this->logToBot("=== COMANDO SCAN COMPLETATO CON SUCCESSO ===");
        } catch (\Exception $e) {
            $this->logToBot("ERRORE in executeScanCommand: " . $e->getMessage(), 'ERROR');
            $this->logToBot("Stack trace: " . $e->getTraceAsString(), 'ERROR');

            try {
                $this->sendMessage($chatId, "❌ Errore durante la scansione: " . $e->getMessage());
                $this->logToBot("Messaggio di errore inviato all'utente");
            } catch (\Exception $sendError) {
                $this->logToBot("ERRORE nell'invio del messaggio di errore: " . $sendError->getMessage(), 'ERROR');
            }
        }
    }

    // Metodo che sostituisce la chiamata HTTP al tuo endpoint /update
    private function triggerUpdate()
    {
        $this->logToBot("=== TRIGGER UPDATE ===");

        try {
            $this->logToBot("Creazione istanza CardsController...");
            $cardsController = new \App\Http\Controllers\CardsController();
            $this->logToBot("CardsController creato con successo");

            $this->logToBot("Chiamata startImport()...");
            $cardsController->startImport();
            $this->logToBot("startImport() completato con successo");

        } catch (\Exception $e) {
            $this->logToBot("ERRORE durante triggerUpdate: " . $e->getMessage(), 'ERROR');
            $this->logToBot("Stack trace: " . $e->getTraceAsString(), 'ERROR');
            throw $e;
        }
    }

    // Metodo helper per inviare messaggi
    private function sendMessage($chatId, $text)
    {
        $this->logToBot("Invio messaggio a chat {$chatId}: {$text}");

        try {
            $result = $this->telegram->sendMessage($chatId, $text);
            $this->logToBot("Messaggio inviato con successo");
            return $result;
        } catch (\Exception $e) {
            $this->logToBot("ERRORE nell'invio messaggio: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    // Metodo per configurare il webhook (da chiamare una sola volta)
    public function setWebhook()
    {
        $this->logToBot("=== CONFIGURAZIONE WEBHOOK ===");

        try {
            $webhook_url = config('telegram.webhook_url') . '/api/telegram/webhook';
            $this->logToBot("URL webhook: " . $webhook_url);

            $this->logToBot("Chiamata setWebhook a Telegram...");
            $response = $this->telegram->setWebhook($webhook_url);
            $this->logToBot("Risposta Telegram: " . json_encode($response));

            $this->logToBot("Webhook configurato con successo!");

            return response()->json([
                'success' => true,
                'message' => 'Webhook configurato con successo',
                'url' => $webhook_url,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            $this->logToBot("ERRORE nella configurazione webhook: " . $e->getMessage(), 'ERROR');
            $this->logToBot("Stack trace: " . $e->getTraceAsString(), 'ERROR');

            return response()->json([
                'success' => false,
                'message' => 'Errore nella configurazione webhook: ' . $e->getMessage()
            ], 500);
        }
    }
}