<?php

namespace App\Http\Middleware;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Services\JwtVerifier;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private readonly JwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return ApiResponse::error('Missing Bearer token', HttpStatus::UNAUTHORIZED['code']);
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), HttpStatus::UNAUTHORIZED['code']);
        } catch (LogicException) {
            return ApiResponse::error(
                'JWT authentication is not configured',
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }

        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }
}
