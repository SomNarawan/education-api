<?php

namespace Tests\Feature;

use App\Services\Grades\StudentGradeJsonGenerator;
use App\Services\JwtIssuer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvisorSummaryTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'http://localhost/api/students/advisor-summary';

    protected function setUp(): void
    {
        parent::setUp();

        // Plan status is read from the per-student JSON files: never the real ones.
        Storage::fake('local');

        config([
            'jwt.secret' => 'test-jwt-secret',
            'jwt.require_expiration' => true,
            'mock_login.enabled' => true,
            'mock_login.student_enabled' => true,
        ]);

        Schema::create('student_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('status_name');
        });
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('title_abbr_th');
        });
        DB::table('titles')->insert(['id' => 1, 'title_abbr_th' => 'นาย']);
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code');
            $table->integer('title_id')->nullable();
            $table->string('first_name_th')->nullable();
            $table->string('last_name_th')->nullable();
            $table->integer('study_plan_id')->nullable();
            $table->string('teacher_id')->nullable();
            $table->string('teacher_full_name')->nullable();
            $table->unsignedBigInteger('student_status_id');
            $table->unsignedSmallInteger('entry_year');
            $table->unsignedSmallInteger('study_year')->nullable();
            $table->decimal('gpax', 3, 2)->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->integer('passed_credits')->nullable();
            $table->integer('not_passed_credits')->nullable();
            $table->integer('overed_credits')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('student_statuses')->insert([
            ['id' => 1, 'status_name' => 'กำลังศึกษา'],
            ['id' => 2, 'status_name' => 'จบการศึกษา'],
            ['id' => 3, 'status_name' => 'พ้นสภาพนิสิต'],
        ]);

        $this->insertStudent('1', 'fengone', 'อาจารย์หนึ่ง', 1, 2022, 3.25, 1);
        $this->insertStudent('2', 'fengone', 'อาจารย์หนึ่ง', 1, 2022, 3.24, 1);
        $this->insertStudent('3', 'fengone', 'อาจารย์หนึ่ง', 1, 2022, 2.00, 2);
        $this->insertStudent('4', 'fengone', 'อาจารย์หนึ่ง', 1, 2023, 1.99, 2);
        $this->insertStudent('5', 'fengone', 'อาจารย์หนึ่ง', 1, 2023, 1.75, 3);
        $this->insertStudent('6', 'fengone', 'อาจารย์หนึ่ง', 1, 2023, 1.74, 3);
        $this->insertStudent('7', 'fengone', 'อาจารย์หนึ่ง', 1, 2023, 0.00, 4);
        $this->insertStudent('8', 'fengone', 'อาจารย์หนึ่ง', 1, 2023, null, 4);
        $this->insertStudent('9', 'fengone', 'อาจารย์หนึ่ง', 2, 2019, 3.90, 4);
        $this->insertStudent('10', 'fengone', 'อาจารย์หนึ่ง', 3, 2021, 1.00);
        $this->insertStudent('11', 'fengtwo', 'อาจารย์สอง', 1, 2022, 3.50);
        $this->insertStudent('12', 'fengtwo', 'อาจารย์สอง', 2, 2020, 3.00);
        $this->insertStudent('13', null, null, 1, 2022, 3.00);
        DB::table('students')->insert([
            'student_code' => '14', 'teacher_id' => 'fengone', 'teacher_full_name' => 'อาจารย์หนึ่ง',
            'student_status_id' => 1, 'entry_year' => 2022, 'gpax' => 3.80, 'deleted_at' => now(),
        ]);
    }

    public function test_teacher_gets_only_own_students_split_by_status_and_gpax_range(): void
    {
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame('fengone', $data['teacher_id']);
        $this->assertSame('อาจารย์หนึ่ง', $data['teacher_full_name']);
        $this->assertSame(
            ['all' => 10, 'studying' => 8, 'graduated' => 1, 'retired' => 1, 'other' => 0],
            $data['totals'],
        );
        $this->assertSame(
            [
                'range_3_25_4_00' => 1,
                'range_2_00_3_24' => 2,
                'range_1_75_1_99' => 2,
                'range_0_00_1_74' => 2,
                'no_gpax' => 1,
            ],
            $data['studying_gpax'],
        );
        $this->assertArrayNotHasKey('advisors', $data);
    }

    public function test_annual_gpa_is_credit_weighted_includes_summer_and_later_passes_do_not_rewrite_history(): void
    {
        DB::table('students')->where('student_code', '1')->update(['study_plan_id' => 3]);
        $attempt = ['student_code' => '1', 'enrollment_type' => 'credit', '_is_in_plan' => true, 'academic_year' => 2022, 'study_year' => 1, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'credit' => 3, 'course_code' => '00000001', 'grade_letter' => 'A', 'grade_point' => 4];
        $attempts = [
            $attempt,
            [...$attempt, 'course_code' => '00000002', 'credit' => 1, 'semester_order' => 2, 'semester' => 'ภาคปลาย', 'grade_letter' => 'F', 'grade_point' => 0],
            [...$attempt, 'course_code' => '00000003', 'credit' => 6, 'semester_order' => 3, 'semester' => 'ภาคฤดูร้อน'],
            [...$attempt, 'course_code' => 'TRAIN', 'credit' => 240, 'semester_order' => 3, 'semester' => 'ภาคฤดูร้อน', 'enrollment_type' => 'hour', 'grade_letter' => 'P', 'grade_point' => null],
            [...$attempt, 'course_code' => '00000002', 'credit' => 1, 'academic_year' => 2023, 'study_year' => 2],
        ];
        $row = ['course_code' => '00000001', 'credit' => 3, 'plan_study_year' => 1, 'plan_semester_order' => 1, 'course_name' => 'A', 'course_category' => 'Core'];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => $attempts]));
        Storage::disk('local')->put('data/study_plan/1.json', json_encode(['curriculum_plan_id' => 3, 'total_credits_min' => 4, 'data' => [$row, [...$row, 'course_code' => '00000002', 'credit' => 1, 'plan_semester_order' => 2]]]));
        Storage::disk('local')->put('data/enrollments_not_pass/1.json', '[]');

        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $this->assertSame(3.6, $data['gpa_by_study_year'][0]['cohorts'][0]['students'][0]['gpa']);
        $this->assertSame(1, $data['gpa_by_study_year'][0]['cohorts'][0]['ranges']['range_3_25_4_00']);
        $terms = collect($data['academic_terms'])->keyBy('term_label');
        $this->assertSame(1, $terms['2565 ภาคต้น']['on_plan']);
        $this->assertSame(1, $terms['2565 ภาคปลาย']['off_plan']);
        $this->assertSame(1, $terms['2566 ภาคต้น']['on_plan']);
        $this->assertSame(1, $terms['2565 ภาคฤดูร้อน']['off_plan']);
        $this->assertEquals(3.6, $terms['2565 ภาคฤดูร้อน']['students'][0]['gpax']);
        $pastStudent = $terms['2565 ภาคปลาย']['students'][0];
        $this->assertSame(1, $pastStudent['pending_count']);
        $this->assertSame(1, $pastStudent['term_study_year']);
        $this->assertSame(['00000002'], array_column($pastStudent['pending_courses'], 'course_code'));
        // The course failed in 2565 ภาคปลาย and was passed later, which is shown without changing that term.
        $this->assertSame('F', $pastStudent['pending_courses'][0]['grade_letter']);
        $this->assertTrue($pastStudent['pending_courses'][0]['resolved']);
        $this->assertSame('2566 ภาคต้น', $pastStudent['pending_courses'][0]['resolved_term_label']);
        $this->assertSame([], $data['off_plan_students']);
        $this->assertCount(4, $data['academic_terms']);
    }

    public function test_annual_chart_reads_all_semester_codes_from_the_grade_attempts(): void
    {
        $attempt = ['student_code' => '1', 'academic_year' => 2022, 'study_year' => 1, 'enrollment_type' => 'credit', 'credit' => 1, 'course_code' => '00000001', 'semester_order' => 1, 'semester' => 'ภาคต้น', 'grade_point' => 0, 'grade_letter' => 'F'];
        $attempts = [$attempt,
            [...$attempt, 'course_code' => '00000002', 'semester_order' => 3, 'semester' => 'ภาคฤดูร้อน', 'grade_point' => 3, 'grade_letter' => 'B'],
            [...$attempt, 'course_code' => '00000003', 'semester_order' => 7, 'semester' => 'ภาคพิเศษตามข้อมูล', 'credit' => 2, 'grade_point' => 4, 'grade_letter' => 'A'],
            [...$attempt, 'course_code' => 'TRAIN', 'semester_order' => 3, 'credit' => 240, 'enrollment_type' => 'hour', 'grade_point' => null, 'grade_letter' => 'P']];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => $attempts]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $this->assertSame(2.75, $data['gpa_by_study_year'][0]['cohorts'][0]['students'][0]['gpa']);
        $this->assertCount(1, $data['gpa_by_study_year'][0]['cohorts'][0]['students']);
        $this->assertSame(1, $data['history_coverage']['with_grades']);
        $this->assertSame(3.25, $data['gpa_by_study_year'][0]['cohorts'][0]['students'][0]['current_gpax']);
        $this->assertSame([1, 3, 7], array_column($data['academic_terms'], 'semester_order'));
        $this->assertSame('ภาคพิเศษตามข้อมูล', $data['academic_terms'][2]['semester_name']);
        $this->assertSame('2565 ภาคพิเศษตามข้อมูล', $data['academic_terms'][2]['term_label']);
    }

    public function test_term_history_includes_summer_and_custom_terms_from_the_grade_attempts(): void
    {
        DB::table('students')->where('student_code', '1')->update(['study_plan_id' => 3]);
        $attempt = ['student_code' => '1', 'academic_year' => 2022, 'study_year' => 1, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'course_code' => '00000001', 'enrollment_type' => 'credit', 'credit' => 3, 'grade_letter' => 'F', 'grade_point' => 0];
        $attempts = [$attempt,
            [...$attempt, 'semester_order' => 3, 'semester' => 'ภาคฤดูร้อน', 'grade_letter' => 'B', 'grade_point' => 3],
            [...$attempt, 'course_code' => '00000002', 'semester_order' => 7, 'semester' => 'ภาคพิเศษตามข้อมูล', 'credit' => 1, 'grade_letter' => 'A', 'grade_point' => 4]];
        $row = ['course_code' => '00000001', 'credit' => 3, 'plan_study_year' => 1, 'plan_semester_order' => 1, 'course_name' => 'A', 'course_category' => 'Core'];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => $attempts]));
        Storage::disk('local')->put('data/study_plan/1.json', json_encode(['curriculum_plan_id' => 3, 'data' => [$row, [...$row, 'course_code' => '00000002', 'credit' => 1, 'plan_semester_order' => 7]]]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $terms = collect($data['academic_terms'])->keyBy('semester_order');
        $this->assertSame([1, 3, 7], $terms->keys()->all());
        $this->assertSame(1, $terms[1]['off_plan']);
        $this->assertSame(1, $terms[1]['students'][0]['pending_count']);
        $this->assertSame(['00000001'], array_column($terms[1]['students'][0]['pending_courses'], 'course_code'));
        $this->assertSame('F', $terms[1]['students'][0]['pending_courses'][0]['grade_letter']);
        $this->assertSame('2565 ภาคฤดูร้อน', $terms[1]['students'][0]['pending_courses'][0]['resolved_term_label']);
        $this->assertSame(1, $terms[3]['on_plan']);
        $this->assertEquals(1.5, $terms[3]['students'][0]['gpax']);
        $this->assertSame('ภาคฤดูร้อน', $terms[3]['semester_name']);
        $this->assertSame('ภาคพิเศษตามข้อมูล', $terms[7]['semester_name']);
        $this->assertEquals(1.86, $terms[7]['students'][0]['gpax']);
        $this->assertCount(1, $terms[3]['students']);
    }

    public function test_term_status_is_unknown_when_the_student_has_no_study_plan_file(): void
    {
        $attempt = ['student_code' => '1', 'academic_year' => 2022, 'study_year' => 1, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'course_code' => '00000001', 'enrollment_type' => 'credit', 'credit' => 3, 'grade_letter' => 'A', 'grade_point' => 4];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => [$attempt]]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $this->assertSame(1, $data['academic_terms'][0]['unknown']);
        $this->assertSame(0, $data['academic_terms'][0]['on_plan'] + $data['academic_terms'][0]['off_plan']);
        $this->assertEquals(4, $data['academic_terms'][0]['students'][0]['gpax']);
    }

    public function test_a_course_that_is_still_missing_has_no_grade_and_is_not_resolved(): void
    {
        $attempt = ['student_code' => '1', 'academic_year' => 2022, 'study_year' => 1, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'course_code' => '00000001', 'enrollment_type' => 'credit', 'credit' => 3, 'grade_letter' => 'A', 'grade_point' => 4];
        $row = ['course_code' => '00000001', 'course_name' => 'A', 'credit' => 3, 'plan_study_year' => 1, 'plan_semester_order' => 1];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => [$attempt, [...$attempt, 'semester_order' => 2, 'semester' => 'ภาคปลาย', 'course_code' => '00000005']]]));
        Storage::disk('local')->put('data/study_plan/1.json', json_encode(['curriculum_plan_id' => 3, 'data' => [$row, [...$row, 'course_code' => '00000002', 'course_name' => 'Never taken', 'plan_semester_order' => 2]]]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $course = $data['academic_terms'][1]['students'][0]['pending_courses'][0];
        $this->assertSame('00000002', $course['course_code']);
        $this->assertNull($course['grade_letter']);
        $this->assertFalse($course['resolved']);
        $this->assertNull($course['resolved_term_label']);
    }

    public function test_each_missing_course_of_a_past_term_shows_whether_it_has_been_passed_since(): void
    {
        $attempt = ['student_code' => '1', 'academic_year' => 2022, 'study_year' => 1, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'enrollment_type' => 'credit', 'credit' => 3, 'grade_letter' => 'F', 'grade_point' => 0];
        $row = ['credit' => 3, 'plan_study_year' => 1, 'plan_semester_order' => 1];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => [
            [...$attempt, 'course_code' => '00000001'],
            [...$attempt, 'course_code' => '00000002'],
            [...$attempt, 'course_code' => '00000001', 'academic_year' => 2023, 'study_year' => 2, 'grade_letter' => 'B', 'grade_point' => 3],
        ]]));
        Storage::disk('local')->put('data/study_plan/1.json', json_encode(['curriculum_plan_id' => 3, 'data' => [
            [...$row, 'course_code' => '00000001', 'course_name' => 'Passed later'],
            [...$row, 'course_code' => '00000002', 'course_name' => 'Still missing'],
        ]]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $term = $data['academic_terms'][0];
        $this->assertSame(1, $term['off_plan']);
        $resolved = array_column($term['students'][0]['pending_courses'], 'resolved', 'course_code');
        $this->assertTrue($resolved['00000001']);
        $this->assertFalse($resolved['00000002']);
    }

    public function test_a_group_slot_is_filled_by_any_course_with_the_same_code_prefix(): void
    {
        $attempt = ['student_code' => '1', 'academic_year' => 2022, 'study_year' => 1, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'course_code' => '01175123', 'enrollment_type' => 'credit', 'credit' => 3, 'grade_letter' => 'B', 'grade_point' => 3];
        $group = ['course_code' => '01175xxx', 'course_name' => 'Elective group', 'credit' => 3, 'plan_study_year' => 1, 'plan_semester_order' => 1, 'courses' => []];
        $fixed = ['course_code' => '00000009', 'course_name' => 'Fixed', 'credit' => 3, 'plan_study_year' => 1, 'plan_semester_order' => 2];
        Storage::disk('local')->put('data/grade_attempts/1.json', json_encode(['grade_attempts' => [$attempt]]));
        Storage::disk('local')->put('data/study_plan/1.json', json_encode(['curriculum_plan_id' => 3, 'data' => [$group, $fixed]]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        // Only the first term has grades: the group slot is passed, the fixed course is not due yet.
        $this->assertSame(1, $data['academic_terms'][0]['on_plan']);
    }

    public function test_current_off_plan_report_excludes_resolved_failures_and_contains_student_identity(): void
    {
        DB::table('students')->where('student_code', '1')->update(['title_id' => 1, 'first_name_th' => 'สมชาย', 'last_name_th' => 'ทดสอบ', 'study_plan_id' => 3]);
        $course = ['course_code' => '00000001', 'course_name' => 'วิชาที่ผ่านแล้ว', 'credit' => 3, 'course_category' => 'Core', 'plan_study_year' => 1, 'plan_semester_order' => 1];
        $plan = ['curriculum_plan_id' => 3, 'total_credits_min' => 9, 'data' => [$course,
            [...$course, 'course_code' => '00000002', 'course_name' => 'วิชาที่ยังไม่ผ่าน'],
            [...$course, 'course_code' => '00000003', 'course_name' => 'วิชาที่ยังไม่ได้เรียน', 'plan_semester_order' => 2]]];
        $attempt = ['student_code' => '1', 'course_code' => '00000001', 'academic_year' => 2022, 'semester_order' => 1, 'semester' => 'ภาคต้น', 'enrollment_type' => 'credit', 'grade_letter' => 'F', 'grade_point' => 0];
        app(StudentGradeJsonGenerator::class)->generate('1', [$attempt,
            [...$attempt, 'semester_order' => 2, 'semester' => 'ภาคปลาย', 'grade_letter' => 'B', 'grade_point' => 3],
            [...$attempt, 'course_code' => '00000002', 'semester_order' => 2, 'semester' => 'ภาคปลาย']], $plan, 3);
        // A second student who has recovered completely must not appear in this report.
        Storage::disk('local')->put('data/enrollments_not_pass/2.json', '[]');
        Storage::disk('local')->put('data/enrollments_pass/2.json', json_encode([['course_code' => '00000001', 'grade_letter' => 'F, C']]));
        Storage::disk('local')->put('data/enrollments_not_pass/11.json', json_encode([['course_code' => '00000002', 'grade_letter' => 'F', 'credit' => 3]]));
        Storage::disk('local')->put('data/enrollments_not_pass/14.json', json_encode([['course_code' => '00000002', 'grade_letter' => 'F', 'credit' => 3]]));
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))->assertOk()->json('data');
        $this->assertCount(1, $data['off_plan_students']);
        $student = $data['off_plan_students'][0];
        $this->assertSame('นายสมชาย ทดสอบ', $student['full_name_th']);
        $this->assertSame(1, $student['study_year']);
        $this->assertSame(2, $student['pending_count']);
        $this->assertEquals(6, $student['pending_credits']);
        $this->assertSame(['00000002', '00000003'], array_column($student['pending_courses'], 'course_code'));
        $this->assertEquals(1, $student['current_gpax']);
        $cohortStudent = collect($data['by_entry_year'])->flatMap(fn ($cohort) => $cohort['students'])->firstWhere('student_code', '1');
        $this->assertSame('นายสมชาย ทดสอบ', $cohortStudent['full_name_th']);
        $this->assertSame('นายสมชาย ทดสอบ', $data['gpa_by_study_year'][0]['cohorts'][0]['students'][0]['full_name_th']);
        $this->assertSame(1, $data['plan_status']['off_plan']);
    }

    public function test_teacher_cannot_read_another_advisor_through_the_query(): void
    {
        $data = $this->getJson(
            self::URL.'?teacher_id=fengtwo',
            $this->headersFor(['teacher'], 'teacher', 'fengone'),
        )->assertOk()->json('data');

        $this->assertSame('fengone', $data['teacher_id']);
        $this->assertSame(10, $data['totals']['all']);
    }

    public function test_entry_years_are_sorted_with_their_own_counts(): void
    {
        $years = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data.by_entry_year');

        $this->assertSame([2019, 2021, 2022, 2023], array_column($years, 'entry_year'));

        $byYear = array_column($years, null, 'entry_year');
        $this->assertSame(1, $byYear[2019]['graduated']);
        $this->assertSame(1, $byYear[2021]['retired']);
        $this->assertSame(3, $byYear[2022]['studying']);
        $this->assertSame(5, $byYear[2023]['studying']);
        $this->assertSame(1, $byYear[2022]['gpax']['range_3_25_4_00']);
        $this->assertSame(1, $byYear[2023]['gpax']['no_gpax']);
        $this->assertSame(0, $byYear[2019]['gpax']['range_3_25_4_00']);
    }

    public function test_graduated_students_get_their_own_gpax_ranges_by_cohort(): void
    {
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            [
                'range_3_25_4_00' => 1,
                'range_2_00_3_24' => 0,
                'range_1_75_1_99' => 0,
                'range_0_00_1_74' => 0,
                'no_gpax' => 0,
            ],
            $data['graduated_gpax'],
        );

        $byYear = array_column($data['by_entry_year'], null, 'entry_year');

        $this->assertSame(1, $byYear[2019]['graduated_gpax']['range_3_25_4_00']);
        $this->assertSame(0, $byYear[2022]['graduated_gpax']['range_3_25_4_00']);
        // The retired student (1.00) is in neither GPAX split.
        $this->assertSame(0, array_sum($byYear[2021]['graduated_gpax']));
        $this->assertSame(0, array_sum($byYear[2021]['gpax']));
    }

    public function test_studying_students_are_counted_by_study_year(): void
    {
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            [
                ['study_year' => 1, 'count' => 2],
                ['study_year' => 2, 'count' => 2],
                ['study_year' => 3, 'count' => 2],
                ['study_year' => 4, 'count' => 2],
            ],
            $data['studying_by_study_year'],
        );
    }

    public function test_data_quality_counts_zero_and_missing_gpax_of_studying_and_graduated_students(): void
    {
        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data');

        $this->assertSame(['gpax_zero' => 1, 'gpax_missing' => 1], $data['data_quality']);
    }

    public function test_plan_status_comes_from_the_courses_of_the_plan_not_passed_yet(): void
    {
        // Students 1-3 were studying in 2022, 4-8 in 2023 (all of fengone).
        Storage::disk('local')->put('data/enrollments_not_pass/1.json', '[]');
        Storage::disk('local')->put(
            'data/enrollments_not_pass/2.json',
            json_encode([['course_code' => '01204111', 'course_name' => 'Programming']]),
        );
        Storage::disk('local')->put('data/enrollments_not_pass/3.json', '[]');
        // The graduated student is not a studying student: ignored.
        Storage::disk('local')->put(
            'data/enrollments_not_pass/9.json',
            json_encode([['course_code' => '01204999']]),
        );

        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            ['on_plan' => 2, 'off_plan' => 1, 'unknown' => 5],
            $data['plan_status'],
        );
        $this->assertSame(['available' => true, 'reason' => null], $data['blocks']['plan_status']);

        $byYear = array_column($data['by_entry_year'], null, 'entry_year');

        $this->assertSame(['on_plan' => 2, 'off_plan' => 1, 'unknown' => 0], $byYear[2022]['plan']);
        $this->assertSame(['on_plan' => 0, 'off_plan' => 0, 'unknown' => 5], $byYear[2023]['plan']);
        $this->assertSame(['on_plan' => 0, 'off_plan' => 0, 'unknown' => 0], $byYear[2019]['plan']);
    }

    public function test_a_student_code_that_is_not_digits_is_never_read_as_a_path(): void
    {
        DB::table('students')->insert([
            'student_code' => '../1', 'teacher_id' => 'fengthree', 'teacher_full_name' => 'อาจารย์สาม',
            'student_status_id' => 1, 'entry_year' => 2022, 'study_year' => 1, 'gpax' => 3.00,
        ]);
        // data/enrollments_not_pass/../1.json would be data/1.json.
        Storage::disk('local')->put('data/1.json', json_encode([['course_code' => 'x']]));

        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengthree'))
            ->assertOk()
            ->json('data');

        $this->assertSame(['on_plan' => 0, 'off_plan' => 0, 'unknown' => 1], $data['plan_status']);
    }

    public function test_a_damaged_plan_file_counts_as_unknown(): void
    {
        Storage::disk('local')->put('data/enrollments_not_pass/1.json', '{not json');

        $data = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data');

        $this->assertSame(['on_plan' => 0, 'off_plan' => 0, 'unknown' => 8], $data['plan_status']);
    }

    public function test_unfinished_blocks_are_reported_as_unavailable_with_a_reason(): void
    {
        $blocks = $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', 'fengone'))
            ->assertOk()
            ->json('data.blocks');

        // No grade import in this test: no student can be compared with a plan.
        $this->assertSame(
            ['available' => false, 'reason' => 'grade_import_required'],
            $blocks['plan_status'],
        );
        $this->assertSame(
            ['available' => false, 'reason' => 'grade_history_required'],
            $blocks['gpa_by_study_year'],
        );
        $this->assertFalse($blocks['gpa_by_academic_term']['available']);
    }

    public function test_admin_gets_the_advisor_list_and_the_chosen_advisor(): void
    {
        $headers = $this->headersFor(['admin'], 'admin', 'mock-admin');

        $first = $this->getJson(self::URL, $headers)->assertOk()->json('data');

        // Sorted by Thai name: อาจารย์สอง (ส) comes before อาจารย์หนึ่ง (ห).
        $this->assertSame(
            [
                ['teacher_id' => 'fengtwo', 'teacher_full_name' => 'อาจารย์สอง', 'student_count' => 2],
                ['teacher_id' => 'fengone', 'teacher_full_name' => 'อาจารย์หนึ่ง', 'student_count' => 10],
            ],
            $first['advisors'],
        );
        $this->assertSame('fengtwo', $first['teacher_id']);
        $this->assertSame(2, $first['totals']['all']);

        $chosen = $this->getJson(self::URL.'?teacher_id=fengone', $headers)->assertOk()->json('data');

        $this->assertSame('fengone', $chosen['teacher_id']);
        $this->assertSame(10, $chosen['totals']['all']);
        $this->assertSame(8, $chosen['totals']['studying']);
    }

    public function test_unknown_advisor_gets_zero_counts(): void
    {
        $data = $this->getJson(
            self::URL.'?teacher_id=nobody',
            $this->headersFor(['admin'], 'admin', 'mock-admin'),
        )->assertOk()->json('data');

        $this->assertSame(0, $data['totals']['all']);
        $this->assertSame([], $data['by_entry_year']);
    }

    public function test_student_and_anonymous_requests_are_refused(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->getJson(self::URL, $this->headersFor(['student'], 'student', '6400000001'))
            ->assertForbidden();
    }

    public function test_teacher_token_without_nontri_id_is_refused(): void
    {
        $this->getJson(self::URL, $this->headersFor(['teacher'], 'teacher', null))
            ->assertForbidden();
    }

    private function insertStudent(
        string $code,
        ?string $teacherId,
        ?string $teacherName,
        int $statusId,
        int $entryYear,
        ?float $gpax,
        ?int $studyYear = null,
    ): void {
        DB::table('students')->insert([
            'student_code' => $code,
            'teacher_id' => $teacherId,
            'teacher_full_name' => $teacherName,
            'student_status_id' => $statusId,
            'entry_year' => $entryYear,
            'study_year' => $studyYear,
            'gpax' => $gpax,
        ]);
    }

    /**
     * @param  list<string>  $roles
     * @return array<string, string>
     */
    private function headersFor(array $roles, string $currentRole, ?string $nontriId): array
    {
        $claims = [
            'role' => $roles,
            'current_role' => $currentRole,
            'name' => 'Tester',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        if ($nontriId !== null) {
            $claims['nontri_id'] = $nontriId;
        }

        return ['Authorization' => 'Bearer '.app(JwtIssuer::class)->issue($claims)];
    }
}
