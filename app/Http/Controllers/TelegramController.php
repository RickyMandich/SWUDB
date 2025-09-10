<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Update;
use App\Services\ThreadManager;
use App\Events\ThreadMessageCreated;

class TelegramController extends Controller
{
    private $telegram;
    private $logFile;
    private $currentThreadId;

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

        // Genera un thread ID univoco per questa scansione
        $this->currentThreadId = ThreadManager::generateThreadId('scan');
        $this->logToBot("Thread ID generato: " . $this->currentThreadId);

        try {
            // Invia messaggio di avvio usando il sistema di eventi (stesso del CardsController)
            $this->logToBot("Invio messaggio di avvio scansione...");
            ThreadMessageCreated::dispatch($this->currentThreadId, "🔍 Avvio scansione API per nuove carte...", false);
            $this->logToBot("Messaggio di avvio inviato con successo");

            // Chiama la tua logica esistente (sostituisce la chiamata HTTP)
            $this->logToBot("Avvio triggerUpdate()...");
            $this->triggerUpdate();
            $this->logToBot("triggerUpdate() completato");

            // Il messaggio di completamento e tutti i messaggi di progresso
            // verranno gestiti automaticamente dal sistema di thread messaging del CardsController
            $this->logToBot("=== COMANDO SCAN AVVIATO CON SUCCESSO ===");
            $this->logToBot("Thread ID: " . $this->currentThreadId);
            $this->logToBot("I messaggi di progresso verranno gestiti automaticamente dal CardsController");
        } catch (\Exception $e) {
            $this->logToBot("ERRORE in executeScanCommand: " . $e->getMessage(), 'ERROR');
            $this->logToBot("Stack trace: " . $e->getTraceAsString(), 'ERROR');

            try {
                // Invia messaggio di errore usando il sistema di eventi
                ThreadMessageCreated::dispatch($this->currentThreadId, "❌ Errore durante l'avvio della scansione: " . $e->getMessage(), true);
                $this->logToBot("Messaggio di errore inviato all'utente");
            } catch (\Exception $sendError) {
                $this->logToBot("ERRORE nell'invio del messaggio di errore: " . $sendError->getMessage(), 'ERROR');
                // Fallback al messaggio normale se il sistema di eventi fallisce
                try {
                    $this->sendMessage($chatId, "❌ Errore durante la scansione: " . $e->getMessage());
                } catch (\Exception $fallbackError) {
                    $this->logToBot("ERRORE anche nel fallback: " . $fallbackError->getMessage(), 'ERROR');
                }
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

            // Il messaggio di progresso verrà gestito dal CardsController
            // Non inviamo messaggi aggiuntivi qui per evitare conflitti

            $this->logToBot("Chiamata startImport() con thread ID: " . $this->currentThreadId);
            $cardsController->startImport($this->currentThreadId);
            $this->logToBot("startImport() completato con successo");

            // Il processo continuerà in background, i messaggi di progresso
            // verranno gestiti dal sistema di thread messaging esistente

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

    /**
     * Send a threaded message that can replace previous messages
     * Invia un messaggio in thread che può sostituire i messaggi precedenti
     *
     * @param string $chatId Telegram chat ID
     * @param string $message Message text
     * @param bool $isComplete Whether this message marks the thread as complete
     * @return void
     */
    private function sendThreadMessage($chatId, $message, $isComplete = false)
    {
        if (!$this->currentThreadId) {
            $this->logToBot("ERRORE: Nessun thread ID attivo per sendThreadMessage", 'ERROR');
            // Fallback al messaggio normale
            $this->sendMessage($chatId, $message);
            return;
        }

        $this->logToBot("Invio thread message [{$this->currentThreadId}]: {$message}" . ($isComplete ? " [COMPLETE]" : ""));

        $botToken = env('TELEGRAM_BOT_TOKEN');

        try {
            // Controlla se abbiamo un messaggio esistente da modificare
            $existingMessageId = ThreadManager::getTelegramMessageId($this->currentThreadId);

            if ($existingMessageId) {
                // Modifica il messaggio esistente
                $response = Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $existingMessageId,
                    'text' => $message
                ]);

                $this->logToBot("Messaggio thread modificato ID {$existingMessageId}");
            } else {
                // Invia un nuovo messaggio e memorizza il suo ID
                $response = Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message
                ]);

                $responseData = $response->json();
                if (isset($responseData['result']['message_id'])) {
                    $messageId = $responseData['result']['message_id'];
                    ThreadManager::setTelegramMessageId($this->currentThreadId, $messageId);
                    $this->logToBot("Nuovo messaggio thread inviato ID {$messageId}");
                }
            }

            // Aggiorna il thread manager
            ThreadManager::updateThread($this->currentThreadId, $message, $isComplete);

        } catch (\Exception $e) {
            $this->logToBot("ERRORE in sendThreadMessage: " . $e->getMessage(), 'ERROR');
            // Fallback al messaggio normale in caso di errore
            $this->sendMessage($chatId, $message);
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