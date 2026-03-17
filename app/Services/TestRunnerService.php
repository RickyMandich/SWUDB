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

        // Mock dell'ambiente CLI per Collision/PHPUnit in contesto web
        // Questo risolve l'errore "Undefined array key 'argv'"
        if (!isset($_SERVER['argv'])) {
            $_SERVER['argv'] = [base_path('artisan'), 'test'];
            $_SERVER['argc'] = count($_SERVER['argv']);
        }

        $startTime = microtime(true);
        
        // Eseguiamo i test tramite Artisan
        $exitCode = Artisan::call('test', ["--log-junit" => $logPath]);
        
        $duration = microtime(true) - $startTime;

        $output = Artisan::output();
        $passed = ($exitCode === 0);
        
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
