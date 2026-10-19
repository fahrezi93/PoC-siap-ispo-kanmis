<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        $validKey = config('finance_sync.gateway_key');

        if (! is_string($apiKey) || ! is_string($validKey) || $validKey === '' || ! hash_equals($validKey, $apiKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan.',
            ], 401);
        }

        return $next($request);
    }
}
