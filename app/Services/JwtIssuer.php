<?php

namespace App\Services;

use LogicException;

class JwtIssuer
{
    private const ALGORITHMS = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
    ];

    public function issue(array $claims): string
    {
        $algorithm = (string) config('jwt.algorithm', 'HS256');

        if (!isset(self::ALGORITHMS[$algorithm])) {
            throw new LogicException("Unsupported JWT algorithm: {$algorithm}");
        }

        $secret = config('jwt.secret');

        if (!is_string($secret) || $secret === '') {
            throw new LogicException('JWT_SECRET is not configured');
        }

        $header = $this->encode(['typ' => 'JWT', 'alg' => $algorithm]);
        $payload = $this->encode($claims);
        $signature = hash_hmac(self::ALGORITHMS[$algorithm], "{$header}.{$payload}", $secret, true);

        return "{$header}.{$payload}.".$this->base64UrlEncode($signature);
    }

    private function encode(array $data): string
    {
        return $this->base64UrlEncode(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
