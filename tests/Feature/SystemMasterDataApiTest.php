<?php

namespace Tests\Feature;

use App\Services\PersonnelApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SystemMasterDataApiTest extends TestCase
{
    private const SECRET = 'system-master-data-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => self::SECRET,
            'jwt.algorithm' => 'HS256',
            'jwt.require_expiration' => true,
        ]);

        $this->createTables();

        DB::table('syncs')->insert([
            'id' => 1,
            'sync_type' => 1,
            'synced_count' => 0,
            'deleted_count' => 0,
            'skipped_count' => 0,
            'status' => 'success',
            'error_message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_endpoints_separate_active_records_from_all_records(): void
    {
        DB::table('system_faculties')->insert([
            [
                'id' => 10,
                'th_name' => 'คณะที่ใช้งาน',
                'en_name' => 'Active Faculty',
                'th_short_name' => 'ใช้งาน',
                'en_short_name' => 'ACTIVE',
                ...$this->auditFields(),
            ],
            [
                'id' => 11,
                'th_name' => 'คณะที่ลบ',
                'en_name' => 'Deleted Faculty',
                'th_short_name' => 'ลบ',
                'en_short_name' => 'DELETED',
                ...$this->auditFields(true),
            ],
        ]);
        DB::table('system_departments')->insert([
            [
                'id' => 20,
                'th_name' => 'ภาควิชาที่ใช้งาน',
                'en_name' => 'Active Department',
                'th_short_name' => 'ใช้งาน',
                'en_short_name' => 'ACTIVE',
                'system_faculty_id' => 10,
                ...$this->auditFields(),
            ],
            [
                'id' => 21,
                'th_name' => 'ภาควิชาที่ลบ',
                'en_name' => 'Deleted Department',
                'th_short_name' => 'ลบ',
                'en_short_name' => 'DELETED',
                'system_faculty_id' => 11,
                ...$this->auditFields(true),
            ],
        ]);
        DB::table('system_teachers')->insert([
            [
                'id' => 30,
                'nontri_id' => 'active-user',
                'full_name_th' => 'อาจารย์ที่ใช้งาน',
                'department_id' => 20,
                ...$this->auditFields(),
            ],
            [
                'id' => 31,
                'nontri_id' => 'deleted-user',
                'full_name_th' => 'อาจารย์ที่ลบ',
                'department_id' => 21,
                ...$this->auditFields(true),
            ],
        ]);

        $token = $this->token('reader');

        foreach (['system-faculties', 'system-departments', 'system-teachers'] as $resource) {
            $this->withToken($token)
                ->getJson("/api/{$resource}")
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.deleted_at', null);

            $this->withToken($token)
                ->getJson("/api/{$resource}/all")
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }

        $this->withToken($token)
            ->getJson('/api/system-teachers/all?department_id=21')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.deleted_by', 'sync-user')
            ->assertJsonPath('data.0.sync_id', 1);
    }

    public function test_sync_creates_updates_restores_and_deletes_records(): void
    {
        DB::table('system_faculties')->insert([
            [
                'id' => 10,
                'th_name' => 'ชื่อคณะเดิม',
                'en_name' => 'Old Faculty',
                'th_short_name' => 'เดิม',
                'en_short_name' => 'OLD',
                ...$this->auditFields(),
            ],
            [
                'id' => 11,
                'th_name' => 'คณะที่ไม่อยู่ในผล Sync',
                'en_name' => 'Missing Faculty',
                'th_short_name' => 'หาย',
                'en_short_name' => 'MISSING',
                ...$this->auditFields(),
            ],
        ]);

        $personnelApi = Mockery::mock(PersonnelApiService::class);
        $this->app->instance(PersonnelApiService::class, $personnelApi);
        $token = $this->token('sync-user');

        $personnelApi->shouldReceive('getFaculties')->once()->andReturn([
            [
                'id' => 10,
                'th_name' => 'ชื่อคณะใหม่',
                'en_name' => 'New Faculty',
                'th_short_name' => 'ใหม่',
                'en_short_name' => 'NEW',
            ],
        ]);

        $this->withToken($token)->postJson('/api/system-faculties/sync')->assertOk();

        $this->assertDatabaseHas('system_faculties', [
            'id' => 10,
            'th_name' => 'ชื่อคณะใหม่',
            'created_by' => 'seed-user',
            'updated_by' => 'sync-user',
            'deleted_by' => '',
            'sync_id' => 2,
        ]);
        $this->assertSoftDeleted('system_faculties', [
            'id' => 11,
            'deleted_by' => 'sync-user',
            'sync_id' => 2,
        ]);

        DB::table('system_departments')->insert([
            'id' => 21,
            'th_name' => 'ภาควิชาที่ไม่อยู่ในผล Sync',
            'en_name' => 'Missing Department',
            'th_short_name' => 'หาย',
            'en_short_name' => 'MISSING',
            'system_faculty_id' => 10,
            ...$this->auditFields(),
        ]);

        $personnelApi->shouldReceive('getDepartments')->once()->andReturn([
            [
                'id' => 20,
                'th_name' => 'ภาควิชาใหม่',
                'en_name' => 'New Department',
                'th_short_name' => 'ใหม่',
                'en_short_name' => 'NEW',
                'system_faculty_id' => 10,
            ],
        ]);

        $this->withToken($token)->postJson('/api/system-departments/sync')->assertOk();

        $this->assertDatabaseHas('system_departments', [
            'id' => 20,
            'created_by' => 'sync-user',
            'updated_by' => 'sync-user',
            'deleted_by' => '',
            'sync_id' => 3,
        ]);
        $this->assertSoftDeleted('system_departments', [
            'id' => 21,
            'deleted_by' => 'sync-user',
            'sync_id' => 3,
        ]);

        DB::table('system_teachers')->insert([
            [
                'id' => 30,
                'nontri_id' => 'restored-user',
                'full_name_th' => 'ชื่อเดิม',
                'department_id' => 20,
                ...$this->auditFields(true),
            ],
            [
                'id' => 31,
                'nontri_id' => 'missing-user',
                'full_name_th' => 'อาจารย์ที่ไม่อยู่ในผล Sync',
                'department_id' => 20,
                ...$this->auditFields(),
            ],
        ]);

        $personnelApi->shouldReceive('getSystemTeachers')->once()->andReturn([
            [
                'nontri_id' => 'restored-user',
                'full_name' => 'ชื่อใหม่',
                'department_id' => 20,
            ],
            [
                'nontri_id' => 'new-user',
                'full_name' => 'อาจารย์ใหม่',
                'department_id' => 20,
            ],
        ]);

        $this->withToken($token)->postJson('/api/system-teachers/sync')->assertOk();

        $this->assertDatabaseHas('system_teachers', [
            'id' => 30,
            'full_name_th' => 'ชื่อใหม่',
            'created_by' => 'seed-user',
            'updated_by' => 'sync-user',
            'deleted_at' => null,
            'deleted_by' => '',
            'sync_id' => 4,
        ]);
        $this->assertDatabaseHas('system_teachers', [
            'nontri_id' => 'new-user',
            'created_by' => 'sync-user',
            'updated_by' => 'sync-user',
            'deleted_by' => '',
            'sync_id' => 4,
        ]);
        $this->assertSoftDeleted('system_teachers', [
            'id' => 31,
            'deleted_by' => 'sync-user',
            'sync_id' => 4,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('syncs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('sync_type');
            $table->unsignedInteger('synced_count');
            $table->unsignedInteger('deleted_count');
            $table->unsignedInteger('skipped_count');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('system_faculties', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('th_name', 50);
            $table->string('en_name', 50);
            $table->string('th_short_name', 50);
            $table->string('en_short_name', 50);
            $this->auditColumns($table);
        });

        Schema::create('system_departments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('th_name', 50);
            $table->string('en_name', 50);
            $table->string('th_short_name', 50);
            $table->string('en_short_name', 50);
            $table->unsignedInteger('system_faculty_id');
            $this->auditColumns($table);
        });

        Schema::create('system_teachers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nontri_id', 20)->unique();
            $table->string('full_name_th');
            $table->unsignedInteger('department_id');
            $this->auditColumns($table);
        });
    }

    private function auditColumns(Blueprint $table): void
    {
        $table->dateTime('created_at');
        $table->string('created_by');
        $table->dateTime('updated_at');
        $table->string('updated_by');
        $table->softDeletes();
        $table->string('deleted_by');
        $table->unsignedInteger('sync_id');
    }

    private function auditFields(bool $deleted = false): array
    {
        return [
            'created_at' => now()->subDay(),
            'created_by' => 'seed-user',
            'updated_at' => now()->subDay(),
            'updated_by' => 'seed-user',
            'deleted_at' => $deleted ? now()->subHour() : null,
            'deleted_by' => $deleted ? 'sync-user' : '',
            'sync_id' => 1,
        ];
    }

    private function token(string $nontriId): string
    {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'nontri_id' => $nontriId,
            'iat' => time(),
            'exp' => time() + 300,
        ], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', "{$header}.{$payload}", self::SECRET, true);

        return "{$header}.{$payload}.".$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
