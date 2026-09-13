<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportCardsFromSwuApiJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. chiama l'API ufficiale SWU (Http::get(...))
        // 2. per ogni carta ricevuta, updateOrCreate su Card usando external_id come chiave
        // 3. dispaccia NotifyAdminJob con il riepilogo (nuove carte trovate, eventuali errori)
    }
}
