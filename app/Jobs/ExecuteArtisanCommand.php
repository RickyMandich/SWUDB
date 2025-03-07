<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

class ExecuteArtisanCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    
    private $command;
    private $parameters;
    
    public function __construct(string $command, array $parameters = [])
    {
        $this->command = $command;
        $this->parameters = $parameters;
    }
    
    public function handle()
    {
        Artisan::call($this->command, $this->parameters);
    }
}