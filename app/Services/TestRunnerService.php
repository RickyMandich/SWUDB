<?php

namespace App\Services;

use App\Models\TestResult;
use App\Models\User;
use App\Mail\TestFailedMail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TestRunnerService
{
    /**
     * Run all feature tests and log results
     *
     * @param string|null $debugLogPath Optional path to a log file for debugging
     * @return bool True if all tests passed, false otherwise
     */
    public function runTests($debugLogPath = null)
    {
        $runId = Str::uuid()->toString();
        $logPath = storage_path("logs/tests/{$runId}.xml");
        
        $this->log("Inizio esecuzione test (Run ID: $runId)", $debugLogPath);

        // Assicuriamoci che la directory dei log esista
        if (!file_exists(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        // Mock dell'ambiente CLI per Collision/PHPUnit in contesto web
        // Questo risolve l'errore "Undefined array key 'argv'" e "Undefined constant 'STDOUT'"
        if (!isset($_SERVER['argv'])) {
            $this->log("Simulazione ambiente CLI (argv/argc)", $debugLogPath);
            $_SERVER['argv'] = [base_path('artisan'), 'test'];
            $_SERVER['argc'] = count($_SERVER['argv']);
        }

        if (!defined('STDOUT')) {
            $this->log("Definizione costante STDOUT", $debugLogPath);
            define('STDOUT', fopen('php://stdout', 'w'));
        }
        if (!defined('STDERR')) {
            $this->log("Definizione costante STDERR", $debugLogPath);
            define('STDERR', fopen('php://stderr', 'w'));
        }
        if (!defined('STDIN')) {
            $this->log("Definizione costante STDIN", $debugLogPath);
            define('STDIN', fopen('php://stdin', 'r'));
        }

        $startTime = microtime(true);
        
        try {
            $this->log("Lancio Artisan::call('test')...", $debugLogPath);
            
            // Eseguiamo i test tramite Artisan
            // NOTA: In alcuni ambienti web, questo potrebbe hangare se ci sono conflitti di sessione o DB
            $exitCode = Artisan::call('test', ["--log-junit" => $logPath]);
            
            $this->log("Artisan::call completato. Exit code: $exitCode", $debugLogPath);
        } catch (\Throwable $e) {
            $this->log("❌ ERRORE FATALE durante l'esecuzione dei test: " . $e->getMessage(), $debugLogPath);
            $this->log("Stack trace: " . substr($e->getTraceAsString(), 0, 500) . "...", $debugLogPath);
            
            // Registriamo l'errore nel database ma senza inviare mail per ora (debug)
            TestResult::create([
                'test_name' => 'Errore Esecuzione Suite',
                'status' => false,
                'output' => "Eccezione: " . $e->getMessage() . "\n" . $e->getTraceAsString(),
                'duration' => 0,
                'run_id' => $runId,
            ]);
            
            return false;
        }
        
        $duration = microtime(true) - $startTime;

        $output = Artisan::output();
        $passed = ($exitCode === 0);
        
        $this->log("Salvataggio risultati nel database (Esito: " . ($passed ? 'PASS' : 'FAIL') . ")", $debugLogPath);

        $result = TestResult::create([
            'test_name' => 'Suite Completa Feature Tests',
            'status' => $passed,
            'output' => $output,
            'duration' => round($duration, 2),
            'run_id' => $runId,
        ]);

        if (!$passed) {
            // Notifichiamo gli admin solo se non siamo in debug (o come preferisce l'utente)
            // L'utente ha chiesto di aggiungere l'errore senza mail/telegram per ora
            $this->log("Test falliti. Notifiche disabilitate in modalità debug/recupero.", $debugLogPath);
            // $this->notifyAdmins($result);
        }

        return $passed;
    }

    /**
     * Helper per il logging
     */
    protected function log($message, $path = null)
    {
        $formatted = "[" . date('Y-m-d H:i:s') . "] [TestRunner] " . $message . PHP_EOL;
        \Illuminate\Support\Facades\Log::info($message);
        
        if ($path) {
            file_put_contents($path, $formatted, FILE_APPEND);
        }
    }

    /**
     * Notify all admins about a failed test
     */
    protected function notifyAdmins(TestResult $result)
    {
        $admins = User::getAdmins();
        
        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new TestFailedMail($result));
        }
    }
}
