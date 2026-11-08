<?php

namespace Tests\Unit\Services\Students;

use App\Actions\Students\SaveStudent;
use App\Constants\Status;
use App\Enums\ListOfValueType;
use App\Models\Student;
use App\Services\ListOfValueService;
use App\Services\Students\StudentImportService;
use App\Services\StudyPlans\StudentStudyPlanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;
use Tests\TestCase;

class StudentImportAdvisorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createImportTables();
        $this->seedMasterData();
        Storage::fake('local');
    }

    public function test_blank_advisor_maps_to_null_and_passes_validation(): void
    {
        $capturedAttributes = null;
        $saveStudent = $this->mock(SaveStudent::class);
        $saveStudent->shouldReceive('ensureAcademicCalendarAvailable')
            ->once()
            ->with(false);
        $saveStudent->shouldReceive('createFromImport')
            ->once()
            ->andReturnUsing(function (array $attributes, array $studyPlan) use (&$capturedAttributes): Student {
                $capturedAttributes = $attributes;

                return new Student;
            });
        $this->mock(StudentStudyPlanService::class)
            ->shouldReceive('fetch')
            ->once()
            ->with(99)
            ->andReturn([]);
        $service = $this->service();
        $source = $this->sourceWorkbook($service, [
            '6612345678',
            '',
            'นาย',
            'สมชาย',
            'ใจดี',
            '',
            '',
            '',
            '',
            '2566',
            '',
            'TCAS',
            '',
            '',
            '',
            '',
            '',
            '',
            'กำลังศึกษา',
        ]);

        $result = $service->import(
            $source,
            10,
            123,
            'CUR-123',
            99,
            'แผนปกติ',
            false,
            ['nontri_id' => 'tester'],
        );

        $this->assertSame(1, $result['import']->success_count);
        $this->assertSame(0, $result['import']->failed_count);
        $this->assertIsArray($capturedAttributes);
        $this->assertNull($capturedAttributes['teacher_id']);
        $this->assertNull($capturedAttributes['teacher_full_name']);
    }

    public function test_template_does_not_mark_advisor_as_required(): void
    {
        $this->mock(SaveStudent::class);
        $this->mock(StudentStudyPlanService::class);
        $path = $this->service()->createTemplate(123);
        $xlsx = SimpleXLSX::parse($path);

        $this->assertInstanceOf(SimpleXLSX::class, $xlsx);
        $this->assertSame('อาจารย์ที่ปรึกษา', $xlsx->rows(0)[1][10]);
    }

    private function service(): StudentImportService
    {
        $listOfValues = $this->mock(ListOfValueService::class);
        $listOfValues->shouldReceive('get')
            ->andReturnUsing(fn (ListOfValueType $type, array $filters = []) => match ($type) {
                ListOfValueType::Titles => collect([
                    ['id' => 1, 'name_th' => 'นาย', 'name_en' => 'Mr.'],
                ]),
                ListOfValueType::CurriculumPersonnel => collect([]),
                ListOfValueType::AdmissionChannels => collect([
                    ['id' => 2, 'name_th' => 'TCAS', 'name_en' => null],
                ]),
                ListOfValueType::HighSchools => collect([]),
                ListOfValueType::Relationships => collect([]),
                ListOfValueType::StudentStatuses => collect([
                    ['id' => 5, 'name_th' => 'กำลังศึกษา', 'name_en' => null],
                ]),
                default => collect([]),
            });

        return app(StudentImportService::class);
    }

    private function sourceWorkbook(StudentImportService $service, array $dataRow): UploadedFile
    {
        $template = SimpleXLSX::parse($service->createTemplate(123));
        $this->assertInstanceOf(SimpleXLSX::class, $template);
        $headers = array_slice($template->rows(0), 0, 2);
        $path = Storage::disk('local')->path('student-import-source.xlsx');
        $workbook = SimpleXLSXGen::fromArray([
            ...$headers,
            array_map(fn (mixed $value): string => SimpleXLSXGen::raw((string) $value), $dataRow),
        ], 'Sheet1');
        $this->assertTrue($workbook->saveAs($path));

        return new UploadedFile(
            $path,
            'student-import-source.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function createImportTables(): void
    {
        Schema::create('import_types', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('status');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_type_id');
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->string('curriculum_code')->nullable();
            $table->unsignedBigInteger('curriculum_plan_id')->nullable();
            $table->string('curriculum_plan_name_th')->nullable();
            $table->string('file_name');
            $table->string('file_result_path')->nullable();
            $table->unsignedInteger('total_count')->nullable();
            $table->unsignedInteger('success_count')->nullable();
            $table->unsignedInteger('failed_count')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->string('imported_by')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
        });
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('student_code')->unique();
            $table->string('student_id_card')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('system_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
        });

        foreach (['titles', 'admission_channels', 'high_schools', 'relationships', 'student_statuses'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    private function seedMasterData(): void
    {
        DB::table('import_types')->insert([
            'id' => 1,
            'type' => 'student',
            'status' => Status::ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('system_departments')->insert([
            'id' => 10,
            'status' => Status::ACTIVE,
        ]);
        DB::table('titles')->insert(['id' => 1]);
        DB::table('admission_channels')->insert(['id' => 2]);
        DB::table('student_statuses')->insert(['id' => 5]);
    }
}
