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
            // Se l'utente è admin, mostra la pagina di errore dettagliata di Laravel
            if (Auth::admin()) {
                // Per gli admin, usa il renderer di debug di Laravel/Whoops
                try {
                    // Usa Whoops per il rendering dettagliato dell'errore
                    if (class_exists(\Whoops\Run::class)) {
                        $whoops = new \Whoops\Run;
                        $whoops->allowQuit(false);
                        $whoops->writeToOutput(false);

                        $handler = new \Whoops\Handler\PrettyPageHandler;
                        $handler->setPageTitle("Errore per Admin - SWUDB");
                        $whoops->pushHandler($handler);

                        $content = $whoops->handleException($e);
                        return response($content, 500, ['Content-Type' => 'text/html']);
                    } else {
                        // Fallback: mostra errore con dettagli base
                        $content = '<h1>Errore per Admin</h1>';
                        $content .= '<h2>' . get_class($e) . '</h2>';
                        $content .= '<p><strong>Messaggio:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                        $content .= '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
                        $content .= '<p><strong>Linea:</strong> ' . $e->getLine() . '</p>';
                        $content .= '<h3>Stack Trace:</h3>';
                        $content .= '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';

                        return response($content, 500, ['Content-Type' => 'text/html']);
                    }
                } catch (\Exception $renderException) {
                    // Se anche il rendering fallisce, mostra almeno l'errore base
                    $content = '<h1>Errore per Admin (Rendering Failed)</h1>';
                    $content .= '<p>Errore originale: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    $content .= '<p>Errore di rendering: ' . htmlspecialchars($renderException->getMessage()) . '</p>';

                    return response($content, 500, ['Content-Type' => 'text/html']);
                }
            }

            // Per gli utenti non admin, usa le view personalizzate (comportamento attuale)
            return null;
        });
    })->create();
