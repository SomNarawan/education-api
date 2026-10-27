<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PortalLoginRequest;
use App\Models\AuthSession;
use App\Services\JwtIssuer;
use App\Services\PortalAuthResult;
use App\Services\PortalAuthService;
use App\Services\StaffProfileResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortalAuthController extends Controller
{
    /**
     * API: POST /api/auth/portal-login
     *
     * Turns the tokens ENG Main put on the redirect into this API's JWT. The
     * portal tokens stay on the server (auth_sessions); the JWT carries only
     * the session id (sid) besides the usual claims.
     */
    public function login(
        PortalLoginRequest $request,
        PortalAuthService $portal,
        StaffProfileResolver $profiles,
        JwtIssuer $issuer
    ) {
        $intranetToken = $request->validated('intranetToken');
        $role = $request->validated('role');

        $intranet = $portal->readIntranetToken($intranetToken);

        if (! $intranet->isOk()) {
            return $this->refuse($intranet, HttpStatus::UNAUTHORIZED, 'Token จาก Portal ไม่ถูกต้องหรือหมดอายุ กรุณาเข้าสู่ระบบจาก Portal อีกครั้ง');
        }

        $nontriId = $intranet->value['nontri_id'];
        $roles = $portal->fetchRoles($intranetToken, $nontriId);

        if ($roles->isOk() && ! in_array($role, $roles->value, true)) {
            $roles = PortalAuthResult::rejected("get-role: {$role} is not among ".implode(',', $roles->value));
        }

        if (! $roles->isOk()) {
            return $this->refuse($roles, HttpStatus::FORBIDDEN, 'บัญชีนี้ไม่มีสิทธิ์ใช้งานระบบในบทบาทที่เลือก');
        }

        // Single use, so it is spent only once nothing else can refuse the login.
        $sso = $portal->exchangeToken($request->validated('exchangeToken'));

        if (! $sso->isOk()) {
            return $this->refuse($sso, HttpStatus::UNAUTHORIZED, 'Token จาก Portal หมดอายุหรือถูกใช้ไปแล้ว กรุณาเข้าสู่ระบบจาก Portal อีกครั้ง');
        }

        $profile = $profiles->resolve($nontriId);

        AuthSession::query()->where('expires_at', '<', now()->subDay())->delete();

        $expiresAt = now()->addHours((int) config('portal_auth.session_hours', 8));

        if ($intranet->value['expires_at'] !== null) {
            // now() keeps the app timezone, which the datetime column is stored in.
            $expiresAt = $expiresAt->min(now()->setTimestamp($intranet->value['expires_at']));
        }

        $session = AuthSession::query()->create([
            'nontri_id' => $nontriId,
            'roles' => $roles->value,
            'intranet_token' => $intranetToken,
            'sso_token' => $sso->value,
            'expires_at' => $expiresAt,
            'checked_at' => now(),
        ]);

        $user = [
            'nontri_id' => $nontriId,
            'name' => $profile['name'],
            'role' => $roles->value,
            'current_role' => $role,
            'department_id' => $profile['department_id'],
            'faculty_id' => $profile['faculty_id'],
        ];

        $token = $issuer->issue(['sid' => $session->id] + $user + [
            'iat' => time(),
            'exp' => $session->expires_at->getTimestamp(),
        ]);

        return ApiResponse::success(['token' => $token, 'user' => $user]);
    }

    /**
     * API: POST /api/auth/logout
     *
     * Ends the session in this system only. logout.php of std-eng-auth-service
     * must never be called: the SSO token is shared with ENG Main and the other
     * child systems, so it would sign the user out of all of them.
     */
    public function logout(Request $request)
    {
        $request->attributes->get('auth_session')?->revoke('logout');

        return ApiResponse::success(null);
    }

    /**
     * @param  array{code: int, message: string}  $status
     */
    private function refuse(PortalAuthResult $result, array $status, string $message)
    {
        if ($result->isUnavailable()) {
            Log::warning("Portal login: central system unavailable: {$result->reason}");

            return ApiResponse::error(
                'ระบบกลางไม่ตอบสนอง กรุณาลองใหม่อีกครั้งภายหลัง',
                HttpStatus::SERVICE_UNAVAILABLE['code'],
                code: 'PORTAL_UNAVAILABLE'
            );
        }

        Log::info("Portal login refused: {$result->reason}");

        return ApiResponse::error($message, $status['code'], code: 'PORTAL_LOGIN_REJECTED');
    }
}
