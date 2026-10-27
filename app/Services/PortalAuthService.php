<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Calls ENG Main (get-role, exchange-token) and std-eng-auth-service
 * (verify.php) for the portal login. Token values are never logged or put
 * into a result reason.
 */
class PortalAuthService
{
    /**
     * Reads sub and exp of the intranet JWT without verifying its signature
     * (the portal does not share its key): the nontri id is trusted only once
     * get-role has accepted the token for it. An expired token is refused
     * without calling anything.
     *
     * @return PortalAuthResult ok: array{nontri_id: string, expires_at: int|null}
     */
    public function readIntranetToken(string $token): PortalAuthResult
    {
        $parts = explode('.', $token);
        $payload = count($parts) === 3 ? json_decode($this->base64UrlDecode($parts[1]), true) : null;

        if (! is_array($payload)) {
            return PortalAuthResult::rejected('intranetToken is not a readable JWT');
        }

        $nontriId = is_string($payload['sub'] ?? null) ? trim($payload['sub']) : '';

        if ($nontriId === '') {
            return PortalAuthResult::rejected('intranetToken has no sub');
        }

        $expiresAt = is_numeric($payload['exp'] ?? null) ? (int) $payload['exp'] : null;

        if ($expiresAt !== null && $expiresAt <= time()) {
            return PortalAuthResult::rejected('intranetToken has expired');
        }

        return PortalAuthResult::ok(['nontri_id' => $nontriId, 'expires_at' => $expiresAt]);
    }

    /**
     * @return PortalAuthResult ok: list<string> roles of this app
     */
    public function fetchRoles(string $intranetToken, string $nontriId): PortalAuthResult
    {
        $response = $this->send(fn () => $this->rolesRequest($this->request(), $intranetToken, $nontriId));

        return $this->rolesResult($response, $nontriId);
    }

    /**
     * Trades the single-use exchangeToken for the university SSO token.
     *
     * @return PortalAuthResult ok: string ssoToken
     */
    public function exchangeToken(string $exchangeToken): PortalAuthResult
    {
        $response = $this->send(fn () => $this->request()->post(
            $this->mainApiUrl('external/exchange-token'),
            ['exchangeToken' => $exchangeToken]
        ));

        if ($failure = $this->failure($response, 'exchange-token')) {
            return $failure;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return PortalAuthResult::unavailable('exchange-token: response is not JSON');
        }

        $ssoToken = is_string($body['ssoToken'] ?? null) ? trim($body['ssoToken']) : '';

        if (($body['status'] ?? null) !== 'success' || $ssoToken === '') {
            return PortalAuthResult::rejected('exchange-token: '.$this->describe($body['errorMsg'] ?? 'no ssoToken'));
        }

        return PortalAuthResult::ok($ssoToken);
    }

    /**
     * verify.php without deep=1: whether the SSO session is still active.
     */
    public function verifySession(string $ssoToken): PortalAuthResult
    {
        return $this->verifyResult($this->send(fn () => $this->verifyRequest($this->request(), $ssoToken)));
    }

    /**
     * get-role and verify.php side by side, for re-checking a session.
     *
     * @return array{roles: PortalAuthResult, session: PortalAuthResult|null}
     */
    public function recheck(string $intranetToken, ?string $ssoToken, string $nontriId): array
    {
        $responses = Http::pool(function (Pool $pool) use ($intranetToken, $ssoToken, $nontriId): void {
            $this->rolesRequest($this->request($pool->as('roles')), $intranetToken, $nontriId);

            if ($ssoToken !== null) {
                $this->verifyRequest($this->request($pool->as('session')), $ssoToken);
            }
        });

        return [
            'roles' => $this->rolesResult($responses['roles'], $nontriId),
            'session' => isset($responses['session']) ? $this->verifyResult($responses['session']) : null,
        ];
    }

