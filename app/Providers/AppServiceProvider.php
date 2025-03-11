<?php

namespace App\Providers;

use App\Events\CardReceived;
use App\Events\MessageCreated;

use App\Listeners\SendMessage;
use App\Listeners\AddCard;
use Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void{}
}
