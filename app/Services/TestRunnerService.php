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
     * @return bool True if all tests passed, false otherwise
     */
    public function runTests()
    {
        $runId = Str::uuid()->toString();
        $logPath = storage_path("logs/tests/{$runId}.xml");
        
        // Assicuriamoci che la directory dei log esista
        if (!file_exists(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        // Lancia il comando 'php artisan test' come sottoprocesso CLI
        // Questo evita l'errore di variabili mancanti (come 'argv') tipico delle richieste web
        $process = new Process([PHP_BINARY, 'artisan', 'test', "--log-junit={$logPath}"]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout(300); // 5 minuti di timeout
        
        $startTime = microtime(true);
        $process->run();
        $duration = microtime(true) - $startTime;

        $output = $process->getOutput() ?: $process->getErrorOutput();
        $exitCode = $process->getExitCode();
        $passed = $process->isSuccessful();
        
        $result = TestResult::create([
            'test_name' => 'Suite Completa Feature Tests',
            'status' => $passed,
            'output' => $output,
            'duration' => round($duration, 2),
            'run_id' => $runId,
        ]);

        if (!$passed) {
            $this->notifyAdmins($result);
        }

        return $passed;
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
