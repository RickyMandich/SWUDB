<?php

use App\Events\MessageCreated;
use App\Mail\ErrorNotificationEmail;
use App\Models\User;
use App\Models\SystemError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents()
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (Throwable $e) {
            // Save error to database for admin management
            // Salva l'errore nel database per la gestione admin
            $systemError = null;
            try {
                $systemError = SystemError::create([
                    'exception_class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request_url' => request()->fullUrl() ?? null,
                    'request_method' => request()->method() ?? null,
                    'user_agent' => request()->userAgent() ?? null,
                    'user_id' => Auth::id(),
                    'status' => 'new',
                ]);
            } catch (\Exception $dbException) {
                // If database save fails, log it but don't break the error handling
                // Se il salvataggio nel database fallisce, registralo ma non interrompere la gestione errori
                \Log::error("Failed to save error to database: " . $dbException->getMessage());
            }

            // Invia messaggio Telegram
            MessageCreated::dispatch("Errore: " . $e->getMessage());

            // Invia email a tutti gli admin
            try {
                $admins = User::getAdmins();
                if ($admins->isNotEmpty()) {
                    $requestUrl = request()->fullUrl() ?? null;
                    $requestMethod = request()->method() ?? null;
                    $userAgent = request()->userAgent() ?? null;

                    foreach ($admins as $admin) {
                        Mail::to($admin->email)->send(
                            new ErrorNotificationEmail($e, $requestUrl, $requestMethod, $userAgent, $systemError)
                        );
                    }
                }
            } catch (\Exception $mailException) {
                // Se l'invio email fallisce, invia solo un messaggio Telegram aggiuntivo
                MessageCreated::dispatch("Errore invio email admin: " . $mailException->getMessage());
            }
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
