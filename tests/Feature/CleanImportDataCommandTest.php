<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanImportDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code');
            $table->integer('study_plan_id')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->decimal('gpax', 3, 2)->nullable();
            $table->integer('passed_credits')->nullable();
            $table->integer('not_passed_credits')->nullable();
            $table->integer('overed_credits')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
        });
        Schema::create('student_guardian_phone_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
        });
        Schema::create('import_types', function (Blueprint $table) {
            $table->id();
            $table->string('type');
        });
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_type_id');
        });

        DB::table('import_types')->insert([
            ['id' => 1, 'type' => 'student'],
            ['id' => 2, 'type' => 'grade'],
            ['id' => 3, 'type' => 'other'],
        ]);
        DB::table('imports')->insert([
            ['id' => 10, 'import_type_id' => 1],
            ['id' => 11, 'import_type_id' => 2],
            ['id' => 12, 'import_type_id' => 3],
        ]);

        $this->insertStudent(1, '6510000001', 100);
        $this->insertStudent(2, '6510000002', 100);
        $this->insertStudent(3, '6510000003', 200);
        DB::table('students')->where('id', 2)->update(['deleted_at' => now()]);

        foreach ([1, 2, 3] as $id) {
            DB::table('notes')->insert(['student_id' => $id]);
            DB::table('student_guardian_phone_histories')->insert(['student_id' => $id]);
        }

        foreach (['6510000001', '6510000002', '6510000003', '6510000001000'] as $code) {
            $this->writeGradeFiles($code);
        }

        $disk = Storage::disk('local');
        $disk->put('imports/students/10/result.xlsx', 'x');
        $disk->put('imports/grades/11/source.xlsx', 'x');
        $disk->put('imports/templates/student-import-template.xlsx', 'x');
        $disk->put('data/study_plans/100/courses.json', '[]');
    }

    public function test_refuses_to_run_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('dev:clean-import-data', ['--all' => true, '--force' => true])
            ->assertFailed();

        $this->assertSame(3, DB::table('students')->count());
    }

    public function test_requires_exactly_one_scope(): void
    {
        $this->artisan('dev:clean-import-data', ['--force' => true])->assertExitCode(2);
        $this->artisan('dev:clean-import-data', ['--all' => true, '--plan' => ['100'], '--force' => true])
            ->assertExitCode(2);

        $this->assertSame(3, DB::table('students')->count());
    }

    public function test_rejects_student_codes_that_could_escape_the_data_directory(): void
    {
        $this->artisan('dev:clean-import-data', ['--student' => ['../x'], '--force' => true])
            ->assertExitCode(2);
    }

    public function test_student_scope_deletes_only_that_student_and_their_grade_files(): void
    {
        $this->artisan('dev:clean-import-data', ['--student' => ['6510000001'], '--force' => true])
            ->assertSuccessful();

        $this->assertSame([2, 3], DB::table('students')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, DB::table('notes')->where('student_id', 1)->count());
        $this->assertSame(0, DB::table('student_guardian_phone_histories')->where('student_id', 1)->count());
        $this->assertGradeFilesMissing('6510000001');
        $this->assertGradeFilesPresent('6510000002');
        // A code that only starts with the same digits keeps its group files.
        $this->assertGradeFilesPresent('6510000001000');
        $this->assertSame(3, DB::table('imports')->count());
        Storage::disk('local')->assertExists('imports/grades/11/source.xlsx');
    }

    public function test_plan_scope_includes_soft_deleted_students(): void
    {
        $this->artisan('dev:clean-import-data', ['--plan' => ['100'], '--force' => true])
            ->assertSuccessful();

        $this->assertSame([3], DB::table('students')->pluck('id')->all());
        $this->assertGradeFilesMissing('6510000001');
        $this->assertGradeFilesMissing('6510000002');
        $this->assertGradeFilesPresent('6510000003');
    }

    public function test_all_scope_clears_students_imports_and_files_but_keeps_caches_and_templates(): void
    {
        $this->artisan('dev:clean-import-data', ['--all' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, DB::table('students')->count());
        $this->assertSame(0, DB::table('notes')->count());
        $this->assertSame(0, DB::table('student_guardian_phone_histories')->count());
        $this->assertSame([12], DB::table('imports')->pluck('id')->all());

        $disk = Storage::disk('local');
        $this->assertGradeFilesMissing('6510000003');
        $this->assertGradeFilesMissing('6510000001000');
        $disk->assertMissing('imports/students/10/result.xlsx');
        $disk->assertMissing('imports/grades/11/source.xlsx');
        $disk->assertExists('imports/templates/student-import-template.xlsx');
        $disk->assertExists('data/study_plans/100/courses.json');
    }

    public function test_grades_only_keeps_students_and_resets_their_grade_summary(): void
    {
        $this->artisan('dev:clean-import-data', ['--plan' => ['100'], '--grades-only' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(3, DB::table('students')->count());
        $this->assertSame(3, DB::table('notes')->count());

        $student = DB::table('students')->where('id', 1)->first();
        $this->assertNull($student->gpa);
        $this->assertNull($student->gpax);
        $this->assertNull($student->passed_credits);
        $this->assertNull($student->not_passed_credits);
        $this->assertNull($student->overed_credits);
        $this->assertNotNull(DB::table('students')->where('id', 3)->value('gpax'));

        $this->assertGradeFilesMissing('6510000001');
        $this->assertGradeFilesPresent('6510000003');
    }

    public function test_grades_only_with_all_keeps_student_import_history(): void
    {
        $this->artisan('dev:clean-import-data', ['--all' => true, '--grades-only' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(3, DB::table('students')->count());
        $this->assertSame([10, 12], DB::table('imports')->orderBy('id')->pluck('id')->all());
        Storage::disk('local')->assertExists('imports/students/10/result.xlsx');
        Storage::disk('local')->assertMissing('imports/grades/11/source.xlsx');
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('dev:clean-import-data', ['--all' => true, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(3, DB::table('students')->count());
        $this->assertSame(3, DB::table('imports')->count());
        $this->assertGradeFilesPresent('6510000001');
    }

    public function test_declining_the_confirmation_changes_nothing(): void
    {
        $this->artisan('dev:clean-import-data', ['--all' => true])
            ->expectsConfirmation('ยืนยันการลบข้อมูลด้านบน?', 'no')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('students')->count());
    }

    private function insertStudent(int $id, string $code, int $planId): void
    {
        DB::table('students')->insert([
            'id' => $id,
            'student_code' => $code,
            'study_plan_id' => $planId,
            'gpa' => 3.00,
            'gpax' => 3.10,
            'passed_credits' => 30,
            'not_passed_credits' => 3,
            'overed_credits' => 0,
        ]);
    }

    private function writeGradeFiles(string $code): void
    {
        $disk = Storage::disk('local');

        foreach ($this->gradePaths($code) as $path) {
            $disk->put($path, '[]');
        }
    }

    private function gradePaths(string $code): array
    {
        return [
            "data/grade_attempts/{$code}.json",
            "data/enrollments/{$code}.json",
            "data/enrollments_not_pass/{$code}.json",
            "data/enrollments_pass/{$code}.json",
            "data/enrollments_over/{$code}.json",
            "data/graph/by_credit/{$code}.json",
            "data/graph/by_semester/{$code}.json",
            "data/graph/by_group/{$code}_1.json",
            "data/graph/by_group/{$code}_2.json",
        ];
    }

    private function assertGradeFilesMissing(string $code): void
    {
        foreach ($this->gradePaths($code) as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    private function assertGradeFilesPresent(string $code): void
    {
        foreach ($this->gradePaths($code) as $path) {
            Storage::disk('local')->assertExists($path);
        }
    }
}
