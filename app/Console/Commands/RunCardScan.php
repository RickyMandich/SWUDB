<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunCardScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Esegue la scansione automatica delle carte (equivalente al comando /scan da Telegram)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // I due parametri recuperati dal .env
        $username = env('TELEGRAM_ADMIN_USERNAME');
        $chatId = env('TELEGRAM_CHAT_ID');

        $this->info("Avvio scansione automatica ({$username}, chat {$chatId})...");

        (new \App\Http\Controllers\TelegramController())->executeScanCommand($username, $chatId);

        $this->info('Scansione avviata (i messaggi di progresso arrivano via thread Telegram come al solito).');

        return self::SUCCESS;
    }
}
