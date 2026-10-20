<?php

namespace App\Services\Students;

use App\Constants\StudySemester;
use App\Contracts\CmisApi;
use App\Models\Student;
use App\Services\Storage\LocalJsonFileStore;
use App\Services\StudyPlans\StudentStudyPlanStore;
use App\Services\StudyPlans\StudyPlanPayload;
use JsonException;
use RuntimeException;

class StudentAcademicOverviewService
{
    public function __construct(
        private readonly StudentStudyPlanStore $studyPlanStore,
        private readonly StudyPlanPayload $studyPlanPayload,
        private readonly LocalJsonFileStore $jsonFiles,
        private readonly CmisApi $cmisApi,
    ) {}

    public function get(Student $student): array
    {
        $studentCode = trim((string) $student->student_code);
        $missingSections = [];
        $historyRows = $this->readOptionalJson(
            "data/graph/by_semester/{$studentCode}.json",
            'academic_history',
            $missingSections,
        );
        $history = $this->academicHistory($historyRows);
        $pendingRows = $this->readOptionalJson(
            "data/enrollments_not_pass/{$studentCode}.json",
            'pending_courses',
            $missingSections,
        );
        $nextTerm = $this->predictionTerm(
            (int) $student->study_year,
            (int) $student->study_semester,
            $history,
        );
        [$sourceImportId, $nextCourses] = $this->nextTermCourses(
            $student,
            $nextTerm,
            $missingSections,
        );

        return [
            'student_id' => (int) $student->id,
            'student_code' => $studentCode,
            'current_term' => [
                'year' => (int) $student->study_year,
                'semester' => $this->semesterName((int) $student->study_semester),
                'semester_order' => (int) $student->study_semester,
            ],
            'gpax' => $student->gpax === null ? null : (float) $student->gpax,
            'cumulative_credits' => $student->passed_credits === null
                ? null
                : (int) $student->passed_credits,
            'latest_gpa' => $student->gpa === null ? null : (float) $student->gpa,
            'latest_completed_term' => $this->latestCompletedTerm($history),
            'academic_history' => $history,
            'pending_courses' => $this->pendingCourses($pendingRows),
            'next_term' => [
                ...$nextTerm,
                'source_import_id' => $sourceImportId,
                'courses' => $nextCourses,
            ],
            'missing_sections' => array_values(array_unique($missingSections)),
        ];
    }

    public function unattemptedCourses(Student $student): array
    {
        $studentCode = trim((string) $student->student_code);
        $missingSections = [];
        $attemptPayload = $this->readOptionalJson(
            "data/grade_attempts/{$studentCode}.json",
            'grade_attempts',
            $missingSections,
        );
        $sourceImportId = $this->sourceImportId($attemptPayload);

        if ($sourceImportId === null
            && ! in_array('grade_attempts.source_import_id', $missingSections, true)) {
            $missingSections[] = 'grade_attempts.source_import_id';
        }

        $planPayload = $this->readOptionalStudyPlan(
            $studentCode,
            (int) $student->study_plan_id,
            'study_plan',
            $missingSections,
        );
        $pendingRows = $this->readOptionalJson(
            "data/enrollments_not_pass/{$studentCode}.json",
            'pending_courses',
            $missingSections,
        );
        $historyRows = $this->readOptionalJson(
            "data/graph/by_semester/{$studentCode}.json",
            'academic_history',
            $missingSections,
        );
        $history = $this->academicHistory($historyRows);
        $plan = $this->studyPlanPayload->selected(
            $planPayload,
            (int) $student->study_plan_id,
        );
        $planRows = is_array($plan['data'] ?? null) ? $plan['data'] : [];
        $nextTerm = $this->predictionTerm(
            (int) $student->study_year,
            (int) $student->study_semester,
            $history,
        );
        $excludedCodes = [];

        foreach ($this->gradeAttempts($attemptPayload) as $attempt) {
            $this->excludeCourseCode($excludedCodes, $attempt['course_code'] ?? null);
        }

        foreach ($pendingRows as $row) {
            if (is_array($row)) {
                $this->excludeCourseCode($excludedCodes, $row['course_code'] ?? null);
            }
        }

        foreach ($planRows as $row) {
            if (! is_array($row)
                || ! is_numeric($row['subject_id'] ?? null)
                || (int) ($row['plan_study_year'] ?? 0) !== $nextTerm['year']
                || (int) ($row['plan_semester_order'] ?? 0) !== $nextTerm['semester_order']) {
                continue;
            }

            $this->excludeCourseCode($excludedCodes, $row['course_code'] ?? null);
        }

        $coursesByCode = [];

        foreach ($this->planSubjects($planRows) as $course) {
            $courseCode = $this->normalizeCourseCode($course['course_code'] ?? null);

            if ($courseCode === null || isset($excludedCodes[$courseCode])) {
                continue;
            }

            $coursesByCode[$courseCode] ??= $course;
        }

        $courses = array_values($coursesByCode);
        usort(
            $courses,
            fn (array $left, array $right): int => strnatcasecmp(
                (string) $left['course_code'],
                (string) $right['course_code'],
            ),
        );

        return [
            'student_id' => (int) $student->id,
            'student_code' => $studentCode,
            'source_import_id' => $sourceImportId,
            'courses' => $courses,
            'missing_sections' => array_values(array_unique($missingSections)),
        ];
    }

