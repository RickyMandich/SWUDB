<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\MessageCreated;
use App\Events\ThreadMessageCreated;
use App\Listeners\SendMessage;
use App\Listeners\SendThreadMessage;

/**
 * Event service provider for SWUDB application
 * Provider di servizi eventi per l'applicazione SWUDB
 *
 * This provider registers event listeners for the application,
 * including both regular messages and threaded messages for
 * better notification management during long-running processes.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application
     * Mappature degli event listener per l'applicazione
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        MessageCreated::class => [
            SendMessage::class,
        ],
        ThreadMessageCreated::class => [
            SendThreadMessage::class,
        ],
    ];

    /**
     * Register any events for your application
     * Registra tutti gli eventi per la tua applicazione
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered
     * Determina se eventi e listener dovrebbero essere scoperti automaticamente
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
