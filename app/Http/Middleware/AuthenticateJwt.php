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
            return ApiResponse::error(__('errors.auth.missing_bearer_token'), HttpStatus::UNAUTHORIZED['code']);
        }

        try {
            $claims = $this->verifier->verify($token);
        } catch (InvalidArgumentException) {
            return ApiResponse::error(__('errors.auth.invalid_token'), HttpStatus::UNAUTHORIZED['code']);
        } catch (LogicException) {
            return ApiResponse::error(
                __('errors.auth.not_configured'),
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }

        if (isset($claims['sid'])) {
            $session = is_string($claims['sid']) ? $this->sessions->active($claims['sid']) : null;

            if (! $session) {
                return ApiResponse::error(
                    __('errors.auth.portal_session_ended'),
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
            if (! config('mock_login.student_enabled') || ($claims['role'] ?? null) !== ['student']) {
                return ApiResponse::error(__('errors.auth.portal_session_required'), HttpStatus::UNAUTHORIZED['code']);
            }

            if (! $this->isStudentRoute($request)) {
                return ApiResponse::error(__('errors.auth.student_endpoint_forbidden'), HttpStatus::FORBIDDEN['code']);
            }
        }

        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }

    private function isStudentRoute(Request $request): bool
    {
        $endpoint = $request->method().' '.$request->route()?->uri();

        return in_array($endpoint, config('mock_login.student_routes'), true);
    }
}
