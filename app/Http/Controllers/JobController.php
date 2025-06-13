<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;
use Illuminate\Support\Facades\Http;
use App\Events\MessageCreated;
use App\Events\CardReceived;

/**
 * Controller for handling background job operations and external integrations
 * Controller per gestire operazioni di job in background e integrazioni esterne
 *
 * This controller provides endpoints for:
 * - Adding cards to the database from import processes
 * - Sending messages via Telegram bot integration
 * - Fire-and-forget HTTP requests for asynchronous operations
 */
class JobController extends Controller{

    /**
     * Add a single card to the database from JSON data
     * Aggiunge una singola carta al database da dati JSON
     *
     * This method handles the complex process of creating a new card record
     * with comprehensive field mapping, validation, and error handling.
     * Includes special logic for max copies based on card type.
     *
     * @param Request $request HTTP request containing 'card' JSON parameter
     * @return void Outputs debug information or error messages
     */
    public function addCard(Request $request){
        $card = json_decode($request->input('card'), true);
        // return $card;
        if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug-addCard-start.log", "start addCard " . $card["espansione"] . "-" . $card["numero"]. " \n\n", FILE_APPEND);
        if(Card::where('espansione', $card["espansione"])->where('numero',$card["numero"])->get()->isEmpty()){
            $last = "prima di new";
            try{
                $carta = new Card();
                $last = "new-cid";
                $carta->cid = $card["cid"];
                $last = "cid-espansione";
                $carta->espansione = $card["espansione"];
                $last = "espansione-numero";
                $carta->numero = $card["numero"];
                $last = "numero-aspettoPrimario";
                $carta->aspettoPrimario = $card["aspettoPrimario"] ?? "";
                $last = "aspettoPrimario-aspettoSecondario";
                $carta->aspettoSecondario = $card["aspettoSecondario"] ?? "";
                $last = "aspettoSecondario-unica";
                $carta->unica = $card["unica"] ?? "";
                $last = "unica-nome";
                $carta->nome = $card["nome"];
                $last = "nome-titolo";
                $carta->titolo = $card["titolo"] ?? "";
                $last = "titolo-tipo";
                $carta->tipo = $card["tipo"];
                $last = "tipo-rarita";
                $carta->rarita = $card["rarita"];
                $last = "rarita-costo";
                $carta->costo = $card["costo"];
                $last = "costo-vita";
                $carta->vita = $card["vita"] ?? "";
                $last = "vita-potenza"; 
                $carta->potenza = $card["potenza"] ?? "";
                $last = "potenza-descrizione";
                $carta->descrizione = $card["descrizione"] ?? "";
                $last = "descrizione-tratti";
                if(gettype($card["tratti"]) == "string"){
                    $carta->tratti = $card["tratti"];
                }else{
                    $carta->tratti = implode(" * ", $card["tratti"]);
                }
                $last = "tratti-arena";
                $carta->arena = $card["arena"] ?? "";
                $last = "arena-artista";
                $carta->artista = $card["artista"];
                $last = "artista-uscita";
                $carta->uscita = $card["uscita"];
                $last = "uscita-frontArt";
                $carta->frontArt = $card["frontArt"];
                $last = "frontArt-backArt";
                $carta->backArt = $card["backArt"] ?? "";
                $last = "backArt-maxCopie3";
                $carta->maxCopie = 3;
                $last = "maxCopie3-maxCopie1";
                if(str_contains(strtolower($carta->tipo), 'leader')){
                    $carta->maxCopie = 1;
                }
                $last = "maxCopie1leader-maxCopie1base";
                if(str_contains(strtolower($carta->tipo), 'base')){
                    $carta->maxCopie = 1;
                }
                $last = "maxCopie1-maxCopie15";
                if(strtoupper($carta->espansione) == 'JTL' && $carta->numero == 256){
                    $carta->maxCopie = 15;
                }
                $last = "maxCopie15-maxCopie0";
                if(str_contains(strtolower($carta->tipo), "segnalino")){
                    $carta->maxCopie = 0;
                }
                $last = "maxCopie-creazione";
                unset($carta->creazione);
                $last = "creazione-save";
                $carta->save();
            }catch(\Exception $e){
                echo "eccezione ".$e->getMessage() . " <strong>at</strong> " . $last;
                if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug-addCard-end.log", "eccezione ".$e->getMessage() . " at " . "$last \n\n", FILE_APPEND);
                // MessageCreated::dispatch("eccezione ".$e->getMessage());
            }
            if(env("APP_DEBUG")) file_put_contents(__DIR__ . "/debug-addCard-end.log", "end addCard " . $card["espansione"] . "-" . $card["numero"]. " \n\n", FILE_APPEND);
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