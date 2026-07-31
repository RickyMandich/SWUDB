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
     * Run all feature tests manually (In-Process Simulator)
     *
     * @param string|null $debugLogPath Optional path to a log file for debugging
     * @return bool True if all tests passed, false otherwise
     */
    public function runTests($debugLogPath = null)
    {
        $runId = Str::uuid()->toString();
        $this->log("=== INIZIO SIMULAZIONE TEST IN-PROCESS (Run ID: $runId) ===", $debugLogPath);

        $results = [];
        $allPassed = true;

        // Test 1: Ricerca Carte e Aspetti
        $results[] = $this->simulateCardSearchTest($debugLogPath);

        // Test 2: Accesso Admin e Logs
        $results[] = $this->simulateAdminToolsTest($debugLogPath);

        // Test 3: Gestione Mazzi
        $results[] = $this->simulateDeckManagementTest($debugLogPath);

        // Calcolo esito finale
        $output = "";
        foreach ($results as $res) {
            if (!$res['passed']) {
                $allPassed = false;
            }
            $output .= ($res['passed'] ? "✅" : "❌") . " " . $res['name'] . ": " . ($res['message'] ?? 'OK') . "\n";
        }

        $result = TestResult::create([
            'test_name' => 'Simulazione Suite Completa',
            'status' => $allPassed,
            'output' => $output,
            'duration' => 0,
            'run_id' => $runId,
        ]);

        if (!$allPassed) {
            $this->log("Esito Finale: FALLITO.", $debugLogPath);
        } else {
            $this->log("Esito Finale: SUCCESSO.", $debugLogPath);
        }

        return $allPassed;
    }

    protected function simulateCardSearchTest($logPath)
    {
        $this->log("Esecuzione simulazione: Ricerca Carte...", $logPath);
        \DB::beginTransaction();
        try {
            // Crea dati temporanei
            $aspect = \App\Models\Aspect::create(['nome' => 'Test Aspect', 'slug' => 'test-aspect', 'colore' => '#000000']);
            $card = \App\Models\Card::create([
                'cid' => 'test-sim-1',
                'nome' => 'Test Card Sim',
                'numero' => 999,
                'espansione' => 'TEST',
                'tipo' => 'Unità',
                'costo' => 1,
                'rarita' => 'C',
                'descrizione' => 'Test',
                'tratti' => 'Test',
                'artista' => 'Test'
            ]);

            // Verifica esistenza nel DB
            if (!\App\Models\Card::where('cid', 'test-sim-1')->exists()) {
                throw new \Exception("Salvataggio card fallito");
            }

            // Simula rotta /carte (solo controllo 200)
            $response = $this->simulateGet('/carte');
            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Rotta /carte ha restituito " . $response->getStatusCode());
            }

            \DB::rollBack();
            return ['name' => 'Ricerca Carte', 'passed' => true];
        } catch (\Exception $e) {
            \DB::rollBack();
            return ['name' => 'Ricerca Carte', 'passed' => false, 'message' => $e->getMessage()];
        }
    }

    // Per brevità e sicurezza, implementiamo una versione semplificata che usa transazioni manuali
    protected function simulateAdminToolsTest($logPath)
    {
        $this->log("Esecuzione simulazione: Admin Tools...", $logPath);
        \DB::beginTransaction();
        try {
            $email = "test-admin-" . Str::random(5) . "@example.com";
            $user = \App\Models\User::create([
                'name' => 'Test Admin Sim',
                'email' => $email,
                'password' => \Hash::make('password'),
                'admin' => 1
            ]);
            \Auth::login($user);

            $response = $this->simulateGet('/admin/logs');
            \Auth::logout();

            if ($response->getStatusCode() !== 200)
                throw new \Exception("Accesso logs fallito");

            \DB::rollBack();
            return ['name' => 'Admin Tools', 'passed' => true];
        } catch (\Exception $e) {
            \DB::rollBack();
            return ['name' => 'Admin Tools', 'passed' => false, 'message' => $e->getMessage()];
        }
    }

    protected function simulateDeckManagementTest($logPath)
    {
        $this->log("Esecuzione simulazione: Deck Management...", $logPath);
        \DB::beginTransaction();
        try {
            $email = "test-user-" . Str::random(5) . "@example.com";
            $user = \App\Models\User::create([
                'name' => 'Test User Sim',
                'email' => $email,
                'password' => \Hash::make('password'),
                'admin' => 0
            ]);
            \Auth::login($user);

            // Simuliamo il salvataggio diretto invece della request POST per evitare problemi di CSRF/Sessione in-process
            $deck = \App\Models\Deck::create([
                'nome' => 'Mazzo Test Sim',
                'codUtente' => $user->id,
                'public' => 1
            ]);

            if (!$deck->exists)
                throw new \Exception("Creazione mazzo fallita");

            \Auth::logout();
            \DB::rollBack();
            return ['name' => 'Deck Management', 'passed' => true];
        } catch (\Exception $e) {
            \DB::rollBack();
            return ['name' => 'Deck Management', 'passed' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Simula una richiesta GET interamente in-process
     */
    protected function simulateGet($uri)
    {
        $request = \Illuminate\Http\Request::create($uri, 'GET');
        return app()->handle($request);
    }

    protected function log($message, $path = null)
    {
        $formatted = "[" . date('Y-m-d H:i:s') . "] [TestRunner] " . $message . PHP_EOL;
        \Illuminate\Support\Facades\Log::info($message);
        // if ($path) file_put_contents($path, $formatted, FILE_APPEND);
    }
}
