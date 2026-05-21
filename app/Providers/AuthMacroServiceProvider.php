<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AuthMacroServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No bindings needed for the macro.
    }

    /**
     * Bootstrap any application services.
     *
     * Defines a convenient `Auth::admin()` macro that returns true when the
     * currently authenticated user is considered an administrator.
     *
     * Adjust the inner logic to reflect how your app determines admin status.
     * The simplest approach is to rely on an `is_admin` column on the User
     * model. If you have a dedicated admin guard, you can uncomment the first
     * return line and comment out the column‑based check.
     */
    public function boot(): void
    {
        Auth::macro('admin', function () {
            $user = Auth::user();
            $isAdmin = $user && ($user->admin == 1); // check admin column
            Log::info('Auth::admin check', [
                'user_id' => $user ? $user->id : null,
                'is_admin' => $isAdmin,
            ]);
            return $isAdmin;
        });
    }
}
