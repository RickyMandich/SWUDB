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
        
        // Per semplicità e compatibilità, eseguiamo i test tramite Artisan
        // In produzione potrebbe servire un comando shell per catturare output dettagliato
        $exitCode = Artisan::call('test', ['--log-junit' => storage_path("logs/tests/{$runId}.xml")]);
        $output = Artisan::output();

        // Nota: Artisan::call('test') potrebbe non restituire l'output completo o log in tempo reale.
        // Se necessario, usiamo Symfony Process.
        
        $passed = ($exitCode === 0);

        // Salviamo il risultato generale (per ora come log complessivo o scorporato)
        // In un'implementazione reale, parseremmo il file JUnit XML per ogni test case.
        // Per ora salviamo un record per l'intera esecuzione.
        
        $result = TestResult::create([
            'test_name' => 'Suite Completa Feature Tests',
            'status' => $passed,
            'output' => $output,
            'duration' => 0, // Opzionale
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
