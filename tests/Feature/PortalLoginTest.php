<?php

namespace Tests\Feature;

use App\Contracts\TeacherApi;
use App\Models\AuthSession;
use App\Services\JwtIssuer;
use App\Services\JwtVerifier;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalLoginTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_API = 'https://portal.test/api';

    private const ORG_AUTH_API = 'https://auth.test';

    private const NONTRI_ID = 'fengabc';

    /**
     * Fake answers of the central systems, replaceable within a test.
     *
     * @var array{get-role: Closure, exchange-token: Closure, verify: Closure}
     */
    private array $portal;

    /**
     * @var list<array<string, mixed>>
     */
    private array $teachers = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => 'test-jwt-secret',
            'jwt.require_expiration' => true,
            'mock_login.enabled' => false,
            'portal_auth.main_api_url' => self::MAIN_API,
            'portal_auth.org_auth_api_url' => self::ORG_AUTH_API,
            'portal_auth.system_id' => '33',
            'portal_auth.role_map' => ['admin' => ['admin'], 'staff' => ['teacher']],
            'portal_auth.recheck_seconds' => 120,
        ]);

        $this->app->instance(TeacherApi::class, new class(fn (): array => $this->teachers) implements TeacherApi
        {
            public function __construct(private readonly Closure $teachers) {}

            public function getTeachers(): array
            {
                return ($this->teachers)();
            }

            public function findTeacher(int $teacherId): ?array
            {
                return null;
            }

            public function findTeacherByNontriId(string $nontriId): ?array
            {
                return null;
            }
        });

        $this->portal = [
            'get-role' => fn () => $this->roles(['staff']),
            'exchange-token' => fn () => Http::response(['status' => 'success', 'ssoToken' => 'sso-token']),
            'verify' => fn () => Http::response(['active' => true, 'sid' => 1]),
        ];

        Http::preventStrayRequests();
        Http::fake([
            self::MAIN_API.'/external/get-role*' => fn (Request $request) => ($this->portal['get-role'])($request),
            self::MAIN_API.'/external/exchange-token' => fn (Request $request) => ($this->portal['exchange-token'])($request),
            self::ORG_AUTH_API.'/api/verify.php' => fn (Request $request) => ($this->portal['verify'])($request),
        ]);
    }

    public function test_login_issues_a_token_tied_to_a_new_session(): void
    {
        $intranetToken = $this->intranetToken();

        $response = $this->login('teacher', $intranetToken)
            ->assertOk()
            ->assertJsonPath('data.user.nontri_id', self::NONTRI_ID)
            ->assertJsonPath('data.user.role', ['teacher'])
            ->assertJsonPath('data.user.current_role', 'teacher');

        $claims = app(JwtVerifier::class)->verify($response->json('data.token'));
        $session = AuthSession::query()->findOrFail($claims['sid']);

        $this->assertSame(self::NONTRI_ID, $session->nontri_id);
        $this->assertSame(['teacher'], $session->roles);
        $this->assertSame('sso-token', $session->sso_token);
        $this->assertSame($session->expires_at->getTimestamp(), $claims['exp']);
        $this->assertNotSame($intranetToken, DB::table('auth_sessions')->value('intranet_token'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/external/get-role')
            && $request->hasHeader('Authorization', "Bearer {$intranetToken}")
            && $request['nontriId'] === self::NONTRI_ID
            && $request['systemId'] === '33');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/external/exchange-token')
            && $request['exchangeToken'] === 'exchange-token');

        $this->withToken($response->json('data.token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.nontri_id', self::NONTRI_ID)
            ->assertJsonPath('data.current_role', 'teacher');
    }

    public function test_session_ends_with_the_intranet_token_when_that_is_sooner(): void
    {
        $expiresAt = time() + 3600;

        $response = $this->login('teacher', $this->intranetToken(exp: $expiresAt))->assertOk();

        $claims = app(JwtVerifier::class)->verify($response->json('data.token'));

        $this->assertSame($expiresAt, $claims['exp']);
        $this->assertSame($expiresAt, AuthSession::query()->sole()->expires_at->getTimestamp());
    }

    public function test_login_finds_name_department_and_faculty_of_the_teacher(): void
    {
        Schema::create('system_departments', function (Blueprint $table) {
            $table->id();
            $table->string('th_name');
            $table->unsignedBigInteger('system_faculty_id')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        DB::table('system_departments')->insert([
            'id' => 7,
            'th_name' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
            'system_faculty_id' => 3,
            'status' => 'active',
        ]);
        $this->teachers = [[
            'id' => 1,
            'nontri_id' => 'FENGABC',
            'full_name_th' => 'อาจารย์ ทดสอบ',
            'department_name' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
            'roles' => [],
        ]];

        $this->login('teacher')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'อาจารย์ ทดสอบ')
            ->assertJsonPath('data.user.department_id', 7)
            ->assertJsonPath('data.user.faculty_id', 3);
    }

    public function test_expired_intranet_token_is_refused_without_calling_out(): void
    {
        $this->login('teacher', $this->intranetToken(exp: time() - 10))->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function refusedRoleAnswers(): array
    {
        return [
            'get-role answers 403' => [['status' => 'fail', 'errorMsg' => 'Forbidden'], 403],
            'get-role answers 401' => [['status' => 'fail', 'errorMsg' => 'Token expired'], 401],
            'status fail' => [['status' => 'fail', 'errorMsg' => 'Token expired'], 200],
            'no roles' => [['status' => 'success', 'nontriId' => self::NONTRI_ID, 'roles' => []], 200],
            'only roles of other systems' => [['status' => 'success', 'nontriId' => self::NONTRI_ID, 'roles' => ['user']], 200],
            'answered for someone else' => [['status' => 'success', 'nontriId' => 'someone-else', 'roles' => ['staff']], 200],
        ];
    }

    #[DataProvider('refusedRoleAnswers')]
    public function test_refused_roles_are_forbidden_and_keep_the_exchange_token(array $body, int $status): void
    {
        $this->portal['get-role'] = fn () => Http::response($body, $status);

        $this->login('teacher')
            ->assertForbidden()
            ->assertJsonPath('code', 'PORTAL_LOGIN_REJECTED');

        $this->assertExchangeTokenNotSent();
        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_role_the_user_does_not_have_is_forbidden(): void
    {
        $this->login('admin')->assertForbidden();

        $this->assertExchangeTokenNotSent();
    }

    public function test_role_names_are_matched_case_insensitively(): void
    {
        $this->portal['get-role'] = fn () => $this->roles(['Admin', ' STAFF ']);

        $this->login('admin')
            ->assertOk()
            ->assertJsonPath('data.user.role', ['admin', 'teacher']);
    }

    public function test_user_with_both_roles_enters_with_the_role_clicked(): void
    {
        $this->portal['get-role'] = fn () => $this->roles(['admin', 'staff']);

        $response = $this->login('teacher')->assertOk();
        $claims = app(JwtVerifier::class)->verify($response->json('data.token'));

        $this->assertSame('teacher', $claims['current_role']);
        $this->assertEqualsCanonicalizing(['admin', 'teacher'], $claims['role']);
    }

    public function test_spent_or_expired_exchange_token_is_unauthorized(): void
    {
        $this->portal['exchange-token'] = fn () => Http::response(['status' => 'fail', 'errorMsg' => 'Token used'], 401);

        $this->login('teacher')->assertUnauthorized();

        $this->assertDatabaseCount('auth_sessions', 0);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function outages(): array
    {
        return [
            'get-role timeout' => ['get-role', 'timeout'],
            'get-role 500' => ['get-role', '500'],
            'get-role rate limited' => ['get-role', '429'],
            'exchange-token timeout' => ['exchange-token', 'timeout'],
            'exchange-token 502' => ['exchange-token', '502'],
        ];
    }

    #[DataProvider('outages')]
    public function test_central_outage_during_login_is_service_unavailable(string $endpoint, string $failure): void
    {
        $this->portal[$endpoint] = $failure === 'timeout'
            ? Http::failedConnection('cURL error 28: Operation timed out')
            : fn () => Http::response('', (int) $failure);

        $this->login('teacher')
            ->assertStatus(503)
            ->assertJsonPath('code', 'PORTAL_UNAVAILABLE');

        $this->assertDatabaseCount('auth_sessions', 0);
    }

    public function test_recheck_ends_the_session_when_get_role_refuses(): void
    {
        $token = $this->loggedInToken();
        $this->portal['get-role'] = fn () => Http::response(['status' => 'fail', 'message' => 'Token revoked'], 401);

        $this->travel(121)->seconds();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'PORTAL_SESSION_ENDED');

        $session = AuthSession::query()->sole();
        $this->assertNotNull($session->revoked_at);
        $this->assertSame('portal_role_rejected', $session->revoke_reason);
    }

    public function test_recheck_ends_the_session_when_verify_says_it_ended(): void
    {
        $token = $this->loggedInToken();
        $this->portal['verify'] = fn () => Http::response(['active' => false], 401);

        $this->travel(121)->seconds();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'PORTAL_SESSION_ENDED');

        $this->assertSame('portal_session_ended', AuthSession::query()->sole()->revoke_reason);
    }

    public function test_recheck_keeps_the_session_while_the_central_systems_are_down(): void
    {
        $token = $this->loggedInToken();
        $this->portal['get-role'] = Http::failedConnection();
        $this->portal['verify'] = fn () => Http::response('', 503);

        $this->travel(121)->seconds();

        $this->withToken($token)->getJson('/api/me')->assertOk();

        $session = AuthSession::query()->sole();
        $this->assertNull($session->revoked_at);
        $this->assertTrue($session->checked_at->greaterThan(now()->subSeconds(5)));
    }

    public function test_recheck_applies_roles_changed_in_eng_main(): void
    {
        $this->portal['get-role'] = fn () => $this->roles(['admin', 'staff']);
        $token = $this->loggedInToken('admin');
        $this->portal['get-role'] = fn () => $this->roles(['staff']);

        $this->travel(121)->seconds();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.role', ['teacher'])
            ->assertJsonPath('data.current_role', 'teacher');
    }

    public function test_no_recheck_within_the_interval(): void
    {
        $token = $this->loggedInToken();
        $this->portal['get-role'] = fn () => Http::response(['status' => 'fail'], 401);

        $this->travel(60)->seconds();

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_logout_revokes_only_this_session(): void
    {
        $token = $this->loggedInToken();

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $session = AuthSession::query()->sole();
        $this->assertNotNull($session->revoked_at);
        $this->assertSame('logout', $session->revoke_reason);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'PORTAL_SESSION_ENDED');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'logout.php'));
    }

    public function test_token_without_a_session_is_refused_while_mock_login_is_off(): void
    {
        $token = $this->mockToken();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        config(['mock_login.enabled' => true]);

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_token_with_an_unknown_session_is_refused_even_with_mock_login_on(): void
    {
        config(['mock_login.enabled' => true]);

        $this->withToken($this->mockToken(['sid' => '00000000-0000-0000-0000-000000000000']))
            ->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'PORTAL_SESSION_ENDED');
    }

    public function test_mock_login_follows_mock_login_enabled_in_every_app_env(): void
    {
        $this->assertTrue($this->mockLoginEnabledWith('production', 'true'));
        $this->assertFalse($this->mockLoginEnabledWith('production', 'false'));
        $this->assertFalse($this->mockLoginEnabledWith('local', 'false'));

        config(['mock_login' => ['enabled' => $this->mockLoginEnabledWith('production', 'false')] + config('mock_login')]);

        $this->get('/api/mock-login/admin')->assertNotFound();
    }

    public function test_login_throttle_counts_each_client_behind_the_proxy(): void
    {
        $fromClient = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
            ->withHeader('X-Forwarded-For', $ip)
            ->postJson('/api/auth/portal-login', []);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $fromClient('158.108.1.1')->assertUnprocessable();
        }

        $fromClient('158.108.1.1')->assertTooManyRequests();
        $fromClient('158.108.1.2')->assertUnprocessable();
        Http::assertNothingSent();
    }

    private function login(string $role, ?string $intranetToken = null)
    {
        return $this->postJson('/api/auth/portal-login', [
            'exchangeToken' => 'exchange-token',
            'intranetToken' => $intranetToken ?? $this->intranetToken(),
            'role' => $role,
        ]);
    }

    private function loggedInToken(string $role = 'teacher'): string
    {
        return $this->login($role)->assertOk()->json('data.token');
    }

    private function intranetToken(?int $exp = null): string
    {
        $encode = fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $encode(['typ' => 'JWT', 'alg' => 'RS256'])
            .'.'.$encode(['sub' => self::NONTRI_ID, 'exp' => $exp ?? time() + 12 * 3600])
            .'.signature-not-checked';
    }

    /**
     * @param  list<string>  $roles
     */
    private function roles(array $roles)
    {
        return Http::response(['status' => 'success', 'nontriId' => self::NONTRI_ID, 'systemId' => '33', 'roles' => $roles]);
    }

    private function mockToken(array $claims = []): string
    {
        return app(JwtIssuer::class)->issue($claims + [
            'nontri_id' => 'mock-admin',
            'name' => 'Mock Admin',
            'role' => ['admin'],
            'current_role' => 'admin',
            'department_id' => null,
            'faculty_id' => null,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);
    }

    private function assertExchangeTokenNotSent(): void
    {
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/external/exchange-token'));
    }

    private function mockLoginEnabledWith(string $appEnv, string $mockLoginEnabled): bool
    {
        $original = array_intersect_key($_SERVER, array_flip(['APP_ENV', 'MOCK_LOGIN_ENABLED']));
        $_SERVER['APP_ENV'] = $appEnv;
        $_SERVER['MOCK_LOGIN_ENABLED'] = $mockLoginEnabled;

        try {
            return (require config_path('mock_login.php'))['enabled'];
        } finally {
            unset($_SERVER['APP_ENV'], $_SERVER['MOCK_LOGIN_ENABLED']);
            $_SERVER = $original + $_SERVER;
        }
    }
}
