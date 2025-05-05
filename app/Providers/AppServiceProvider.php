<?php

namespace App\Providers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\URL;

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
    public function boot(): void{
        // Soluzione semplificata per HTTPS con ngrok
    if (str_contains(request()->getHost(), 'ngrok')) {
        URL::forceScheme('https');
        
        // Forza il trust proxy per ngrok
        if (method_exists(Request::class, 'setTrustedProxies')) {
            request()->setTrustedProxies(
                ['*'], 
                Request::HEADER_X_FORWARDED_FOR | 
                Request::HEADER_X_FORWARDED_HOST | 
                Request::HEADER_X_FORWARDED_PORT | 
                Request::HEADER_X_FORWARDED_PROTO
            );
        }
    }
    }
}
