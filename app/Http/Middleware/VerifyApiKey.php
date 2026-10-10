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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        $validKey = env('API_GATEWAY_KEY');

        if (empty($apiKey) || $apiKey !== $validKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan.'
            ], 401);
        }

        return $next($request);
    }
}
