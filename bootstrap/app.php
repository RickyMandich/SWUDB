<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Carica le variabili non sensibili (es. APP_VERSION_*) da .env-overrides,
// file tracciato in Git a differenza di .env. Va fatto PRIMA che Laravel
// processi il suo .env principale: il repository usato da Laravel per
// leggere il .env e' immutabile e non sovrascrive variabili gia' presenti
// in $_ENV/$_SERVER, quindi impostandole qui vincono su quelle (se presenti)
// nel .env vero e proprio.
\Dotenv\Dotenv::createMutable(dirname(__DIR__), '.env-overrides')->safeLoad();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
