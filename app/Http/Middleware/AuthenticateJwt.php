<?php

namespace App\Http\Middleware;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Services\JwtVerifier;
use App\Services\PortalSessionService;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(
        private readonly JwtVerifier $verifier,
        private readonly PortalSessionService $sessions,
    ) {}

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

        if (isset($claims['sid'])) {
            $session = is_string($claims['sid']) ? $this->sessions->active($claims['sid']) : null;

            if (! $session) {
                return ApiResponse::error(
                    'The portal session has ended; sign in again from the portal',
                    HttpStatus::UNAUTHORIZED['code'],
                    code: 'PORTAL_SESSION_ENDED'
                );
            }

            // Roles as last confirmed by get-role rather than as issued at login,
            // so every consumer of jwt_claims (MeController included) sees a role
            // withdrawn in ENG Main within PORTAL_RECHECK_SECONDS.
            $claims['role'] = $session->roles;

            if (! in_array($claims['current_role'] ?? null, $session->roles, true)) {
                $claims['current_role'] = $session->roles[0] ?? null;
            }

            $request->attributes->set('auth_session', $session);
        } elseif (! config('mock_login.enabled')) {
            // Only mock-login issues tokens without a portal session.
            return ApiResponse::error('Token is not tied to a portal session', HttpStatus::UNAUTHORIZED['code']);
        }

        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }
}
