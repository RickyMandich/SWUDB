<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class JobController extends Controller{
    public function addCard(Request $request){
        if(Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()->isEmpty()){
            $last = "prima di new";
            try{
                $card = new Card();
                $last = "new-cid";
                $card->cid = $event->card["cid"];
                $last = "cid-espansione";
                $card->espansione = $event->card["espansione"];
                $last = "espansione-numero";
                $card->numero = $event->card["numero"];
                $last = "numero-aspettoPrimario";
                $card->aspettoPrimario = $event->card["aspettoPrimario"];
                $last = "aspettoPrimario-aspettoSecondario";
                $card->aspettoSecondario = $event->card["aspettoSecondario"];
                $last = "aspettoSecondario-unica";
                $card->unica = $event->card["unica"];
                $last = "unica-nome";
                $card->nome = $event->card["nome"];
                $last = "nome-titolo";
                $card->titolo = $event->card["titolo"];
                $last = "titolo-tipo";
                $card->tipo = $event->card["tipo"];
                $last = "tipo-rarita";
                $card->rarita = $event->card["rarita"];
                $last = "rarita-costo";
                $card->costo = $event->card["costo"];
                $last = "costo-vita";
                $card->vita = $event->card["vita"];
                $last = "vita-potenza"; 
                $card->potenza = $event->card["potenza"];
                $last = "potenza-descrizione";
                $card->descrizione = $event->card["descrizione"];
                $last = "descrizione-tratti";
                $card->tratti = $event->card["tratti"];
                $last = "tratti-arena";
                $card->arena = $event->card["arena"];
                $last = "arena-artista";
                $card->artista = $event->card["artista"];
                $last = "artista-uscita";
                $card->uscita = $event->card["uscita"];
                $last = "uscita-frontArt";
                $card->frontArt = $event->card["frontArt"];
                $last = "frontArt-backArt";
                $card->backArt = $event->card["backArt"];
                $last = "backArt-maxCopie";
                $card->maxCopie = 3;
                if(str_contains(strtolower($card->tipo), 'leader')){
                    $card->maxCopie = 1;
                }
                if(str_contains(strtolower($card->tipo), 'base')){
                    $card->maxCopie = 1;
                }
                if(strtoupper($card->espansione) == 'JTL' && $card->numero == 256){
                    $card->maxCopie = 15;
                }
                if(str_contains(strtolower($card->tipo), "segnalino")){
                    $card->maxCopie = 0;
                }
                $last = "maxCopie-save";
                unset($card->creazione);
                $card->save();
            }catch(\Exception $e){
                echo "eccezione ".$e->getMessage()."\n";
                echo $last;
                MessageCreated::dispatch("eccezione ".$e->getMessage());
            }
            try{
                $result = Card::where('espansione', $event->card["espansione"])->where('numero',$event->card["numero"])->get()->get(0);
                echo $result->snippet."\n";
                // MessageCreated::dispatch($result->snippet);
            }catch(\Exception $e){
                echo "eccezione ".$e->getMessage()."\n";
                MessageCreated::dispatch("eccezione ".$e->getMessage());
            }
        }
    }

    public function sendMessage(Request $request){
        file_put_contents(__DIR__ . '/debug-job.log', "invio messaggio telegram alle " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        if ($request->input('token') !== env('JOB_TOKEN')) {
        file_put_contents(__DIR__ . '/debug-job.log', "token non valido alle " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            abort(403);
        }

        file_put_contents(__DIR__ . '/debug-job.log', "token valido alle " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        $message = $request->input('message');

        $botToken = env('TELEGRAM_BOT_TOKEN', '7717265706:AAH5chf4Ae3vsFSt7158K-RFWdh9BudnnQc');
        $chatId = env('TELEGRAM_CHAT_ID', '5533337157');
        
        try {
            Http::withoutVerifying()->get("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message
            ]);
            
            return;
        } catch (\Exception $e) {
            \Log::error("Errore Telegram: " . $e->getMessage());
            return;
        }

        file_put_contents(__DIR__ . '/debug-job.log', "messaggio inviato: $message alle " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    }

    static function fireAndForget($url, $data = []) {
        $logPath = __DIR__ . '/debug-fire.log';

        file_put_contents($logPath, "=== fireAndForget START === " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        file_put_contents($logPath, "URL: $url\n", FILE_APPEND);
        file_put_contents($logPath, "DATA: " . json_encode($data) . "\n", FILE_APPEND);

        $postdata = http_build_query($data);
        $parts = parse_url($url);

        if (!isset($parts['host']) || !isset($parts['path'])) {
            file_put_contents($logPath, "ERRORE: URL non valido.\n", FILE_APPEND);
            return false;
        }

        $fp = fsockopen($parts['host'], 8000, $errno, $errstr, 30);

        if (!$fp) {
            file_put_contents($logPath, "ERRORE: fsockopen fallito - $errstr ($errno)\n", FILE_APPEND);
            return false;
        }

        $out = "POST " . $parts['path'] . " HTTP/1.1\r\n";
        $out .= "Host: " . $parts['host'] . "\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-Length: " . strlen($postdata) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        $out .= $postdata;

        file_put_contents($logPath, "REQUEST:\n$out\n", FILE_APPEND);

        fwrite($fp, $out);
        fclose($fp);

        file_put_contents($logPath, "=== fireAndForget END ===\n\n", FILE_APPEND);

        return true;
    }


}