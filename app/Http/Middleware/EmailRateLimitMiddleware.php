<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\MessageCreated;

/**
 * Middleware to prevent email abuse by rate limiting email-triggering actions
 * Middleware per prevenire abusi email limitando le azioni che scatenano email
 *
 * This middleware can be applied to routes that trigger email sending
 * to prevent users from overwhelming the email system.
 */
class EmailRateLimitMiddleware
{
    /**
     * Handle an incoming request
     * Gestisce una richiesta in arrivo
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param int $maxAttempts Maximum attempts per time window
     * @param int $decayMinutes Time window in minutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 5, int $decayMinutes = 60)
    {
        $key = $this->resolveRequestSignature($request);
        
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            $this->logRateLimitExceeded($request, $key);
            
            return response()->json([
                'error' => 'Troppi tentativi. Riprova tra qualche minuto.',
                'retry_after' => $this->getRetryAfter($key)
            ], 429);
        }
        
        $this->incrementAttempts($key, $decayMinutes);
        
        return $next($request);
    }
    
    /**
     * Resolve request signature for rate limiting
     * Risolve la firma della richiesta per il rate limiting
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        $ip = $request->ip();
        $route = $request->route()->getName() ?? $request->path();
        
        // Use user ID if authenticated, otherwise use IP
        $identifier = $user ? "user:{$user->id}" : "ip:{$ip}";
        
        return "email_action_limit:{$identifier}:{$route}";
    }
    
    /**
     * Determine if the given key has been "accessed" too many times
     * Determina se la chiave data è stata "acceduta" troppe volte
     *
     * @param string $key
     * @param int $maxAttempts
     * @return bool
     */
    protected function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return Cache::get($key, 0) >= $maxAttempts;
    }
    
    /**
     * Increment the counter for a given key
     * Incrementa il contatore per una chiave data
     *
     * @param string $key
     * @param int $decayMinutes
     * @return void
     */
    protected function incrementAttempts(string $key, int $decayMinutes): void
    {
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, now()->addMinutes($decayMinutes));
    }
    
    /**
     * Get the number of seconds until the next retry
     * Ottiene il numero di secondi fino al prossimo tentativo
     *
     * @param string $key
     * @return int
     */
    protected function getRetryAfter(string $key): int
    {
        // Get the cache expiration time
        $cacheKey = Cache::getStore()->getPrefix() . $key;
        
        // Default to 60 seconds if we can't determine exact time
        return 60;
    }
    
    /**
     * Log when rate limit is exceeded
     * Registra quando il rate limit viene superato
     *
     * @param \Illuminate\Http\Request $request
     * @param string $key
     * @return void
     */
    protected function logRateLimitExceeded(Request $request, string $key): void
    {
        $user = $request->user();
        $userInfo = $user ? "User ID: {$user->id} ({$user->email})" : "IP: {$request->ip()}";
        $route = $request->route()->getName() ?? $request->path();
        
        $message = "Rate limit superato per azioni email - {$userInfo} - Route: {$route}";
        
        MessageCreated::dispatch($message);
        
        Log::warning('Email action rate limit exceeded', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip' => $request->ip(),
            'route' => $route,
            'user_agent' => $request->userAgent(),
            'key' => $key
        ]);
    }
}
