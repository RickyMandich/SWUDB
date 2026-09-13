<?php

namespace App\Console\Commands;

use App\Jobs\ImportCardsFromSwuApiJob;
use Illuminate\Console\Command;

class ScanCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cards:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan cards from StarWarsUnlimited API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ImportCardsFromSwuApiJob::dispatch();
        $this->info('Cards scan queued.');
    }
}
