<?php

namespace App\Services;

use App\Models\AuthSession;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Keeps a portal login tied to ENG Main while it is used: every
 * PORTAL_RECHECK_SECONDS a request re-checks the roles (get-role) and the SSO
 * session (verify.php). A refusal ends the session; an unreachable central
 * system does not.
 */
class PortalSessionService
{
    public function __construct(private readonly PortalAuthService $portal) {}

    /**
     * The session while the portal login still stands, otherwise null.
     */
    public function active(string $id): ?AuthSession
    {
        $session = AuthSession::query()->find($id);

        if (! $session?->isActive()) {
            return null;
        }

        if (! $this->recheckDue($session)) {
            return $session;
        }

        // One re-check per session at a time. Requests arriving meanwhile carry
        // on with the state as last checked instead of waiting for ENG Main.
        $lock = Cache::lock("auth-recheck:{$session->id}", (int) config('portal_auth.timeout', 10) + 5);

        if (! $lock->get()) {
            return $session;
        }

        try {
            $session->refresh();

            if (! $session->isActive()) {
                return null;
            }

            return $this->recheckDue($session) ? $this->recheck($session) : $session;
        } finally {
            $lock->release();
        }
    }

    private function recheckDue(AuthSession $session): bool
    {
        return $session->checked_at->lte(now()->subSeconds((int) config('portal_auth.recheck_seconds', 120)));
    }

    private function recheck(AuthSession $session): ?AuthSession
    {
        try {
            $intranetToken = $session->intranet_token;
            $ssoToken = $session->sso_token;
        } catch (DecryptException) {
            // APP_KEY changed since the login.
            $session->revoke('token_unreadable');

            return null;
        }

        ['roles' => $roles, 'session' => $sso] = $this->portal->recheck($intranetToken, $ssoToken, $session->nontri_id);

        if ($roles->isRejected() || $sso?->isRejected()) {
            $ended = $roles->isRejected() ? $roles : $sso;

            Log::info("Portal session {$session->id} ended: {$ended->reason}");
            $session->revoke($roles->isRejected() ? 'portal_role_rejected' : 'portal_session_ended');

            return null;
        }

        if ($roles->isUnavailable() || $sso?->isUnavailable()) {
            Log::warning("Portal session {$session->id} kept: ENG Main could not be reached", [
                'get-role' => $roles->reason,
                'verify.php' => $sso?->reason,
            ]);
        }

        if ($roles->isOk()) {
            $session->roles = $roles->value;
        }

        // Also after an outage, so it is retried once per interval rather than
        // on every request.
        $session->checked_at = now();
        $session->save();

        return $session;
    }
}
