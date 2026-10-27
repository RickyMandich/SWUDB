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
    protected $signature = 'cards:scan {--page=1 : Pagina da cui far partire la scansione}';

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
        $page = max(1, (int) $this->option('page'));
        ImportCardsFromSwuApiJob::dispatch($page);
        $this->info("Cards scan queued from page {$page}.");
    }
}
