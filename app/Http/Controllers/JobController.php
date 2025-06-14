<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Controller for handling background job operations and Telegram notifications
 * Controller per gestire operazioni di job in background e notifiche Telegram
 *
 * This controller provides endpoints for background processing tasks,
 * including card imports and Telegram message sending for status updates.
 */
class JobController extends Controller
{
    /**
     * Add a new card to the database from external API data
     * Aggiunge una nuova carta al database da dati API esterni
     *
     * This method processes card data from the request and creates a new
     * Card record in the database with all the provided attributes.
     *
     * @param Request $request HTTP request containing card data and authentication token
     * @return void Outputs success/error messages directly
     */
    public function addCard(Request $request){
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

        $card = new \App\Models\Card();
        $card->nome = $request->input('nome');
        $card->costo = $request->input('costo');
        $card->vita = $request->input('vita');
        $card->potenza = $request->input('potenza');
        $card->tipo = $request->input('tipo');
        $card->sottotipo = $request->input('sottotipo');
        $card->tratti = $request->input('tratti');
        $card->testo = $request->input('testo');
        $card->aspetto_primario = $request->input('aspetto_primario');
        $card->aspetto_secondario = $request->input('aspetto_secondario');
        $card->rarita = $request->input('rarita');
        $card->numero_carta = $request->input('numero_carta');
        $card->espansione = $request->input('espansione');
        $card->artista = $request->input('artista');
        $card->variante = $request->input('variante');
        $card->immagine_carta = $request->input('immagine_carta');
        $card->immagine_artista = $request->input('immagine_artista');
        $card->arena = $request->input('arena');
        $card->epicness = $request->input('epicness');
        $card->unique = $request->input('unique');

        try {
            $card->save();
            echo "Carta '{$card->nome}' aggiunta con successo!\n";
        } catch (\Exception $e) {
            echo "Errore nell'aggiunta della carta: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Send a message via Telegram bot integration
     * Invia un messaggio tramite integrazione bot Telegram
     *
     * This method sends status messages and notifications to a configured
     * Telegram chat using the bot API. Includes token validation for security.
     *
     * @param Request $request HTTP request containing 'message' and 'token' parameters
     * @return void Sends message to Telegram or logs errors
     */
    public function sendMessage(Request $request){
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

        $message = $request->input('message');

        $botToken = env('TELEGRAM_BOT_TOKEN', '7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc');
        $chatId = env('TELEGRAM_CHAT_ID', '5533337157');
        
        try {
            Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message
            ]);
        } catch (\Exception $e) {
            \Log::error("Errore Telegram: " . $e->getMessage());
        }

    }

    /**
     * Send a threaded message via Telegram bot with message replacement logic
     * Invia un messaggio in thread tramite bot Telegram con logica di sostituzione messaggi
     *
     * This method handles threaded messages that can replace previous messages
     * within the same execution context. It uses Telegram's message editing
     * capabilities to avoid notification spam by editing existing messages.
     *
     * @param Request $request HTTP request containing 'threadId', 'message', 'isComplete', and 'token' parameters
     * @return void Sends or edits message in Telegram or logs errors
     */
    public function sendThreadMessage(Request $request){
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

        $threadId = $request->input('threadId');
        $message = $request->input('message');
        $isComplete = (bool) $request->input('isComplete', false);

        $botToken = env('TELEGRAM_BOT_TOKEN', '7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc');
        $chatId = env('TELEGRAM_CHAT_ID', '5533337157');

        try {
            // Check if we have an existing message to edit
            $existingMessageId = \App\Services\ThreadManager::getTelegramMessageId($threadId);
            
            if ($existingMessageId) {
                // Edit the existing message
                $response = Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $existingMessageId,
                    'text' => $message
                ]);
                
                if(env("APP_DEBUG")) {
                    file_put_contents(__DIR__ . "/debug-threadMessage.log", 
                        "Edited thread message [{$threadId}] ID {$existingMessageId}: {$message}" . 
                        ($isComplete ? " [COMPLETE]" : "") . "\n", FILE_APPEND);
                }
            } else {
                // Send a new message and store its ID
                $response = Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message
                ]);
                
                $responseData = $response->json();
                if (isset($responseData['result']['message_id'])) {
                    $messageId = $responseData['result']['message_id'];
                    \App\Services\ThreadManager::setTelegramMessageId($threadId, $messageId);
                    
                    if(env("APP_DEBUG")) {
                        file_put_contents(__DIR__ . "/debug-threadMessage.log", 
                            "Sent new thread message [{$threadId}] ID {$messageId}: {$message}" . 
                            ($isComplete ? " [COMPLETE]" : "") . "\n", FILE_APPEND);
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Log::error("Errore Telegram Thread Message: " . $e->getMessage());
            if(env("APP_DEBUG")) {
                file_put_contents(__DIR__ . "/debug-threadMessage.log", 
                    "Error in thread message [{$threadId}]: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }

    /**
     * Execute a fire-and-forget GET request without waiting for response
     * Esegue una richiesta GET "fire-and-forget" senza aspettare la risposta
     *
     * This method sends an HTTP GET request asynchronously using raw sockets,
     * allowing the calling process to continue without waiting for the response.
     * Useful for triggering background processes.
     *
     * @param string $url The target URL for the GET request
     * @param array $data Query parameters to append to the URL
     * @return bool True if request was sent successfully, false on error
     */
    public static function fireAndForgetGet($url, $data = []) {
        $query = http_build_query($data);
        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug-fire.log", "fireAndForget: $url?$query" . "\n\n", FILE_APPEND);
        $parts = parse_url($url);

        if (!isset($parts['host']) || !isset($parts['path'])) {
            return false;
        }

        // Ricostruisci la query string
        $path = $parts['path'];
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'] . '&' . $query;
        } elseif ($query !== '') {
            $path .= '?' . $query;
        }

        $fp = fsockopen($parts['host'], $parts['port'] ?? 80, $errno, $errstr, 30);

        if (!$fp) {
            return false;
        }

        $out = "GET " . $path . " HTTP/1.1\r\n";
        $out .= "Host: " . $parts['host'] . "\r\n";
        $out .= "Connection: Close\r\n\r\n";

        fwrite($fp, $out);
        fclose($fp);

        return true;
    }

    /**
     * Execute a fire-and-forget POST request without waiting for response
     * Esegue una richiesta POST "fire-and-forget" senza aspettare la risposta
     *
     * This method sends an HTTP POST request asynchronously using raw sockets,
     * allowing the calling process to continue without waiting for the response.
     * Useful for triggering background processes with form data.
     *
     * @param string $url The target URL for the POST request
     * @param array $data Form data to send in the POST body
     * @return bool True if request was sent successfully, false on error
     */
    public static function fireAndForgetPost($url, $data = []) {
        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug-fire.log", "fireAndForget POST: $url, " . http_build_query($data) . "\n\n", FILE_APPEND);
        $parts = parse_url($url);

        if (!isset($parts['host']) || !isset($parts['path'])) {
            return false;
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 80;
        $path = $parts['path'];
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        $postData = http_build_query($data);

        $fp = fsockopen($host, $port, $errno, $errstr, 30);

        if (!$fp) {
            return false;
        }

        $out = "POST " . $path . " HTTP/1.1\r\n";
        $out .= "Host: " . $host . "\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-Length: " . strlen($postData) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        $out .= $postData;

        fwrite($fp, $out);
        fclose($fp);

        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug-fire.log", "fine fire post \n\n", FILE_APPEND);

        return true;
    }
}
