<?php

namespace Tests\Feature;

use App\Http\Requests\PortalMain\GetUserDetailsBulkRequest;
use App\Services\PortalMain\PortalMainStudentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requests are sent without `Accept: application/json`, as Portal Main and the
 * curl examples in docs/portal-main-student-api.md do.
 */
class PortalMainStudentApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/portal-main-student';

    private const API_KEY = 'test-portal-main-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['portal_main_api.api_key' => self::API_KEY]);

        // The real system_departments table has no `status` column, so the
        // queries must bypass SystemDepartment's active-status scope.
        Schema::create('system_departments', function (Blueprint $table) {
            $table->id();
            $table->string('th_name');
            $table->string('en_name')->nullable();
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code');
            $table->string('first_name_th')->nullable();
            $table->string('last_name_th')->nullable();
            $table->string('first_name_en')->nullable();
            $table->string('last_name_en')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('system_department_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('system_departments')->insert([
            ['id' => 1, 'th_name' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์', 'en_name' => 'Department of Computer Engineering'],
            ['id' => 2, 'th_name' => 'ภาควิชาวิศวกรรมโยธา', 'en_name' => 'Department of Civil Engineering'],
        ]);
        DB::table('students')->insert([
            $this->student('6020501361', 'นราวัลย์', 'เอี่ยมสอาด', 'Narawan', 'Iamsaard', 'narawan.i@ku.th', 1),
            $this->student('6400000002', 'สมหญิง', 'ใจดี', 'Somying', 'Jaidee', 'somying.j@ku.th', 1),
            $this->student('6400000003', 'สมชาย', 'ใจดี', 'Somchai', 'Jaidee', 'somchai.j@ku.th', 2),
            $this->student('6400000004', 'ไม่มี', 'ภาควิชา', null, null, 'no_department@ku.th', null),
        ]);

        $this->withHeader('X-API-KEY', self::API_KEY);
    }

    public static function protectedRequests(): array
    {
        return [
            'check-user' => ['GET', '/check-user/b6020501361'],
            'get-user-data-by-nontri' => ['GET', '/get-user-data-by-nontri/b6020501361'],
            'get-user-data-list-by-nontri' => ['POST', '/get-user-data-list-by-nontri'],
            'search-nontri-by-any' => ['GET', '/search-nontri-by-any?search=6020501361'],
            'search-nontri' => ['GET', '/search-nontri?nontriId=6020501361'],
        ];
    }

    #[DataProvider('protectedRequests')]
    public function test_missing_api_key_is_unauthorized(string $method, string $path): void
    {
        $this->flushHeaders()
            ->call($method, self::API.$path)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid or missing API key']);
    }

    #[DataProvider('protectedRequests')]
    public function test_wrong_api_key_is_unauthorized(string $method, string $path): void
    {
        $this->withHeader('X-API-KEY', 'wrong-key')
            ->call($method, self::API.$path)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid or missing API key']);
    }

    public function test_api_key_is_refused_while_none_is_configured(): void
    {
        config(['portal_main_api.api_key' => null]);

        $this->withHeader('X-API-KEY', '')
            ->get(self::API.'/check-user/b6020501361')
            ->assertUnauthorized();
    }

    public function test_check_user_strips_the_prefix_and_echoes_the_nontri_id(): void
    {
        $this->get(self::API.'/check-user/b6020501361')
            ->assertOk()
            ->assertExactJson(['nontriId' => 'b6020501361', 'exists' => true]);

        $this->get(self::API.'/check-user/6020501361')
            ->assertOk()
            ->assertExactJson(['nontriId' => '6020501361', 'exists' => true]);
    }

    public function test_check_user_reports_an_unknown_user(): void
    {
        $this->get(self::API.'/check-user/b9999999999')
            ->assertOk()
            ->assertExactJson(['nontriId' => 'b9999999999', 'exists' => false]);
    }

    public function test_get_user_data_returns_the_student_with_the_nontri_id_as_sent(): void
    {
        $this->get(self::API.'/get-user-data-by-nontri/b6020501361')
            ->assertOk()
            ->assertExactJson([
                'nontriId' => 'b6020501361',
                'name' => 'นราวัลย์',
                'surname' => 'เอี่ยมสอาด',
                'kuMail' => 'narawan.i@ku.th',
                'agency' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
            ]);
    }

    public function test_get_user_data_gives_a_null_agency_without_a_department(): void
    {
        $this->get(self::API.'/get-user-data-by-nontri/6400000004')
            ->assertOk()
            ->assertJsonPath('agency', null);
    }

    public function test_get_user_data_of_an_unknown_user_is_not_found(): void
    {
        $this->get(self::API.'/get-user-data-by-nontri/b9999999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'User not found']);
    }

    public function test_get_user_data_list_skips_unknown_ids_and_echoes_each_nontri_id(): void
    {
        $this->post(self::API.'/get-user-data-list-by-nontri', [
            'nontriIds' => ['b6020501361', '6400000002', 'unknown-id'],
        ])
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['nontriId' => 'b6020501361', 'name' => 'นราวัลย์'])
            ->assertJsonFragment(['nontriId' => '6400000002', 'name' => 'สมหญิง'])
            ->assertJsonMissing(['nontriId' => 'unknown-id']);
    }

    public function test_get_user_data_list_accepts_up_to_the_maximum_of_ids(): void
    {
        $nontriIds = array_fill(0, GetUserDetailsBulkRequest::MAX_NONTRI_IDS, 'b6020501361');

        $this->post(self::API.'/get-user-data-list-by-nontri', ['nontriIds' => $nontriIds])
            ->assertOk()
            ->assertJsonCount(1);
    }

    public static function invalidUserDataListBodies(): array
    {
        return [
            'missing nontriIds' => [[]],
            'empty nontriIds' => [['nontriIds' => []]],
            'nontriIds not a list' => [['nontriIds' => 'b6020501361']],
            'id over 50 characters' => [['nontriIds' => [str_repeat('1', 51)]]],
            'over the maximum of ids' => [['nontriIds' => array_fill(0, GetUserDetailsBulkRequest::MAX_NONTRI_IDS + 1, 'b6020501361')]],
        ];
    }

    #[DataProvider('invalidUserDataListBodies')]
    public function test_invalid_user_data_list_body_is_unprocessable(array $body): void
    {
        $this->post(self::API.'/get-user-data-list-by-nontri', $body)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    }

    public static function searchByAnyKeywords(): array
    {
        return [
            'student code' => ['60205', ['6020501361']],
            'thai first name' => ['สมชาย', ['6400000003']],
            'thai surname' => ['ใจดี', ['6400000002', '6400000003']],
            'english name' => ['somying', ['6400000002']],
            'email' => ['narawan.i@', ['6020501361']],
            'thai full name' => ['สมชาย ใจดี', ['6400000003']],
            'english full name' => ['Somying Jaidee', ['6400000002']],
            'surname and code' => ['ใจดี 0003', ['6400000003']],
            'no match' => ['ไม่พบใคร', []],
            'literal underscore' => ['_department', ['6400000004']],
            'percent is not a wildcard' => ['%', []],
            'underscore is not a wildcard' => ['_', ['6400000004']],
            'escape character is literal' => ['!', []],
        ];
    }

    #[DataProvider('searchByAnyKeywords')]
    public function test_search_by_any_returns_plain_student_codes(string $keyword, array $expected): void
    {
        $this->get(self::API.'/search-nontri-by-any?'.http_build_query(['search' => $keyword]))
            ->assertOk()
            ->assertExactJson(['nontriIds' => $expected]);
    }

    public function test_search_by_any_without_a_keyword_is_unprocessable(): void
    {
        $this->get(self::API.'/search-nontri-by-any')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public static function searchByFieldQueries(): array
    {
        return [
            'partial nontriId' => [['nontriId' => '640000'], ['6400000002', '6400000003', '6400000004']],
            'full name tokens across first and last name' => [['fullName' => 'สมชาย ใจดี'], ['6400000003']],
            'english full name' => [['fullName' => 'Somying Jaidee'], ['6400000002']],
            'thai agency' => [['agency' => 'โยธา'], ['6400000003']],
            'english agency' => [['agency' => 'Computer'], ['6020501361', '6400000002']],
            'fields combined' => [['fullName' => 'ใจดี', 'agency' => 'คอมพิวเตอร์'], ['6400000002']],
            'percent in nontriId is not a wildcard' => [['nontriId' => '%'], []],
            'underscore in nontriId is not a wildcard' => [['nontriId' => '6_2'], []],
            'percent in fullName is not a wildcard' => [['fullName' => '%'], []],
            'percent in agency is not a wildcard' => [['agency' => '%'], []],
        ];
    }

    #[DataProvider('searchByFieldQueries')]
    public function test_search_by_fields_returns_plain_student_codes(array $query, array $expected): void
    {
        $this->get(self::API.'/search-nontri?'.http_build_query($query))
            ->assertOk()
            ->assertExactJson(['nontriIds' => $expected]);
    }

    public function test_searches_return_at_most_the_maximum_of_results(): void
    {
        $max = PortalMainStudentService::MAX_SEARCH_RESULTS;
        DB::table('students')->insert(array_map(
            fn (int $i) => $this->student(sprintf('65%08d', $i), 'นิสิต', 'จำนวนมาก', null, null, null, 2),
            range(1, $max + 1),
        ));

        $byAny = $this->get(self::API.'/search-nontri-by-any?'.http_build_query(['search' => 'จำนวนมาก']))->assertOk();
        $byFields = $this->get(self::API.'/search-nontri?'.http_build_query(['fullName' => 'จำนวนมาก']))->assertOk();

        foreach ([$byAny, $byFields] as $response) {
            $response->assertJsonCount($max, 'nontriIds')
                ->assertJsonPath('nontriIds.0', '6500000001')
                ->assertJsonPath('nontriIds.'.($max - 1), sprintf('65%08d', $max));
        }
    }

    public function test_search_by_fields_without_any_field_is_unprocessable(): void
    {
        $this->get(self::API.'/search-nontri')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    private function student(
        string $code,
        string $firstNameTh,
        string $lastNameTh,
        ?string $firstNameEn,
        ?string $lastNameEn,
        ?string $email,
        ?int $departmentId,
    ): array {
        return [
            'student_code' => $code,
            'first_name_th' => $firstNameTh,
            'last_name_th' => $lastNameTh,
            'first_name_en' => $firstNameEn,
            'last_name_en' => $lastNameEn,
            'email' => $email,
            'system_department_id' => $departmentId,
        ];
    }
}
