<?php

namespace App\Services\Students;

use App\Constants\Status;
use App\Services\Storage\LocalJsonFileStore;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Counts of the students of one advisor for the advisor overview page: by
 * status, by GPAX range (students who are still studying or have graduated),
 * by study year, by plan status and by entry year.
 *
 * Plan status of a studying student comes from the grade import, which already
 * compares the grades with the study plan: data/enrollments_not_pass/{code}.json
 * lists the courses of the plan that are due (up to the latest imported term)
 * but not passed or not taken. A non-empty list = off plan, an empty list = on
 * plan, no file = unknown (grades were never imported).
 *
 * Blocks that cannot be computed yet are listed in `blocks` with a reason, so
 * the page can show them as "waiting" instead of inventing numbers.
 */
class AdvisorSummaryService
{
    public const PLAN_ON = 'on_plan';

    public const PLAN_OFF = 'off_plan';

    public const PLAN_UNKNOWN = 'unknown';

    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
        private readonly AdvisorHistoryService $history,
    ) {}

    public const RANGE_HIGH = 'range_3_25_4_00';

    public const RANGE_GOOD = 'range_2_00_3_24';

    public const RANGE_WARNING = 'range_1_75_1_99';

    public const RANGE_RISK = 'range_0_00_1_74';

    public const NO_GPAX = 'no_gpax';

    /**
     * @return list<array{teacher_id: string, teacher_full_name: string|null, student_count: int}>
     */
    public function advisors(): array
    {
        return DB::table('students')
            ->whereNull('deleted_at')
            ->whereNotNull('teacher_id')
            ->where('teacher_id', '<>', '')
            ->groupBy('teacher_id')
            ->selectRaw('teacher_id, MAX(teacher_full_name) AS teacher_full_name, COUNT(*) AS student_count')
            ->orderBy('teacher_full_name')
            ->orderBy('teacher_id')
            ->get()
            ->map(fn (object $row): array => [
                'teacher_id' => (string) $row->teacher_id,
                'teacher_full_name' => $row->teacher_full_name,
                'student_count' => (int) $row->student_count,
            ])
            ->all();
    }

    public function summarize(?string $teacherId): array
    {
        $rows = $teacherId === null
            ? collect()
            : DB::table('students')
                ->join('student_statuses', 'student_statuses.id', '=', 'students.student_status_id')
                ->leftJoin('titles', 'titles.id', '=', 'students.title_id')
                ->whereNull('students.deleted_at')
                ->where('students.teacher_id', $teacherId)
                ->get([
                    'students.id',
                    'students.student_code',
                    'students.first_name_th',
                    'students.last_name_th',
                    'titles.title_abbr_th',
                    'students.study_plan_id',
                    'students.entry_year',
                    'students.study_year',
                    'students.gpax',
                    'students.updated_at',
                    'student_statuses.status_name',
                ]);

        $rows->each(function (object $row): void {
            $row->full_name_th = trim(($row->title_abbr_th ?? '').($row->first_name_th ?? '').' '.($row->last_name_th ?? ''));
            unset($row->title_abbr_th, $row->first_name_th, $row->last_name_th);
        });

        $history = $this->history->summarize($rows);

        $totals = ['all' => 0, 'studying' => 0, 'graduated' => 0, 'retired' => 0, 'other' => 0];
        $studyingGpax = $this->emptyRanges();
        $graduatedGpax = $this->emptyRanges();
        $studyingByStudyYear = [];
        $planStatus = $this->emptyPlanStatus();
        $byEntryYear = [];
        $gpaxZero = 0;
        $gpaxMissing = 0;
        $offPlanStudents = [];

        foreach ($rows as $row) {
            $kind = $this->statusKind((string) $row->status_name);
            $year = (int) $row->entry_year;

            $byEntryYear[$year] ??= [
                'entry_year' => $year,
                'total' => 0,
                'studying' => 0,
                'graduated' => 0,
                'retired' => 0,
                'other' => 0,
                'gpax' => $this->emptyRanges(),
                'graduated_gpax' => $this->emptyRanges(),
                'plan' => $this->emptyPlanStatus(),
                'students' => [],
            ];

            $totals['all']++;
            $totals[$kind]++;
            $byEntryYear[$year]['total']++;
            $byEntryYear[$year][$kind]++;
            $student = [
                'id' => (int) $row->id, 'student_code' => (string) $row->student_code,
                'full_name_th' => $row->full_name_th,
                'study_year' => $row->study_year === null ? null : (int) $row->study_year,
                'current_gpax' => $row->gpax === null ? null : (float) $row->gpax,
                'gpax' => $row->gpax === null ? null : (float) $row->gpax,
                'status' => $kind,
            ];
            $byEntryYear[$year]['students'][] = $student;

            if ($kind !== 'studying' && $kind !== 'graduated') {
                continue;
            }

            $range = $this->rangeOf($row->gpax);

            if ($range === self::NO_GPAX) {
                $gpaxMissing++;
            } elseif ((float) $row->gpax === 0.0) {
                $gpaxZero++;
            }

            if ($kind === 'studying') {
                $studyingGpax[$range]++;
                $byEntryYear[$year]['gpax'][$range]++;

                if ($row->study_year !== null) {
                    $studyingByStudyYear[(int) $row->study_year] ??= 0;
                    $studyingByStudyYear[(int) $row->study_year]++;
                }

                $pending = $this->pendingCoursesOf((string) $row->student_code);
                $plan = $pending === null ? self::PLAN_UNKNOWN : ($pending === [] ? self::PLAN_ON : self::PLAN_OFF);
                $planStatus[$plan]++;
                $byEntryYear[$year]['plan'][$plan]++;
                if ($plan === self::PLAN_OFF) {
                    $offPlanStudents[] = [...$student, 'plan_status' => self::PLAN_OFF,
                        'pending_count' => count($pending),
                        'pending_credits' => array_sum(array_map(fn (array $course): float => ($course['enrollment_type'] ?? 'credit') === 'credit' ? (float) ($course['credit'] ?? 0) : 0, $pending)),
                        'pending_courses' => $pending];
                }
            } else {
                $graduatedGpax[$range]++;
                $byEntryYear[$year]['graduated_gpax'][$range]++;
            }
        }

        ksort($byEntryYear);
        ksort($studyingByStudyYear);
        usort($offPlanStudents, fn (array $a, array $b): int => $b['pending_count'] <=> $a['pending_count'] ?: strcmp($a['student_code'], $b['student_code']));

        return [
            'teacher_id' => $teacherId,
            ...$history,
            'teacher_full_name' => $teacherId === null
                ? null
                : DB::table('students')->where('teacher_id', $teacherId)->value('teacher_full_name'),
            'totals' => $totals,
            'studying_gpax' => $studyingGpax,
            'graduated_gpax' => $graduatedGpax,
            'studying_by_study_year' => array_map(
                fn (int $studyYear, int $count): array => ['study_year' => $studyYear, 'count' => $count],
                array_keys($studyingByStudyYear),
                array_values($studyingByStudyYear),
            ),
            'plan_status' => $planStatus,
            'off_plan_students' => $offPlanStudents,
            'by_entry_year' => array_values($byEntryYear),
            // GPAX of a student who is studying or has graduated that is exactly
            // 0.00 or missing usually means grades were never imported.
            'data_quality' => [
                'gpax_zero' => $gpaxZero,
                'gpax_missing' => $gpaxMissing,
            ],
            'blocks' => $this->pendingBlocks($planStatus, $history),
        ];
    }

    /**
     * @param  array<string, int>  $planStatus
     * @return array<string, array{available: bool, reason: string|null}>
     */
    private function pendingBlocks(array $planStatus, array $history): array
    {
        $planKnown = $planStatus[self::PLAN_ON] + $planStatus[self::PLAN_OFF] > 0;

        return [
            // Plan status needs the grade import of at least one studying student.
            'plan_status' => $planKnown
                ? ['available' => true, 'reason' => null]
                : ['available' => false, 'reason' => 'grade_import_required'],
            // Grade ranges per study year and per academic term need the grade
            // history of every term, which comes from the grade import.
            'gpa_by_study_year' => ['available' => $history['history_coverage']['with_grades'] > 0, 'reason' => $history['history_coverage']['with_grades'] > 0 ? null : 'grade_history_required'],
            'gpa_by_academic_term' => ['available' => $history['academic_terms'] !== [], 'reason' => $history['academic_terms'] !== [] ? null : 'grade_history_required'],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyPlanStatus(): array
    {
        return [self::PLAN_ON => 0, self::PLAN_OFF => 0, self::PLAN_UNKNOWN => 0];
    }

    private function pendingCoursesOf(string $studentCode): ?array
    {
        // The code becomes part of a file path: digits only.
        if (preg_match('/^\d{1,10}$/', $studentCode) !== 1) {
            return null;
        }

        try {
            $notPass = $this->jsonFiles->read("data/enrollments_not_pass/{$studentCode}.json");
        } catch (JsonException) {
            return null;
        }

        if ($notPass === null) {
            return null;
        }

        // This is the current outstanding list, not the history of failed attempts.
        return array_values(array_filter($notPass, function (mixed $course): bool {
            if (! is_array($course)) {
                return false;
            }
            $grades = array_map('trim', explode(',', strtoupper((string) ($course['grade_letter'] ?? ''))));

            return ! in_array(end($grades), ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'P', 'S'], true);
        }));
    }

    /**
     * @return array<string, int>
     */
    private function emptyRanges(): array
    {
        return [
            self::RANGE_HIGH => 0,
            self::RANGE_GOOD => 0,
            self::RANGE_WARNING => 0,
            self::RANGE_RISK => 0,
            self::NO_GPAX => 0,
        ];
    }

    private function rangeOf(mixed $gpax): string
    {
        if ($gpax === null || ! is_numeric($gpax)) {
            return self::NO_GPAX;
        }

        $value = (float) $gpax;

        return match (true) {
            $value >= 3.25 => self::RANGE_HIGH,
            $value >= 2.00 => self::RANGE_GOOD,
            $value >= 1.75 => self::RANGE_WARNING,
            default => self::RANGE_RISK,
        };
    }

    private function statusKind(string $statusName): string
    {
        return match ($statusName) {
            Status::STUDYING => 'studying',
            Status::GRADUATED => 'graduated',
            Status::RETIRED => 'retired',
            default => 'other',
        };
    }
}
