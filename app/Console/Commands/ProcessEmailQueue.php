<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Events\MessageCreated;

/**
 * Command to process the email queue with proper rate limiting
 * Comando per processare la coda email con rate limiting appropriato
 *
 * This command starts a queue worker specifically for the 'emails' queue
 * to ensure emails are processed with the configured rate limiting.
 */
class ProcessEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process-queue 
                            {--timeout=60 : The number of seconds a child process can run}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--tries=3 : Number of times to attempt a job before logging it failed}
                            {--daemon : Run the worker in daemon mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process the email queue with rate limiting to prevent overflow';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting email queue processor...');
        
        // Log the start of email queue processing
        MessageCreated::dispatch('Avvio processore coda email');
        Log::info('Email queue processor started');
        
        try {
            $options = [
                '--queue' => 'emails',
                '--timeout' => $this->option('timeout'),
                '--sleep' => $this->option('sleep'),
                '--tries' => $this->option('tries'),
            ];
            
            if ($this->option('daemon')) {
                $options['--daemon'] = true;
                $this->info('Running in daemon mode...');
            }
            
            // Start the queue worker for emails
            $exitCode = Artisan::call('queue:work', $options);
            
            if ($exitCode === 0) {
                $this->info('Email queue processor completed successfully');
                MessageCreated::dispatch('Processore coda email completato con successo');
            } else {
                $this->error('Email queue processor exited with code: ' . $exitCode);
                MessageCreated::dispatch('Processore coda email terminato con errore: ' . $exitCode);
            }
            
            return $exitCode;
            
        } catch (\Exception $e) {
            $this->error('Error processing email queue: ' . $e->getMessage());
            MessageCreated::dispatch('Errore processore coda email: ' . $e->getMessage());
            Log::error('Email queue processor error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}
