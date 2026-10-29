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
    protected $signature = 'cards:scan {--page=1 : Pagina da cui far partire la scansione} {--phase=originals : originals (completo) oppure variants}';

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
        $phase = (string) $this->option('phase');

        if (! in_array($phase, [ImportCardsFromSwuApiJob::PHASE_ORIGINALS, ImportCardsFromSwuApiJob::PHASE_VARIANTS], true)) {
            $this->error('--phase deve essere "originals" oppure "variants".');

            return self::FAILURE;
        }

        ImportCardsFromSwuApiJob::dispatch($page, null, $phase);
        $this->info("Cards scan queued from page {$page} (phase: {$phase}).");

        return self::SUCCESS;
    }
}