    private function academicHistory(array $rows): array
    {
        $history = array_map(function (array $row): array {
            $semester = trim((string) ($row['semester'] ?? ''));

            return [
                'year' => (int) ($row['study_year'] ?? 0),
                'semester' => $semester,
                'semester_order' => $this->semesterOrder($semester),
                'academic_year' => isset($row['semester_year'])
                    ? (int) $row['semester_year']
                    : null,
                'academic_year_be' => isset($row['semester_year_be'])
                    ? (int) $row['semester_year_be']
                    : null,
                'credits' => $this->number($row['credits'] ?? 0),
                'gpa' => $this->number($row['gpa'] ?? 0),
                'gpax' => $this->number($row['gpax'] ?? 0),
            ];
        }, array_values(array_filter($rows, 'is_array')));

        usort($history, fn (array $left, array $right): int => [
            $left['academic_year'] ?? 0,
            $left['semester_order'],
        ] <=> [
            $right['academic_year'] ?? 0,
            $right['semester_order'],
        ]);

        return $history;
    }

    private function pendingCourses(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'course_code' => $this->nullableString($row['course_code'] ?? null),
            'course_name_th' => $this->nullableString($row['course_name'] ?? null),
            'credit' => is_numeric($row['credit'] ?? null)
                ? $this->number($row['credit'])
                : null,
            'previous_grade' => $this->nullableString($row['grade_letter'] ?? null),
            'course_category' => $this->nullableString($row['course_category'] ?? null),
            'course_sub_category' => $this->nullableString($row['course_sub_category'] ?? null),
            'course_group' => $this->nullableString($row['course_group'] ?? null),
        ], array_values(array_filter($rows, 'is_array')));
    }

    private function predictionTerm(
        int $currentYear,
        int $currentSemester,
        array $history,
    ): array {
        $currentYear = max(1, $currentYear);
        $hasCurrentTermGrades = false;

        foreach ($history as $term) {
            if ($term['year'] === $currentYear
                && $term['semester_order'] === $currentSemester) {
                $hasCurrentTermGrades = true;
                break;
            }
        }

        if (! $hasCurrentTermGrades) {
            return [
                'year' => $currentYear,
                'semester' => $this->semesterName($currentSemester),
                'semester_order' => $currentSemester,
            ];
        }

        if ($currentSemester === StudySemester::FIRST) {
            return [
                'year' => $currentYear,
                'semester' => StudySemester::nameTh(StudySemester::SECOND),
                'semester_order' => StudySemester::SECOND,
            ];
        }

        return [
            'year' => $currentYear + 1,
            'semester' => StudySemester::nameTh(StudySemester::FIRST),
            'semester_order' => StudySemester::FIRST,
        ];
    }

    private function latestCompletedTerm(array $history): ?array
    {
        $latestTerm = $history[array_key_last($history)] ?? null;

        if ($latestTerm === null) {
            return null;
        }

        return [
            'year' => $latestTerm['year'],
            'semester' => $latestTerm['semester'],
            'semester_order' => $latestTerm['semester_order'],
            'academic_year' => $latestTerm['academic_year'],
            'academic_year_be' => $latestTerm['academic_year_be'],
        ];
    }

    private function nextTermCourses(
        Student $student,
        array $nextTerm,
        array &$missingSections,
    ): array {
        $studentCode = trim((string) $student->student_code);
        $attemptPayload = $this->readOptionalJson(
            "data/grade_attempts/{$studentCode}.json",
            'grade_attempts',
            $missingSections,
        );
        $sourceImportId = $this->sourceImportId($attemptPayload);

        if ($sourceImportId === null
            && ! in_array('grade_attempts.source_import_id', $missingSections, true)) {
            $missingSections[] = 'grade_attempts.source_import_id';
        }

        $planPayload = $this->readOptionalStudyPlan(
            $studentCode,
            (int) $student->study_plan_id,
            'next_term.courses',
            $missingSections,
        );
        $plan = $this->studyPlanPayload->selected(
            $planPayload,
            (int) $student->study_plan_id,
        );
        $rows = is_array($plan['data'] ?? null) ? $plan['data'] : [];
        $courses = [];

        foreach ($rows as $row) {
            if (! is_array($row)
                || ! is_numeric($row['subject_id'] ?? null)
                || (int) ($row['plan_study_year'] ?? 0) !== $nextTerm['year']
                || (int) ($row['plan_semester_order'] ?? 0) !== $nextTerm['semester_order']) {
                continue;
            }

            $courses[] = [
                'course_code' => $this->nullableString($row['course_code'] ?? null),
                'course_name_th' => $this->nullableString($row['course_name'] ?? null),
                'credit' => is_numeric($row['credit'] ?? null)
                    ? $this->number($row['credit'])
                    : null,
                'course_category' => $this->nullableString($row['course_category'] ?? null),
                'course_sub_category' => $this->nullableString($row['course_sub_category'] ?? null),
                'course_group' => $this->nullableString($row['course_group'] ?? null),
            ];
        }

        return [$sourceImportId, $courses];
    }

    private function gradeAttempts(array $payload): array
    {
        $attempts = array_is_list($payload)
            ? $payload
            : ($payload['grade_attempts'] ?? []);

        return is_array($attempts)
            ? array_values(array_filter($attempts, 'is_array'))
            : [];
    }

    private function planSubjects(array $planRows): array
    {
        $courses = [];

        foreach ($planRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (is_numeric($row['subject_id'] ?? null)) {
                $courses[] = $this->planSubjectRecord($row, $row);
            }

            foreach (($row['courses'] ?? []) as $course) {
                if (is_array($course) && is_numeric($course['subject_id'] ?? null)) {
                    $courses[] = $this->planSubjectRecord($course, $row);
                }
            }
        }

        return $courses;
    }

    private function planSubjectRecord(array $course, array $context): array
    {
        return [
            'course_code' => $this->nullableString($course['course_code'] ?? null),
            'course_name_th' => $this->nullableString($course['course_name'] ?? null),
            'credit' => is_numeric($course['credit'] ?? null)
                ? $this->number($course['credit'])
                : null,
            'course_category' => $this->nullableString($context['course_category'] ?? null),
            'course_sub_category' => $this->nullableString($context['course_sub_category'] ?? null),
            'course_group' => $this->nullableString($context['course_group'] ?? null),
        ];
    }

    private function excludeCourseCode(array &$excludedCodes, mixed $courseCode): void
    {
        $normalizedCode = $this->normalizeCourseCode($courseCode);

        if ($normalizedCode !== null) {
            $excludedCodes[$normalizedCode] = true;
        }
    }

    private function normalizeCourseCode(mixed $courseCode): ?string
    {
        $courseCode = $this->nullableString($courseCode);

        if ($courseCode === null) {
            return null;
        }

        return strtoupper((string) preg_replace('/\s+/', '', $courseCode));
    }

    private function sourceImportId(array $payload): ?int
    {
        $value = $payload['source_import_id'] ?? null;

        if (filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0) {
            return (int) $value;
        }

        if (! array_is_list($payload)) {
            return null;
        }

        $ids = array_filter(array_map(
            fn (mixed $attempt): ?int => is_array($attempt)
                && filter_var($attempt['source_import_id'] ?? null, FILTER_VALIDATE_INT) !== false
                && (int) $attempt['source_import_id'] > 0
                    ? (int) $attempt['source_import_id']
                    : null,
            $payload,
        ));

        return $ids === [] ? null : max($ids);
    }

    private function semesterName(int $semester): string
    {
        return in_array($semester, StudySemester::values(), true)
            ? StudySemester::nameTh($semester)
            : "ภาคการเรียนที่ {$semester}";
    }

    private function semesterOrder(string $semester): int
    {
        return StudySemester::fromInput($semester) ?? 0;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function number(mixed $value): int|float
    {
        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    /**
     * @param  array<int, string>  $missingSections
     *
     * @throws JsonException
     */
    private function readOptionalJson(
        string $path,
        string $section,
        array &$missingSections,
    ): array {
        try {
            $payload = $this->jsonFiles->read($path);
        } catch (JsonException) {
            throw new JsonException($section);
        }

        if ($payload === null) {
            $missingSections[] = $section;

            return [];
        }

        return $payload;
    }

    /**
     * @param  array<int, string>  $missingSections
     *
     * @throws JsonException
     */
    private function readOptionalStudyPlan(
        string $studentCode,
        int $studyPlanId,
        string $section,
        array &$missingSections,
    ): array {
        try {
            $payload = $this->studyPlanStore->get($studentCode);
        } catch (JsonException) {
            throw new JsonException($section);
        }

        if ($payload === null && $studyPlanId > 0) {
            try {
                $payload = $this->cmisApi->getCurriculumPlanCourses($studyPlanId);
            } catch (RuntimeException) {
                $payload = null;
            }

            if ($payload !== null) {
                try {
                    $this->studyPlanStore->put($studentCode, $payload);
                } catch (JsonException|RuntimeException) {
                    // The fetched plan remains usable even if the local cache cannot be written.
                }
            }
        }

        if ($payload === null) {
            $missingSections[] = $section;

            return [];
        }

        return $payload;
    }
}
