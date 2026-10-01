<?php

namespace App\Providers;

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
    public function boot(): void
    {
        // Source - https://stackoverflow.com/a/51819095
        // Posted by Amitesh Bharti, modified by community. See post 'Timeline' for change history
        // Retrieved 2026-10-01, License - CC BY-SA 4.0
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }
    }
}
