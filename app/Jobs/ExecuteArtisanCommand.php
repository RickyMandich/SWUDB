<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * Job for executing Artisan commands asynchronously in the queue
 * Job per eseguire comandi Artisan in modo asincrono nella coda
 *
 * This job allows running Laravel Artisan commands in the background
 * through the queue system, useful for long-running or resource-intensive tasks.
 */
class ExecuteArtisanCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private $command;
    private $parameters;

    /**
     * Create a new job instance
     * Crea una nuova istanza del job
     *
     * @param string $command The Artisan command to execute
     * @param array $parameters Command parameters and options
     */
    public function __construct(string $command, array $parameters = [])
    {
        $this->command = $command;
        $this->parameters = $parameters;
    }

    /**
     * Execute the job by running the specified Artisan command
     * Esegue il job lanciando il comando Artisan specificato
     *
     * @return void
     */
    public function handle()
    {
        Artisan::call($this->command, $this->parameters);
    }
}