    private function rolesRequest(PendingRequest $request, string $intranetToken, string $nontriId): mixed
    {
        return $request->withToken($intranetToken)->get($this->mainApiUrl('external/get-role'), [
            'nontriId' => $nontriId,
            'systemId' => config('portal_auth.system_id'),
        ]);
    }

    private function rolesResult(Response|Throwable $response, string $nontriId): PortalAuthResult
    {
        if ($failure = $this->failure($response, 'get-role')) {
            return $failure;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return PortalAuthResult::unavailable('get-role: response is not JSON');
        }

        if (($body['status'] ?? null) !== 'success') {
            return PortalAuthResult::rejected('get-role: '.$this->describe($body['errorMsg'] ?? $body['message'] ?? $body['status'] ?? null));
        }

        $answeredFor = is_string($body['nontriId'] ?? null) ? trim($body['nontriId']) : '';

        if ($answeredFor !== '' && strcasecmp($answeredFor, $nontriId) !== 0) {
            return PortalAuthResult::rejected('get-role: answered for a different nontriId');
        }

        $portalRoles = is_array($body['roles'] ?? null) ? $body['roles'] : [];
        $roles = $this->mapRoles($portalRoles);

        // The ENG Main role names are logged so PORTAL_ROLE_MAP can be fixed.
        return $roles === []
            ? PortalAuthResult::rejected('get-role: no role of this system in '.$this->describe($portalRoles))
            : PortalAuthResult::ok($roles);
    }

    private function verifyRequest(PendingRequest $request, string $ssoToken): mixed
    {
        return $request->withToken($ssoToken)->get($this->orgAuthUrl('api/verify.php'));
    }

    private function verifyResult(Response|Throwable $response): PortalAuthResult
    {
        if ($failure = $this->failure($response, 'verify.php')) {
            return $failure;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return PortalAuthResult::unavailable('verify.php: response is not JSON');
        }

        return ($body['active'] ?? null) === true
            ? PortalAuthResult::ok()
            : PortalAuthResult::rejected('verify.php: session is not active');
    }

    /**
     * @param  array<mixed>  $portalRoles
     * @return list<string>
     */
    private function mapRoles(array $portalRoles): array
    {
        $roleMap = config('portal_auth.role_map', []);
        $roles = [];

        foreach ($portalRoles as $portalRole) {
            if (is_string($portalRole)) {
                array_push($roles, ...($roleMap[mb_strtolower(trim($portalRole))] ?? []));
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * Failure of a call, or null when the response is a 2xx to interpret.
     */
    private function failure(Response|Throwable $response, string $endpoint): ?PortalAuthResult
    {
        if ($response instanceof Throwable) {
            return PortalAuthResult::unavailable("{$endpoint}: ".$response->getMessage());
        }

        $status = $response->status();

        // 408 and 429 are "try later", not a refusal.
        if ($response->clientError() && ! in_array($status, [408, 429], true)) {
            return PortalAuthResult::rejected("{$endpoint}: HTTP {$status}");
        }

        if (! $response->successful()) {
            return PortalAuthResult::unavailable("{$endpoint}: HTTP {$status}");
        }

        return null;
    }

    private function send(Closure $call): Response|ConnectionException
    {
        try {
            return $call();
        } catch (ConnectionException $exception) {
            return $exception;
        }
    }

    private function request(?PendingRequest $request = null): PendingRequest
    {
        $caFile = config('portal_auth.ca_file');

        return ($request ?? Http::createPendingRequest())
            ->acceptJson()
            ->timeout(max(1, (int) config('portal_auth.timeout', 10)))
            ->withOptions(['verify' => $caFile ?: (bool) config('portal_auth.verify_ssl', true)]);
    }

    private function mainApiUrl(string $path): string
    {
        return config('portal_auth.main_api_url').'/'.$path;
    }

    private function orgAuthUrl(string $path): string
    {
        return config('portal_auth.org_auth_api_url').'/'.$path;
    }

    private function describe(mixed $value): string
    {
        return mb_substr(is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE), 0, 200);
    }

    private function base64UrlDecode(string $value): string
    {
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
