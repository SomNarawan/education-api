<?php

namespace App\Services\Students;

use App\Constants\StudySemester;
use App\Services\Storage\LocalJsonFileStore;
use App\Services\StudyPlans\StudentStudyPlanStore;
use App\Services\StudyPlans\StudyPlanPayload;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;

/**
 * Read-only history for the advisor dashboard: GPA by study year and plan
 * status per academic term. It reads saved grade attempts and study plans.
 * The result is cached until the students' own rows
 * (GPAX, updated_at) change, because reading every transcript is slow.
 *
 * Plan status of a term = every course slot of the study plan that was due up
 * to that term has been passed with the grades up to that term. The latest
 * term uses the requirement unit from the curriculum when comparing results.
 */
class AdvisorHistoryService
{
    private const PASS_GRADES = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'P', 'S'];

    /** @var array<int, array|null> */
    private array $plans = [];

    public function __construct(
        private readonly LocalJsonFileStore $files,
        private readonly StudentStudyPlanStore $studyPlans,
        private readonly StudyPlanPayload $studyPlanPayload,
        private readonly AdvisorPlanUnits $units,
    ) {}

    /**
     * GPA by study year and the plan status counts per term. The students of a
     * term are not part of it: they are loaded when a term is opened.
     */
    public function summarize(Collection $students): array
    {
        if (app()->environment('testing')) {
            return $this->split($this->calculate($students))[0];
        }

        $key = $this->cacheKey($students);

        return Cache::get($key.':summary') ?? $this->store($students, $key);
    }

    /** The students of one term, with their plan status and the courses still missing at that term. */
    public function termStudents(Collection $students, int $academicYear, int $semester): array
    {
        if (app()->environment('testing')) {
            return $this->split($this->calculate($students))[1]["{$academicYear}-{$semester}"] ?? [];
        }

        $key = $this->cacheKey($students);
        if (! Cache::has($key.':summary')) {
            $this->store($students, $key);
        }

        return Cache::get($key.":term:{$academicYear}-{$semester}", []);
    }

    /** The latest term with grades of a student, or null when there are none. */
    public function latestTermOf(string $studentCode): ?array
    {
        if (preg_match('/^\d{1,10}$/', $studentCode) !== 1) {
            return null;
        }

        $payload = $this->read("data/grade_attempts/{$studentCode}.json") ?? [];
        $attempts = array_values(array_filter(array_is_list($payload) ? $payload : ($payload['grade_attempts'] ?? []), 'is_array'));
        $latest = null;
        foreach ($attempts as $attempt) {
            $period = $this->periodOf($attempt);
            if ($period !== null && ($latest === null || $period > $latest['period'])) {
                $latest = ['period' => $period, 'attempt' => $attempt];
            }
        }
        if ($latest === null) {
            return null;
        }

        [$year, $semester] = $latest['period'];

        return ['academic_year' => $year, 'semester_order' => $semester,
            'term_label' => ($year + 543).' '.$this->semesterName($latest['attempt'], $semester)];
    }

    private function cacheKey(Collection $students): string
    {
        return 'advisor-history:v6:'.hash('sha256', $students->toJson());
    }

    private function store(Collection $students, string $key): array
    {
        [$summary, $termStudents] = $this->split($this->calculate($students));
        $ttl = now()->addSeconds($this->units->lookupFailed ? 15 : 300);
        foreach ($termStudents as $termKey => $list) {
            Cache::put("{$key}:term:{$termKey}", $list, $ttl);
        }
        Cache::put($key.':summary', $summary, $ttl);

        return $summary;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, list<array<string, mixed>>>}
     */
    private function split(array $full): array
    {
        $termStudents = [];
        $terms = [];
        foreach ($full['academic_terms'] as $term) {
            $termStudents["{$term['academic_year']}-{$term['semester_order']}"] = $term['students'];
            unset($term['students']);
            $terms[] = $term;
        }

        return [[...$full, 'academic_terms' => $terms], $termStudents];
    }

    /** The study plan of a student: the shared copy of the plan, or the old per-student file. */
    private function planOf(int $planId, string $studentCode): ?array
    {
        if (! array_key_exists($planId, $this->plans)) {
            try {
                $this->plans[$planId] = $this->studyPlans->getCourses($planId);
            } catch (JsonException|LockTimeoutException) {
                $this->plans[$planId] = null;
            }
        }

        try {
            $payload = $this->plans[$planId] ?? $this->studyPlans->getLegacyCourses($studentCode);

            return $payload === null ? null : $this->studyPlanPayload->selected($payload, $planId);
        } catch (JsonException) {
            return null;
        }
    }

    /** Preserve the importer's credit allocation, but reassess placements that need unit-aware evidence. */
    public function reconcileUnits(string $studentCode, array $pending, int $studyPlanId): array
    {
        $plan = $this->planOf($studyPlanId, $studentCode);
        $slots = $plan['data'] ?? [];
        $payload = $this->read("data/grade_attempts/{$studentCode}.json") ?? [];
        $source = $payload['grade_attempts'] ?? $payload;
        $attempts = is_array($source) ? array_filter($source, 'is_array') : [];
        $latest = null;
        $studyYear = null;
        $nonCreditAttempts = [];
        foreach ($attempts as $attempt) {
            $period = $this->periodOf($attempt);
            if ($period !== null && AdvisorPlanUnits::normalize($attempt['enrollment_type'] ?? 'credit') !== 'credit') {
                $nonCreditAttempts[trim((string) ($attempt['course_code'] ?? ''))] = true;
            }
            if ($period !== null && ($latest === null || $period > $latest)) {
                $latest = $period;
                $studyYear = $attempt['study_year'] ?? null;
            }
        }
        $confirmed = [];
        $unassessed = [];
        $planId = (int) ($plan['curriculum_plan_id'] ?? 0);
        $reviews = [];
        $curriculumUnavailable = false;
        if (is_array($slots) && $slots && $latest !== null && is_numeric($studyYear)) {
            $standing = $this->standing($attempts, $plan, $latest[0], $latest[1], (int) $studyYear);
            $reviews = $standing['unit_reviews'];
            $curriculumUnavailable = $standing['curriculum_unavailable'];
        }
        foreach ($pending as $index => $row) {
            $unit = AdvisorPlanUnits::normalize($row['enrollment_type'] ?? 'credit');
            $identity = trim((string) ($row['course_code'] ?? ''));
            $matches = [];
            $direct = [];
            foreach (is_array($slots) ? $slots : [] as $id => $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                if ($identity !== '' && $identity !== '-' && $this->slotMatches($slot, $identity)) {
                    $matches[] = $id;
                    if (($slot['course_code'] ?? null) === $identity) {
                        $direct[] = $id;
                    }
                } elseif (! empty($row['course_name']) && $row['course_name'] === ($slot['course_name'] ?? null)
                    && ! empty($row['study_period']) && $row['study_period'] === ($slot['plan_study_period'] ?? null)) {
                    $direct[] = $id;
                }
            }
            $matches = $direct ?: $matches;
            $reviewed = array_intersect($matches, array_keys($reviews));
            $ambiguousUnit = $unit === null || ($matches === [] && $unit === 'credit'
                && (isset($nonCreditAttempts[$identity]) || ($identity === '-' && $nonCreditAttempts !== [])));
            foreach ($matches as $id) {
                if ($this->units->forSlot($slots[$id], $planId, $curriculumUnavailable) !== $unit) {
                    $ambiguousUnit = true;
                }
            }
            // Preserve the imported course even when its quantity has no reliable unit.
            if ($ambiguousUnit) {
                $pending[$index]['credit'] = null;
                $pending[$index]['enrollment_type'] = null;
            } elseif (isset($row['enrollment_type'])) {
                $pending[$index]['enrollment_type'] = $unit;
            }
            if ($matches && count($reviewed) === count($matches)) {
                continue;
            }
            if ($ambiguousUnit || $unit !== 'credit' || $reviewed !== []) {
                $unassessed[] = $row;
            } else {
                if (isset($row['enrollment_type'])) {
                    $row['enrollment_type'] = $unit;
                }
                $confirmed[] = $row;
            }
        }
        if ($curriculumUnavailable) {
            // This file records the current import. It cannot establish the standing of a past term.
            return ['confirmed' => $pending, 'unassessed' => [], 'used_import' => true];
        }
        foreach ($reviews as $row) {
            if ($row === null) {
                continue;
            }
            unset($row['_slot']);
            if (! empty($row['unit_unknown'])) {
                $unassessed[] = $row;
            } else {
                $confirmed[] = $row;
            }
        }

        return ['confirmed' => $confirmed, 'unassessed' => $unassessed, 'used_import' => false];
    }

    private function calculate(Collection $students): array
    {
        $annual = [];
        $terms = [];
        $withHistory = 0;

        foreach ($students as $student) {
            $code = (string) $student->student_code;
            if (preg_match('/^\d{1,10}$/', $code) !== 1) {
                continue;
            }

            $payload = $this->read("data/grade_attempts/{$code}.json") ?? [];
            $attempts = array_is_list($payload) ? $payload : ($payload['grade_attempts'] ?? []);
            $attempts = is_array($attempts) ? array_values(array_filter($attempts, 'is_array')) : [];
            if ($attempts === []) {
                continue;
            }

            $entry = (int) $student->entry_year;
            $member = $this->studentIdentity($student);
            $planId = (int) $student->study_plan_id;
            $plan = $this->planOf($planId, $code);

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
            $standings = [];
            foreach ($periods as $key => $period) {
                $standings[$key] = $this->standing($attempts, $plan, $period['year'], $period['semester'], $period['study_year'] ?? max(1, $period['year'] - $entry + 1));
            }
            foreach ($periods as $key => $period) {
                $standing = $standings[$key];
                $standing['pending_courses'] = $this->withCurrentStatus($standing['pending_courses'], $key, $standings, $periods);
                $status = $standing['plan_status'];
                $name = (string) $period['name'];

                $terms[$key] ??= [
                    'academic_year' => $period['year'], 'semester_order' => $period['semester'],
                    'semester_name' => $name, 'term_label' => ($period['year'] + 543).' '.$name,
                    'on_plan' => 0, 'off_plan' => 0, 'retired' => 0, 'unknown' => 0,
                    'students' => [],
                ];
                if ($standing['curriculum_unavailable']) {
                    $terms[$key]['incomplete_curriculum'] = true;
                }
                $terms[$key][$status] = ($terms[$key][$status] ?? 0) + 1;
                $terms[$key]['students'][] = [
                    ...$member,
                    ...($status === 'off_plan' ? self::pendingDetails($standing['pending_courses']) : []),
                    'term_study_year' => $period['year'] >= $entry ? $period['year'] - $entry + 1 : null,
                    'gpax' => $this->gpaxUpTo($attempts, $period['year'], $period['semester']),
                    'unassessed_courses' => array_values(array_map(fn ($row) => array_diff_key($row, ['_slot' => true]), $standing['unassessed'])),
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
     * @return array{plan_status: string, pending_courses: ?array, unassessed: array, unit_reviews: array, curriculum_unavailable: bool}
     */
    private function standing(array $attempts, ?array $plan, int $year, int $semester, int $studyYear): array
    {
        $slots = is_array($plan['data'] ?? null) ? $plan['data'] : null;
        if ($slots === null || $slots === []) {
            return ['plan_status' => 'unknown', 'pending_courses' => null, 'unassessed' => [], 'unit_reviews' => [], 'curriculum_unavailable' => false];
        }

        $results = [];
        foreach ($attempts as $attempt) {
            if (! is_array($attempt) || ($attempt['_is_in_plan'] ?? true) === false) {
                continue;
            }
            $period = $this->periodOf($attempt);
            $code = trim((string) ($attempt['course_code'] ?? ''));
            if ($code === '' || $period === null || $period > [$year, $semester]) {
                continue;
            }
            $unit = AdvisorPlanUnits::normalize($attempt['enrollment_type'] ?? 'credit') ?? 'unknown';
            $passed = $this->isPassed($attempt);
            $value = $passed ? ($attempt['credit'] ?? null) : 0;
            $results[$code][$unit]['passed'] = ($results[$code][$unit]['passed'] ?? false) || $passed;
            if (is_numeric($value) && $value >= 0) {
                $results[$code][$unit]['amount'] = max((float) $value, $results[$code][$unit]['amount'] ?? 0);
            } else {
                $results[$code][$unit]['incomplete'] = true;
            }
        }

        $due = array_filter($slots, fn ($slot): bool => is_array($slot)
            && [(int) ($slot['plan_study_year'] ?? PHP_INT_MAX), (int) ($slot['plan_semester_order'] ?? PHP_INT_MAX)] <= [$studyYear, $semester]);
        uasort($due, fn (array $a, array $b): int => $this->isGroupSlot($a) <=> $this->isGroupSlot($b));

        $used = [];
        $pending = [];
        $unassessed = [];
        $reviews = [];
        $curriculumUnavailable = false;
        foreach ($due as $slotId => $slot) {
            $unit = $this->units->forSlot($slot, (int) ($plan['curriculum_plan_id'] ?? 0), $curriculumUnavailable);
            $need = $slot['credit'] ?? null;
            $uncertain = $unit === null || $unit === 'audit' || ! is_numeric($need) || $need < 0;
            $review = $uncertain || $unit !== 'credit';
            $got = 0.0;
            foreach ($results as $code => $byUnit) {
                if (isset($used[$code]) || ! $this->slotMatches($slot, (string) $code)) {
                    continue;
                }
                $review = $review || array_keys($byUnit) !== ['credit'];
                unset($byUnit['audit']);
                $earned = 0.0;
                foreach ($byUnit as $actual => $result) {
                    if ($unit === 'course' && $actual !== 'unknown') {
                        $earned = max($earned, $result['passed'] ? 1.0 : 0.0);
                    } elseif ($actual === $unit) {
                        $earned = $result['amount'] ?? 0.0;
                        $uncertain = $uncertain || ! empty($result['incomplete']);
                    } else {
                        $uncertain = true;
                    }
                }
                if ($earned > 0) {
                    $used[$code] = true;
                    $got += $earned;
                }
                if (is_numeric($need) && $got >= $need) {
                    break;
                }
            }
            $complete = $unit !== null && $unit !== 'audit' && is_numeric($need) && $need >= 0 && $got + 0.0001 >= $need;
            $row = null;
            if (! $complete) {
                $row = [
                    '_slot' => $slotId, 'course_code' => $slot['course_code'] ?? null,
                    'course_name' => $slot['course_name'] ?? null, 'study_period' => $slot['plan_study_period'] ?? null,
                    'semester_year_be' => isset($slot['plan_year_be']) ? (int) $slot['plan_year_be'] : null,
                    'grade_letter' => $this->failedGrades($attempts, $slot, $year, $semester),
                    'enrollment_type' => $unit, 'credit' => $uncertain ? null : round($need - $got, 2),
                ];
                if ($uncertain) {
                    $row['unit_unknown'] = true;
                    $unassessed[] = $row;
                } else {
                    $pending[] = $row;
                }
            }
            if ($review) {
                $reviews[$slotId] = $row;
            }
        }

        $status = $curriculumUnavailable ? 'curriculum_unavailable' : ($unassessed ? 'unknown' : 'on_plan');

        return ['plan_status' => $pending ? 'off_plan' : $status, 'curriculum_unavailable' => $curriculumUnavailable,
            'pending_courses' => $pending, 'unassessed' => $unassessed, 'unit_reviews' => $reviews];
    }

    /**
     * Whether a course that was missing at a past term has been passed since,
     * using the latest grades. The history itself is not changed.
     *
     * @param  list<array<string, mixed>>|null  $pending
     * @return list<array<string, mixed>>|null
     */
    private function withCurrentStatus(?array $pending, string $key, array $standings, array $periods): ?array
    {
        if ($pending === null) {
            return null;
        }

        $keys = array_keys($standings);
        $position = array_search($key, $keys, true);
        $slotsAt = fn (string $periodKey): array => array_column($standings[$periodKey]['pending_courses'] ?? [], '_slot');
        $last = $keys[array_key_last($keys)];
        $stillPending = $slotsAt($last);
        $uncertainAt = fn (string $periodKey): array => array_column($standings[$periodKey]['unassessed'], '_slot');

        return array_map(function (array $course) use ($keys, $position, $slotsAt, $stillPending, $uncertainAt, $last, $periods): array {
            $slot = $course['_slot'];
            unset($course['_slot']);
            if (in_array($slot, $uncertainAt($last), true)) {
                return [...$course, 'resolved' => null, 'resolved_term_label' => null];
            }
            $resolved = $position < count($keys) - 1 && ! in_array($slot, $stillPending, true);
            $label = null;
            if ($resolved) {
                foreach (array_slice($keys, $position + 1) as $later) {
                    if (! in_array($slot, $slotsAt($later), true) && ! in_array($slot, $uncertainAt($later), true)) {
                        $label = ($periods[$later]['year'] + 543).' '.$periods[$later]['name'];
                        break;
                    }
                }
            }

            return [...$course, 'resolved' => $resolved, 'resolved_term_label' => $label];
        }, $pending);
    }

    /** Failing grades of the courses that belong to a slot, up to the term, or null if none was taken. */
    private function failedGrades(array $attempts, array $slot, int $year, int $semester): ?string
    {
        $letters = [];
        foreach ($attempts as $attempt) {
            $period = $this->periodOf($attempt);
            if ($period === null || $period > [$year, $semester] || $this->isPassed($attempt) || AdvisorPlanUnits::normalize($attempt['enrollment_type'] ?? 'credit') === 'audit'
                || ($attempt['_is_in_plan'] ?? true) === false
                || ! $this->slotMatches($slot, (string) ($attempt['course_code'] ?? ''))) {
                continue;
            }
            $letter = trim((string) ($attempt['grade_letter'] ?? ''));
            if ($letter !== '') {
                $letters[] = $letter;
            }
        }

        return $letters === [] ? null : implode(', ', $letters);
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
            if (preg_match('/^[0-9]*[xX]+$/D', $candidate) && preg_match('/^'.str_ireplace('x', '[0-9]', $candidate).'$/D', $courseCode)) {
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
                || AdvisorPlanUnits::normalize($attempt['enrollment_type'] ?? 'credit') !== 'credit'
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

    public static function pendingDetails(?array $pending): array
    {
        return ['pending_courses' => $pending,
            'pending_count' => $pending === null ? null : count($pending),
            'pending_credits' => $pending === null ? null : array_sum(array_map(fn (array $course): float => AdvisorPlanUnits::normalize($course['enrollment_type'] ?? null) === 'credit' ? (float) ($course['credit'] ?? 0) : 0, $pending))];
    }

    /** All semesters count toward their academic year; no semester-code allowlist. */
    private function annualGpa(array $attempts, int $entryYear): array
    {
        $years = [];
        foreach ($attempts as $attempt) {
            if (! is_numeric($attempt['academic_year'] ?? null)
                || AdvisorPlanUnits::normalize($attempt['enrollment_type'] ?? 'credit') !== 'credit'
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
