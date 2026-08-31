<?php

namespace App\Http\Middleware;

use App\Constants\HttpStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePortalMainApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('portal_main_api.api_key');
        $providedKey = $request->header('X-API-KEY');

        if (! $configuredKey || ! $providedKey || ! hash_equals((string) $configuredKey, (string) $providedKey)) {
            return response()->json([
                'message' => 'Invalid or missing API key',
            ], HttpStatus::UNAUTHORIZED['code']);
        }

        return $next($request);
    }
}
