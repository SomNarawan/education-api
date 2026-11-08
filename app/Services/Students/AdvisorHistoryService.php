<?php

namespace App\Services\Students;

use App\Constants\StudySemester;
use App\Services\Storage\LocalJsonFileStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;

/**
 * Read-only history for the advisor dashboard: GPA by study year and plan
 * status per academic term. It only reads files the grade import already
 * writes (grade_attempts, study_plan); nothing is written and no other
 * service is changed. The result is cached until the students' own rows
 * (GPAX, updated_at) change, because reading every transcript is slow.
 *
 * Plan status of a term = every course slot of the study plan that was due up
 * to that term has been passed with the grades up to that term. The latest
 * term of a student gives the same result as data/enrollments_not_pass.
 */
class AdvisorHistoryService
{
    private const PASS_GRADES = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'P', 'S'];

    public function __construct(private readonly LocalJsonFileStore $files) {}

    public function summarize(Collection $students): array
    {
        if (app()->environment('testing')) {
            return $this->calculate($students);
        }

        return Cache::remember('advisor-history:v2:'.hash('sha256', $students->toJson()), now()->addDay(), fn () => $this->calculate($students));
    }

    private function calculate(Collection $students): array
    {
        $annual = [];
        $plans = [];
        $terms = [];
        $withHistory = 0;

        foreach ($students as $student) {
            $code = (string) $student->student_code;
            if (preg_match('/^\d{1,10}$/', $code) !== 1) {
                continue;
            }

            $payload = $this->read("data/grade_attempts/{$code}.json") ?? [];
            $attempts = array_is_list($payload) ? $payload : ($payload['grade_attempts'] ?? []);
            $attempts = array_values(array_filter($attempts, 'is_array'));
            if ($attempts === []) {
                continue;
            }

            $entry = (int) $student->entry_year;
            $member = $this->studentIdentity($student);
            // Every student of a plan has the same copy of it: read it once per plan.
            $planKey = (string) ($student->study_plan_id ?? $code);
            if (! array_key_exists($planKey, $plans)) {
                $plans[$planKey] = $this->read("data/study_plan/{$code}.json");
            }
            $plan = $plans[$planKey];

            $periods = [];
            foreach ($attempts as $attempt) {
                $period = $this->periodOf($attempt);
                if ($period === null) {
                    continue;
                }
                [$year, $semester] = $period;
                $key = $year.'-'.$semester;
                $periods[$key] ??= ['year' => $year, 'semester' => $semester, 'name' => null, 'study_year' => null];
                $periods[$key]['name'] = $this->semesterName($attempt, $semester, $periods[$key]['name']);
                $periods[$key]['study_year'] ??= is_numeric($attempt['study_year'] ?? null) ? (int) $attempt['study_year'] : null;
            }

            $annualGrades = $this->annualGpa($attempts, $entry);
            $withHistory += (int) ($periods !== [] || $annualGrades !== []);

            foreach ($annualGrades as $item) {
                $range = $this->range($item['gpa']);
                $annual[$item['study_year']][$entry] ??= ['entry_year' => $entry, 'ranges' => $this->ranges(), 'students' => []];
                $annual[$item['study_year']][$entry]['ranges'][$range]++;
                $annual[$item['study_year']][$entry]['students'][] = [...$member, 'gpa' => $item['gpa'], 'range' => $range];
            }

            ksort($periods, SORT_NATURAL);
            foreach ($periods as $key => $period) {
                $studyYear = $period['study_year'] ?? max(1, $period['year'] - $entry + 1);
                $standing = $this->standing($attempts, $plan, $period['year'], $period['semester'], $studyYear);
                $status = $standing['plan_status'];
                $name = (string) $period['name'];

                $terms[$key] ??= [
                    'academic_year' => $period['year'], 'semester_order' => $period['semester'],
                    'semester_name' => $name, 'term_label' => ($period['year'] + 543).' '.$name,
                    'on_plan' => 0, 'off_plan' => 0, 'retired' => 0, 'unknown' => 0,
                    'students' => [],
                ];
                $terms[$key][$status]++;
                $terms[$key]['students'][] = [
                    ...$member,
                    ...($status === 'off_plan' ? $this->pendingDetails($standing['pending_courses']) : []),
                    'term_study_year' => $period['year'] >= $entry ? $period['year'] - $entry + 1 : null,
                    'gpax' => $this->gpaxUpTo($attempts, $period['year'], $period['semester']),
                    'plan_status' => $status,
                    'status_source' => 'calculated',
                ];
            }
        }

        $years = [];
        for ($year = 1; $year <= 4; $year++) {
            $cohorts = $annual[$year] ?? [];
            ksort($cohorts);
            $years[] = ['study_year' => $year, 'cohorts' => array_values($cohorts)];
        }
        ksort($terms, SORT_NATURAL);

        return [
            'gpa_by_study_year' => $years,
            'academic_terms' => array_values($terms),
            'history_coverage' => ['with_grades' => $withHistory, 'without_grades' => $students->count() - $withHistory],
        ];
    }

    /**
     * Plan status and the courses still missing, using only the grades up to
     * the given term. A missing or unreadable plan is "unknown".
     *
     * @return array{plan_status: string, pending_courses: list<array<string, mixed>>|null}
     */
    private function standing(array $attempts, ?array $plan, int $year, int $semester, int $studyYear): array
    {
        $slots = is_array($plan['data'] ?? null) ? $plan['data'] : null;
        if ($slots === null || $slots === []) {
            return ['plan_status' => 'unknown', 'pending_courses' => null];
        }

        $passed = [];
        foreach ($attempts as $attempt) {
            $period = $this->periodOf($attempt);
            if ($period === null || $period > [$year, $semester] || ! $this->isPassed($attempt)) {
                continue;
            }
            $passed[(string) ($attempt['course_code'] ?? '')] = (float) ($attempt['credit'] ?? 0);
        }

        $due = array_values(array_filter($slots, fn ($slot): bool => is_array($slot)
            && [(int) ($slot['plan_study_year'] ?? PHP_INT_MAX), (int) ($slot['plan_semester_order'] ?? PHP_INT_MAX)] <= [$studyYear, $semester]));
        // Fixed courses claim their grades before group / elective slots do.
        usort($due, fn (array $a, array $b): int => $this->isGroupSlot($a) <=> $this->isGroupSlot($b));

        $used = [];
        $pending = [];
        foreach ($due as $slot) {
            $need = (float) ($slot['credit'] ?? 0);
            $got = 0.0;
            foreach ($passed as $courseCode => $credit) {
                if (isset($used[$courseCode]) || ! $this->slotMatches($slot, $courseCode)) {
                    continue;
                }
                $used[$courseCode] = true;
                $got += $credit;
                if ($got >= $need) {
                    break;
                }
            }
            if ($got + 0.0001 < $need) {
                $pending[] = [
                    'course_code' => $slot['course_code'] ?? null,
                    'course_name' => $slot['course_name'] ?? null,
                    'study_period' => $slot['plan_study_period'] ?? null,
                    'semester_year_be' => isset($slot['plan_year_be']) ? (int) $slot['plan_year_be'] : null,
                    'grade_letter' => null,
                    'enrollment_type' => $slot['enrollment_type'] ?? 'credit',
                    'credit' => round($need - $got, 2),
                ];
            }
        }

        return ['plan_status' => $pending === [] ? 'on_plan' : 'off_plan', 'pending_courses' => $pending];
    }

    private function isGroupSlot(array $slot): int
    {
        return (($slot['courses'] ?? []) !== [] || str_contains(strtolower((string) ($slot['course_code'] ?? '')), 'x')) ? 1 : 0;
    }

    private function slotMatches(array $slot, string $courseCode): bool
    {
        $candidates = [$slot['course_code'] ?? null];
        foreach (($slot['courses'] ?? []) as $course) {
            $candidates[] = is_array($course) ? ($course['course_code'] ?? null) : $course;
        }
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if ($candidate === $courseCode) {
                return true;
            }
            // Codes such as 01175xxx stand for every course with that prefix.
            if (str_contains(strtolower($candidate), 'x') && str_starts_with($courseCode, rtrim($candidate, 'xX'))) {
                return true;
            }
        }

        return false;
    }

    private function isPassed(array $attempt): bool
    {
        $letters = array_map('trim', explode(',', strtoupper((string) ($attempt['grade_letter'] ?? ''))));

        return in_array(end($letters), self::PASS_GRADES, true);
    }

    /** Credit-weighted GPAX of every graded attempt up to and including the term. */
    private function gpaxUpTo(array $attempts, int $year, int $semester): ?float
    {
        $credits = 0.0;
        $points = 0.0;
        foreach ($attempts as $attempt) {
            $period = $this->periodOf($attempt);
            if ($period === null || $period > [$year, $semester]
                || ($attempt['enrollment_type'] ?? 'credit') !== 'credit'
                || ! is_numeric($attempt['grade_point'] ?? null)
                || ! is_numeric($attempt['credit'] ?? null)
                || (float) $attempt['credit'] <= 0) {
                continue;
            }
            $credits += (float) $attempt['credit'];
            $points += (float) $attempt['credit'] * (float) $attempt['grade_point'];
        }

        return $credits > 0 ? round($points / $credits, 2) : null;
    }

    private function read(string $path): ?array
    {
        try {
            return $this->files->read($path);
        } catch (JsonException) {
            return null;
        }
    }

    private function periodOf(array $row): ?array
    {
        $year = filter_var($row['academic_year'] ?? null, FILTER_VALIDATE_INT);
        $semester = filter_var($row['semester_order'] ?? null, FILTER_VALIDATE_INT);

        return $year !== false && $year >= 1900 && $semester !== false && $semester > 0 ? [$year, $semester] : null;
    }

    private function semesterName(array $row, int $semester, ?string $fallback = null): string
    {
        foreach (['semester_name', 'semester', 'term_name_th'] as $field) {
            if (is_string($row[$field] ?? null) && trim($row[$field]) !== '' && ! is_numeric($row[$field])) {
                return trim($row[$field]);
            }
        }
        if ($fallback !== null) {
            return $fallback;
        }
        foreach (StudySemester::options() as $option) {
            if ($option['id'] === $semester) {
                return $option['name_th'];
            }
        }

        return 'ภาคเรียน '.$semester;
    }

    private function pendingDetails(?array $pending): array
    {
        return ['pending_courses' => $pending,
            'pending_count' => $pending === null ? null : count($pending),
            'pending_credits' => $pending === null ? null : array_sum(array_map(fn (array $course): float => ($course['enrollment_type'] ?? 'credit') === 'credit' ? (float) ($course['credit'] ?? 0) : 0, $pending))];
    }

    /** All semesters count toward their academic year; no semester-code allowlist. */
    private function annualGpa(array $attempts, int $entryYear): array
    {
        $years = [];
        foreach ($attempts as $attempt) {
            if (! is_numeric($attempt['academic_year'] ?? null)
                || ($attempt['enrollment_type'] ?? 'credit') !== 'credit'
                || ! is_numeric($attempt['grade_point'] ?? null)
                || ! is_numeric($attempt['credit'] ?? null)
                || (float) $attempt['credit'] <= 0) {
                continue;
            }
            $studyYear = (int) $attempt['academic_year'] - $entryYear + 1;
            if ($studyYear < 1 || $studyYear > 4) {
                continue;
            }
            $years[$studyYear] ??= ['credits' => 0, 'points' => 0];
            $years[$studyYear]['credits'] += (float) $attempt['credit'];
            $years[$studyYear]['points'] += (float) $attempt['credit'] * (float) $attempt['grade_point'];
        }
        ksort($years);
        $annual = [];
        foreach ($years as $studyYear => $totals) {
            $annual[] = ['study_year' => $studyYear, 'gpa' => round($totals['points'] / $totals['credits'], 2)];
        }

        return $annual;
    }

    private function studentIdentity(object $student): array
    {
        return ['id' => (int) $student->id, 'student_code' => (string) $student->student_code,
            'full_name_th' => $student->full_name_th ?? '',
            'study_year' => isset($student->study_year) ? (int) $student->study_year : null,
            'current_gpax' => isset($student->gpax) ? (float) $student->gpax : null];
    }

    private function ranges(): array
    {
        return array_fill_keys(['range_3_25_4_00', 'range_2_00_3_24', 'range_1_75_1_99', 'range_0_00_1_74', 'no_gpax'], 0);
    }

    private function range(float $value): string
    {
        return match (true) {
            $value >= 3.25 => 'range_3_25_4_00', $value >= 2 => 'range_2_00_3_24',
            $value >= 1.75 => 'range_1_75_1_99', default => 'range_0_00_1_74',
        };
    }
}
