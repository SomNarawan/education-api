<?php

namespace Tests\Feature;

use App\Services\JwtIssuer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentMockLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => 'test-jwt-secret',
            'jwt.require_expiration' => true,
            'mock_login.enabled' => false,
            'mock_login.student_enabled' => true,
        ]);
    }

    public function test_student_switch_keeps_staff_mock_logins_closed(): void
    {
        $this->getJson('/api/mock-login/search')->assertNotFound();
        $this->get('/api/mock-login/system-teacher/fengame')->assertNotFound();
    }

    public function test_student_token_reaches_student_routes(): void
    {
        $this->withToken($this->token(['student']))->getJson('/api/me')->assertOk();
    }

    public function test_student_token_is_not_blocked_on_curriculum_lookups(): void
    {
        Http::fake();

        $token = $this->token(['student']);

        foreach (['/api/curriculum-categories', '/api/curriculum-plan-courses'] as $uri) {
            $this->assertNotSame(403, $this->withToken($token)->getJson($uri)->getStatusCode());
        }
    }

    public function test_student_token_cannot_reach_staff_routes(): void
    {
        $token = $this->token(['student']);

        $this->withToken($token)->getJson('/api/syncs')->assertForbidden();
        $this->withToken($token)->postJson('/api/system-faculties/sync')->assertForbidden();
        $this->withToken($token)->postJson('/api/students/import')->assertForbidden();
        $this->withToken($token)->postJson('/api/students')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/students/1')->assertForbidden();
        $this->withToken($token)->getJson('/api/students/studying')->assertForbidden();
        $this->withToken($token)->patchJson('/api/students/advisor')->assertForbidden();
    }

    public function test_staff_token_without_a_session_is_refused(): void
    {
        $this->withToken($this->token(['admin']))->getJson('/api/me')->assertUnauthorized();
        $this->withToken($this->token(['teacher']))->getJson('/api/me')->assertUnauthorized();
        $this->withToken($this->token(['student', 'admin']))->getJson('/api/me')->assertUnauthorized();
    }

    public function test_student_token_is_refused_with_both_switches_off(): void
    {
        config(['mock_login.student_enabled' => false]);

        $this->withToken($this->token(['student']))->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/mock-login/students/search')->assertNotFound();
    }

    public function test_staff_switch_keeps_every_route_open_for_local_development(): void
    {
        config(['mock_login.enabled' => true, 'mock_login.student_enabled' => false]);

        $this->withToken($this->token(['admin']))->getJson('/api/me')->assertOk();
        $this->withToken($this->token(['student']))->getJson('/api/me')->assertOk();
    }

    /**
     * @param  list<string>  $roles
     */
    private function token(array $roles): string
    {
        return app(JwtIssuer::class)->issue([
            'nontri_id' => '6500000001',
            'name' => 'Mock User',
            'role' => $roles,
            'current_role' => $roles[0],
            'department_id' => null,
            'faculty_id' => null,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);
    }
}
