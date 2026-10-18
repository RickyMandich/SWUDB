<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAdminJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $message) {}

    public function handle(TelegramService $telegram): void
    {
        $telegram->sendMessage(config('services.telegram.admin_chat_id'), $this->message);
    }
}
