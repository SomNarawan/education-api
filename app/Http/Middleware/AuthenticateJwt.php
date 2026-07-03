<?php

namespace App\Http\Middleware;

use App\Helpers\ApiResponse;
use App\Services\JwtVerifier;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private readonly JwtVerifier $verifier)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return ApiResponse::error('Missing Bearer token', 401);
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 401);
        } catch (LogicException) {
            return ApiResponse::error('JWT authentication is not configured', 500);
        }

        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }
}
