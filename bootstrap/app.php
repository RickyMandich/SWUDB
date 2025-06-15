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
            // Se l'utente è admin, renderizza con layout dell'app
            if (Auth::admin()) {
                // Forza APP_DEBUG=true per gli admin
                config(['app.debug' => true]);

                // Renderizza usando il layout dell'app con dettagli dell'errore
                return response()->view('errors.admin-debug', [
                    'exception' => $e,
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage(),
                    'request' => $request
                ], 500);
            }

            // Per gli utenti non admin, assicurati che debug sia false
            config(['app.debug' => false]);

            // Restituisce null per usare le view personalizzate
            return null;
        });
    })->create();
