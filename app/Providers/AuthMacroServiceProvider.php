<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
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
            // Example using a dedicated admin guard:
            // return Auth::guard('admin')->check();

            // Example using an `is_admin` boolean column on the default user:
            $user = Auth::user();
            return $user && $user->is_admin; // change attribute name if different
        });
    }
}
