<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalMainStudentApiTest extends TestCase
{
    private const API_KEY = 'test-portal-main-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal_main_api.api_key' => self::API_KEY]);

        $this->createTables();

        DB::table('system_departments')->insert([
            'id' => 1,
            'th_name' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
            'en_name' => 'Department of Computer Engineering',
            'status' => 'active',
        ]);

        DB::table('students')->insert([
            [
                'id' => 1,
                'student_code' => '6400000001',
                'first_name_th' => 'สมชาย',
                'last_name_th' => 'รักเรียน',
                'first_name_en' => 'Somchai',
                'last_name_en' => 'Rakrian',
                'email' => 'somchai.ra@ku.th',
                'system_department_id' => 1,
            ],
            [
                'id' => 2,
                'student_code' => '6400000002',
                'first_name_th' => 'สมหญิง',
                'last_name_th' => 'ใจดี',
                'first_name_en' => 'Somying',
                'last_name_en' => 'Jaidee',
                'email' => 'somying.j@ku.th',
                'system_department_id' => 1,
            ],
        ]);
    }

    public function test_rejects_requests_without_a_valid_api_key(): void
    {
        $this->getJson('/api/portal-main-student/check-user/b6400000001')
            ->assertUnauthorized();

        $this->withHeader('X-API-KEY', 'wrong-key')
            ->getJson('/api/portal-main-student/check-user/b6400000001')
            ->assertUnauthorized();
    }

    public function test_check_user_reports_existence_by_nontri_id(): void
    {
        $this->withApiKey()
            ->getJson('/api/portal-main-student/check-user/b6400000001')
            ->assertOk()
            ->assertExactJson(['nontriId' => 'b6400000001', 'exists' => true]);

        $this->withApiKey()
            ->getJson('/api/portal-main-student/check-user/unknown-id')
            ->assertOk()
            ->assertExactJson(['nontriId' => 'unknown-id', 'exists' => false]);
    }

    public function test_get_user_data_by_nontri_returns_user_detail(): void
    {
        $this->withApiKey()
            ->getJson('/api/portal-main-student/get-user-data-by-nontri/b6400000001')
            ->assertOk()
            ->assertExactJson([
                'nontriId' => 'b6400000001',
                'name' => 'สมชาย',
                'surname' => 'รักเรียน',
                'kuMail' => 'somchai.ra@ku.th',
                'agency' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
            ]);
    }

    public function test_get_user_data_by_nontri_returns_not_found_for_unknown_id(): void
    {
        $this->withApiKey()
            ->getJson('/api/portal-main-student/get-user-data-by-nontri/unknown-id')
            ->assertNotFound();
    }

    public function test_get_user_data_list_by_nontri_returns_matching_users(): void
    {
        $this->withApiKey()
            ->postJson('/api/portal-main-student/get-user-data-list-by-nontri', [
                'nontriIds' => ['b6400000001', 'b6400000002', 'unknown-id'],
            ])
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['nontriId' => 'b6400000001'])
            ->assertJsonFragment(['nontriId' => 'b6400000002']);
    }

    public function test_search_nontri_by_any_matches_name_and_email(): void
    {
        $this->withApiKey()
            ->getJson('/api/portal-main-student/search-nontri-by-any?search='.rawurlencode('สมชาย'))
            ->assertOk()
            ->assertExactJson(['nontriIds' => ['6400000001']]);

        $this->withApiKey()
            ->getJson('/api/portal-main-student/search-nontri-by-any?search=somying.j@ku.th')
            ->assertOk()
            ->assertExactJson(['nontriIds' => ['6400000002']]);
    }

    public function test_search_nontri_matches_by_full_name_and_agency(): void
    {
        $query = http_build_query(['fullName' => 'สมชาย', 'agency' => 'วิศวกรรมคอมพิวเตอร์']);

        $this->withApiKey()
            ->getJson('/api/portal-main-student/search-nontri?'.$query)
            ->assertOk()
            ->assertExactJson(['nontriIds' => ['6400000001']]);
    }

    public function test_search_nontri_requires_at_least_one_field(): void
    {
        $this->withApiKey()
            ->getJson('/api/portal-main-student/search-nontri')
            ->assertUnprocessable();
    }

    private function withApiKey(): self
    {
        return $this->withHeader('X-API-KEY', self::API_KEY);
    }

    private function createTables(): void
    {
        Schema::create('system_departments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('th_name', 50);
            $table->string('en_name', 50);
            $table->string('status')->default('active');
        });

        Schema::create('students', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('student_code', 20)->unique();
            $table->string('first_name_th')->nullable();
            $table->string('last_name_th')->nullable();
            $table->string('first_name_en')->nullable();
            $table->string('last_name_en')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('system_department_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
