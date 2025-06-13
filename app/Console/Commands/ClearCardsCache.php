<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Card;

class ClearCardsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cards:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulisce la cache delle carte per migliorare le performance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Pulizia cache delle carte in corso...');

        Card::clearCache();

        $this->info('Cache delle carte pulita con successo!');

        return 0;
    }
}
