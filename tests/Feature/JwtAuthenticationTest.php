<?php

namespace Tests\Feature;

use Tests\TestCase;

class JwtAuthenticationTest extends TestCase
{
    private const SECRET = 'test-jwt-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => self::SECRET,
            'jwt.algorithm' => 'HS256',
            'jwt.require_expiration' => true,
        ]);
    }

    public function test_api_rejects_a_request_without_a_bearer_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Missing Bearer token');
    }

    public function test_api_accepts_a_valid_jwt(): void
    {
        $token = $this->token([
            'nontri_id' => null,
            'name' => 'Test User',
            'role' => ['teacher'],
            'iat' => time(),
            'exp' => time() + 300,
        ]);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Test User')
            ->assertJsonPath('data.current_role', 'teacher');
    }

    public function test_api_rejects_an_expired_jwt(): void
    {
        $token = $this->token([
            'iat' => time() - 600,
            'exp' => time() - 300,
        ]);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token expired');
    }

    public function test_api_rejects_a_jwt_with_an_invalid_signature(): void
    {
        $token = $this->token(['exp' => time() + 300], 'wrong-secret');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid token signature');
    }

    private function token(array $claims, string $secret = self::SECRET): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', "{$header}.{$payload}", $secret, true);

        return "{$header}.{$payload}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
