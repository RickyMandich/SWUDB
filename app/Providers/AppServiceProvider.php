<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Main application service provider for SWUDB
 * Provider di servizi principale dell'applicazione per SWUDB
 *
 * This provider handles application-wide service registration and bootstrapping,
 * including URL scheme configuration for production deployment.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services in the container
     * Registra tutti i servizi dell'applicazione nel container
     *
     * This method is called during the application bootstrap process
     * to register services that need to be available throughout the app.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services and configure global settings
     * Avvia tutti i servizi dell'applicazione e configura le impostazioni globali
     *
     * This method is called after all services have been registered
     * and is used to configure application-wide settings.
     *
     * @return void
     */
    public function boot(): void
    {
        // Force HTTPS for all generated links in production
        // Forza HTTPS per tutti i link generati in produzione
        URL::forceScheme('https');
    }
}