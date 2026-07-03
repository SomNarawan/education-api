<?php

namespace App\Services;

use InvalidArgumentException;
use LogicException;

class JwtVerifier
{
    private const ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new InvalidArgumentException('Invalid token format');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        $algorithm = (string) config('jwt.algorithm', 'HS256');

        if (($header['alg'] ?? null) !== $algorithm || !isset(self::ALGORITHMS[$algorithm])) {
            throw new InvalidArgumentException('Invalid token algorithm');
        }

        $secret = config('jwt.secret');

        if (!is_string($secret) || $secret === '') {
            throw new LogicException('JWT_SECRET is not configured');
        }

        $expectedSignature = hash_hmac(
            self::ALGORITHMS[$algorithm],
            $encodedHeader.'.'.$encodedPayload,
            $secret,
            true
        );

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Invalid token signature');
        }

        $this->validateClaims($claims);

        return $claims;
    }

    private function validateClaims(array $claims): void
    {
        $now = time();
        $leeway = max(0, (int) config('jwt.leeway', 0));

        if (config('jwt.require_expiration', true) && !isset($claims['exp'])) {
            throw new InvalidArgumentException('Token expiration is required');
        }

        if (isset($claims['exp'])) {
            $expiresAt = $this->numericDate($claims['exp'], 'exp');

            if ($now >= $expiresAt + $leeway) {
                throw new InvalidArgumentException('Token expired');
            }
        }

        if (isset($claims['nbf']) && $now + $leeway < $this->numericDate($claims['nbf'], 'nbf')) {
            throw new InvalidArgumentException('Token is not active yet');
        }

        if (isset($claims['iat']) && $now + $leeway < $this->numericDate($claims['iat'], 'iat')) {
            throw new InvalidArgumentException('Token was issued in the future');
        }

        $issuer = config('jwt.issuer');

        if ($issuer !== null && $issuer !== '' && ($claims['iss'] ?? null) !== $issuer) {
            throw new InvalidArgumentException('Invalid token issuer');
        }

        $audience = config('jwt.audience');

        if ($audience !== null && $audience !== '') {
            $tokenAudience = $claims['aud'] ?? [];
            $tokenAudience = is_array($tokenAudience) ? $tokenAudience : [$tokenAudience];

            if (!in_array($audience, $tokenAudience, true)) {
                throw new InvalidArgumentException('Invalid token audience');
            }
        }
    }

    private function numericDate(mixed $value, string $claim): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException("Invalid {$claim} claim");
        }

        return (int) $value;
    }

    private function decodeJson(string $encoded): array
    {
        $decoded = $this->base64UrlDecode($encoded);
        $value = json_decode($decoded, true);

        if (!is_array($value) || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid token payload');
        }

        return $value;
    }

    private function base64UrlDecode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException('Invalid token encoding');
        }

        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid token encoding');
        }

        return $decoded;
    }
}
