<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     * Ensure the user's email is verified before proceeding
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && !Auth::user()->isEmailVerified()) {
            return redirect()->route('email.resend.form')
                ->with('error', 'Devi verificare la tua email per accedere a questa sezione.');
        }

        return $next($request);
    }
}
