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

        $exceptions->renderable(function (Throwable $e, $request) {
            // Se l'utente è admin, mostra la pagina di errore di default di Laravel con debug
            if (Auth::admin()) {
                // Usa il renderer di default di Laravel forzando il debug
                $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);

                // Forza temporaneamente il debug per questo rendering
                $originalDebug = config('app.debug');
                config(['app.debug' => true]);

                try {
                    // Usa il metodo interno di Laravel per preparare la risposta con debug attivo
                    $method = new \ReflectionMethod($handler, 'prepareResponse');
                    $method->setAccessible(true);

                    return $method->invoke($handler, $request, $e);
                } catch (\Exception $renderException) {
                    // Se il rendering fallisce, usa un fallback semplice
                    return response()->view('errors.500', ['exception' => $e], 500);
                } finally {
                    // Ripristina il valore originale
                    config(['app.debug' => $originalDebug]);
                }
            }

            // Per gli utenti non admin, usa le view personalizzate (comportamento attuale)
            return null;
        });
    })->create();
