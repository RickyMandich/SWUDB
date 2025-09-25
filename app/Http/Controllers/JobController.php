<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Queue\Worker;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\WorkerOptions;
use App\Events\MessageCreated;
use App\Services\EmailLogService;

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
     * Handles both individual parameters and JSON card data.
     *
     * @param Request $request HTTP request containing card data and authentication token
     * @return void Outputs success/error messages directly
     */
    public function addCard(Request $request){
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403);
        }

        $last = "inizio";
        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-addCard.log", "inizio addCard \n\n", FILE_APPEND);
        
        try {
            // Check if card data is passed as JSON
            $cardJson = $request->input('card');
            if ($cardJson) {
                $card = json_decode($cardJson, true);
                if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-addCard.log", "card from JSON: " . json_encode($card) . "\n\n", FILE_APPEND);
            } else {
                // Fallback to individual parameters
                $card = $request->all();
                if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-addCard.log", "card from params: " . json_encode($card) . "\n\n", FILE_APPEND);
            }

            $last = "creazione-carta";
            $carta = new \App\Models\Card();
            $carta->cid = $card['cid'];
            $carta->nome = $card['nome'] ?? '';
            $carta->espansione = $card['espansione'] ?? '';
            $carta->numero = $card['numero'] ?? null;
            $carta->aspettoPrimario = $card['aspettoPrimario'] ?? '';
            $carta->aspettoSecondario = $card['aspettoSecondario'] ?? '';
            $carta->unica = $card['unica'] ?? false;
            $carta->titolo = $card['titolo'] ?? '';
            $carta->tipo = $card['tipo'] ?? '';
            $carta->rarita = $card['rarita'] ?? '';
            $carta->costo = $card['costo'] ?? null;
            $carta->vita = $card['vita'] ?? null;
            $carta->potenza = $card['potenza'] ?? null;
            $carta->descrizione = $card['descrizione'] ?? '';
            $carta->tratti = $card['tratti'] ?? '';
            $carta->arena = $card['arena'] ?? '';
            $carta->artista = $card['artista'] ?? '';
            $carta->frontArt = $card['frontArt'] ?? '';
            $carta->backArt = $card['backArt'] ?? '';
            $carta->uscita = $card['uscita'] ?? '';

            $last = "maxCopie3";
            $carta->maxCopie = 3;
            
            $last = "maxCopie1leader";
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
            
            echo "Carta '{$carta->nome}' aggiunta con successo!\n";
            if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-addCard-end.log", "success addCard " . $card["espansione"] . "-" . $card["numero"]. " \n\n", FILE_APPEND);
            
        } catch(\Exception $e){
            echo "eccezione ".$e->getMessage() . " <strong>at</strong> " . $last;
            if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-addCard-end.log", "eccezione ".$e->getMessage() . " at " . "$last \n\n", FILE_APPEND);
        }
        
        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-addCard-end.log", "end addCard " . ($card["espansione"] ?? 'unknown') . "-" . ($card["numero"] ?? 'unknown'). " \n\n", FILE_APPEND);
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
                
                if(env("APP_DEBUG_LOG")) {
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
                    
                    if(env("APP_DEBUG_LOG")) {
                        file_put_contents(__DIR__ . "/debug-threadMessage.log", 
                            "Sent new thread message [{$threadId}] ID {$messageId}: {$message}" . 
                            ($isComplete ? " [COMPLETE]" : "") . "\n", FILE_APPEND);
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Log::error("Errore Telegram Thread Message: " . $e->getMessage());
            if(env("APP_DEBUG_LOG")) {
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
        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-fire.log", "fireAndForget: $url?$query" . "\n\n", FILE_APPEND);
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
        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-fire.log", "fireAndForget POST: $url, " . http_build_query($data) . "\n\n", FILE_APPEND);
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

        if(env("APP_DEBUG_LOG")) file_put_contents(__DIR__ . "/debug-fire.log", "fine fire post \n\n", FILE_APPEND);

        return true;
    }

    /**
     * Process email queue with rate limiting
     * Elabora la coda email con rate limiting
     *
     * This method processes pending email jobs from the database queue
     * with automatic rate limiting to prevent email provider overflow.
     *
     * @param Request $request HTTP request containing authentication token
     * @return void
     */
    public function processEmailQueue(Request $request)
    {
        // Verify token for security
        if ($request->input('token') !== env('JOB_TOKEN')) {
            abort(403, 'Unauthorized');
        }

        // Create dedicated log file for this processing session
        $logFile = EmailLogService::createLogFile('processor');

        $startTime = time();
        $maxExecutionTime = 240; // 4 minutes limit like other jobs
        $processedCount = 0;
        $maxEmailsPerSecond = 2;

        try {
            EmailLogService::logProcessor("=== AVVIO PROCESSORE EMAIL ===", 'INFO', $logFile);
            // Get pending email jobs from database
            $pendingJobs = \DB::table('jobs')
                ->where('queue', 'emails')
                ->orderBy('available_at', 'asc')
                ->limit(50) // Process max 50 jobs per run
                ->get();

            EmailLogService::logProcessor("Job in coda trovati: {$pendingJobs->count()}", 'INFO', $logFile);

            if ($pendingJobs->isEmpty()) {
                EmailLogService::logProcessor("Nessun job email in coda - terminazione", 'INFO', $logFile);
                \Log::info('No pending email jobs to process');
                return;
            }

            EmailLogService::logProcessor("Inizio elaborazione {$pendingJobs->count()} job email", 'INFO', $logFile);
            \Log::info("Processing {$pendingJobs->count()} email jobs");

            foreach ($pendingJobs as $jobRecord) {
                // Check execution time limit
                if ((time() - $startTime) > $maxExecutionTime) {
                    \Log::info("Email queue processor: Time limit reached, processed {$processedCount} jobs");

                    // Restart the process if there are more jobs
                    $remainingJobs = \DB::table('jobs')
                        ->where('queue', 'emails')
                        ->count();

                    EmailLogService::logProcessor("Timeout raggiunto dopo {$processedCount} email elaborate", 'WARNING', $logFile);

                    if ($remainingJobs > 0) {
                        EmailLogService::logProcessor("Riavvio processore per {$remainingJobs} job rimanenti", 'INFO', $logFile);
                        self::fireAndForgetGet(route('job.processEmailQueue'), [
                            'token' => env('JOB_TOKEN')
                        ]);
                    }
                    return;
                }

                try {
                    // Apply rate limiting
                    $this->applyEmailRateLimit($maxEmailsPerSecond, $logFile);

                    // Process the job
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['displayName'] ?? null;

                    EmailLogService::logProcessor("Elaborazione job ID: {$jobRecord->id} - Classe: {$jobClass}", 'INFO', $logFile);

                    if ($jobClass === 'App\\Jobs\\SendQueuedEmail') {
                        $jobData = unserialize($payload['data']['command']);

                        try {
                            // Execute the email sending directly
                            $jobData->handle();

                            // Remove the job from queue on success
                            \DB::table('jobs')->where('id', $jobRecord->id)->delete();

                        } catch (\Exception $e) {
                            EmailLogService::logError('Job Processing', $e, [
                                'job_id' => $jobRecord->id,
                                'job_class' => $jobClass
                            ]);

                            // Remove job and mark as failed
                            \DB::table('jobs')->where('id', $jobRecord->id)->delete();
                            \DB::table('failed_jobs')->insert([
                                'uuid' => Str::uuid(),
                                'connection' => 'database',
                                'queue' => 'emails',
                                'payload' => $jobRecord->payload,
                                'exception' => $e->getMessage(),
                                'failed_at' => now()
                            ]);
                        }

                        $processedCount++;
                        EmailLogService::logProcessor("✅ Job {$jobRecord->id} completato con successo", 'INFO', $logFile);
                        \Log::info("Email job {$jobRecord->id} processed successfully");
                    }

                } catch (\Exception $e) {
                    EmailLogService::logError('Process Email Job', $e, [
                        'job_id' => $jobRecord->id,
                        'job_class' => $jobClass ?? 'unknown'
                    ]);

                    \Log::error("Error processing email job {$jobRecord->id}: " . $e->getMessage());

                    // Handle job failure
                    $this->handleFailedEmailJob($jobRecord, $e);
                }
            }

            EmailLogService::logProcessor("=== COMPLETAMENTO PROCESSORE EMAIL ===", 'INFO', $logFile);
            EmailLogService::logProcessor("Job elaborati: {$processedCount}", 'INFO', $logFile);
            \Log::info("Email queue processing completed: {$processedCount} jobs processed");

            // Check if there are more jobs to process
            $remainingJobs = \DB::table('jobs')
                ->where('queue', 'emails')
                ->count();

            if ($remainingJobs > 0) {
                EmailLogService::logProcessor("Riavvio processore per {$remainingJobs} job rimanenti", 'INFO', $logFile);
                // Schedule next processing cycle
                self::fireAndForgetGet(route('job.processEmailQueue'), [
                    'token' => env('JOB_TOKEN')
                ]);
            } else {
                EmailLogService::logProcessor("Tutti i job completati - nessun riavvio necessario", 'INFO', $logFile);
            }

        } catch (\Exception $e) {
            EmailLogService::logError('Email Queue Processor', $e, [
                'processed_count' => $processedCount ?? 0,
                'execution_time' => (time() - $startTime) . 's'
            ]);

            \Log::error('Email queue processor error: ' . $e->getMessage());
            \App\Events\MessageCreated::dispatch('Errore processore coda email: ' . $e->getMessage());
        }
    }

    /**
     * Apply rate limiting for email sending
     * Applica rate limiting per l'invio email
     *
     * @param int $maxPerSecond Maximum emails per second
     * @param string|null $logFile Optional log file for detailed logging
     * @return void
     */
    private function applyEmailRateLimit(int $maxPerSecond, ?string $logFile = null)
    {
        $cacheKey = 'email_rate_limit';
        $currentSecond = now()->format('Y-m-d H:i:s');

        // Use atomic increment to prevent race conditions
        $currentCount = \Cache::increment($cacheKey . ':' . $currentSecond, 1);

        // Set expiration if this is the first increment
        if ($currentCount === 1) {
            \Cache::put($cacheKey . ':' . $currentSecond, 1, 5);
        }

        if ($currentCount > $maxPerSecond) {
            if ($logFile) {
                EmailLogService::logProcessor("Rate limit raggiunto ({$currentCount}/{$maxPerSecond}) - attesa 1 secondo", 'WARNING', $logFile);
            }

            // Wait until next second if limit exceeded
            sleep(1);

            // Reset for next second
            $nextSecond = now()->format('Y-m-d H:i:s');
            \Cache::put($cacheKey . ':' . $nextSecond, 1, 5);

            if ($logFile) {
                EmailLogService::logProcessor("Rate limit reset - nuovo secondo: {$nextSecond}", 'INFO', $logFile);
            }
        }
    }

    /**
     * Handle failed email job
     * Gestisce job email fallito
     *
     * @param object $jobRecord The failed job record
     * @param \Exception $exception The exception that caused the failure
     * @return void
     */
    private function handleFailedEmailJob($jobRecord, \Exception $exception)
    {
        $attempts = $jobRecord->attempts + 1;
        $maxAttempts = 3;

        if ($attempts >= $maxAttempts) {
            // Move to failed jobs table
            \DB::table('failed_jobs')->insert([
                'uuid' => Str::uuid(),
                'connection' => 'database',
                'queue' => 'emails',
                'payload' => $jobRecord->payload,
                'exception' => $exception->getMessage() . "\n" . $exception->getTraceAsString(),
                'failed_at' => now()
            ]);

            // Remove from jobs table
            \DB::table('jobs')->where('id', $jobRecord->id)->delete();

            \Log::error("Email job {$jobRecord->id} failed permanently after {$attempts} attempts");
            \App\Events\MessageCreated::dispatch("Job email fallito definitivamente: {$exception->getMessage()}");

        } else {
            // Retry with backoff
            $backoffSeconds = [30, 60, 120][$attempts - 1] ?? 120;
            $retryAt = now()->addSeconds($backoffSeconds)->timestamp;

            \DB::table('jobs')
                ->where('id', $jobRecord->id)
                ->update([
                    'attempts' => $attempts,
                    'available_at' => $retryAt
                ]);

            \Log::info("Email job {$jobRecord->id} scheduled for retry in {$backoffSeconds} seconds (attempt {$attempts})");
        }
    }
}
