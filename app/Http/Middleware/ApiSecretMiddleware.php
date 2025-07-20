<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSecretMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiSecret = config('app.api_secret', 'your-default-secret-key');

        // Check for secret in header
        $providedSecret = $request->header('X-API-Secret');

        // If not in header, check in request body or query parameter
        if (!$providedSecret) {
            $providedSecret = $request->input('api_secret');
        }

        // Validate the secret
        if (!$providedSecret || $providedSecret !== $apiSecret) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing API secret'
            ], 401);
        }

        return $next($request);
    }
}
