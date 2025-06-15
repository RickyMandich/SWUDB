<?php

use App\Events\MessageCreated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents()
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (Throwable $e) {
            MessageCreated::dispatch("Errore: " . $e->getMessage());
        });

        // Intercetta il rendering per modificare il comportamento in base all'utente
        $exceptions->renderable(function (Throwable $e, $request) {
            // Se l'utente è admin, modifica globalmente APP_DEBUG per questa richiesta
            if (Auth::admin()) {
                // Forza APP_DEBUG=true per gli admin
                config(['app.debug' => true]);

                // Restituisce null per far procedere Laravel con il rendering di default
                // ma ora con debug=true, quindi mostrerà la pagina dettagliata originale
                return null;
            }

            // Per gli utenti non admin, assicurati che debug sia false
            config(['app.debug' => false]);

            // Restituisce null per usare le view personalizzate
            return null;
        });
    })->create();
