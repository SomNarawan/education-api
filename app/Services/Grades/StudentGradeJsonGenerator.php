<?php

namespace App\Services\Grades;

use App\Models\Student;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StudentGradeJsonGenerator
{
    private const FAIL_GRADES = ['F', 'W', 'NP', 'U'];

    private const PASS_GRADES = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'P', 'S'];

    private const ACADEMIC_GRADES = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F'];

    public function generate(
        string $studentCode,
        array $newAttempts,
        array $planResponse,
        int $studyPlanId,
        ?int $importId = null,
    ): void {
        [$planRows, $totalCredits] = $this->plan($planResponse, $studyPlanId);
        $newAttempts = $this->withPlanCredits($newAttempts, $planRows);
        $attempts = $this->mergeAttempts($studentCode, $newAttempts);
        $attempts = $this->withPlanCredits($attempts, $planRows);
        $this->writeGeneratedData(
            $studentCode,
            $attempts,
            $planRows,
            $totalCredits,
        );
        $this->writeStudyPlanSyncLog(
            $studentCode,
            $importId,
            $planResponse,
        );
    }

    public function resetAll(string $studentCode): ?int
    {
        $attempts = $this->storedAttempts($studentCode);

        if ($attempts === null) {
            return null;
        }

        $resetCount = count($attempts);
        $this->deleteGeneratedData($studentCode);

        return $resetCount;
    }

    public function resetSemester(
        string $studentCode,
        int $studyYear,
        int $semester,
        array $planResponse,
        int $studyPlanId,
    ): ?int {
        $attempts = $this->storedAttempts($studentCode);

        if ($attempts === null) {
            return null;
        }

        if ($attempts === []) {
            return 0;
        }

        [$planRows, $totalCredits] = $this->plan($planResponse, $studyPlanId);
        $attempts = $this->withPlanCredits($attempts, $planRows);
        $standing = $this->studentStanding($studentCode);
        $entryYear = $this->entryYear($studentCode, $standing, $attempts, $planRows);
        $attempts = array_map(
            fn (array $attempt) => [
                ...$attempt,
                'study_year' => max(1, ((int) $attempt['academic_year']) - $entryYear + 1),
            ],
            $attempts,
        );
        $remainingAttempts = array_values(array_filter(
            $attempts,
            fn (array $attempt): bool => (int) $attempt['study_year'] !== $studyYear
                || (int) ($attempt['semester_order'] ?? 0) !== $semester,
        ));
        $resetCount = count($attempts) - count($remainingAttempts);

        if ($resetCount === 0) {
            return 0;
        }

        if ($remainingAttempts === []) {
            $this->deleteGeneratedData($studentCode);

            return $resetCount;
        }

        $this->writeGeneratedData(
            $studentCode,
            $remainingAttempts,
            $planRows,
            $totalCredits,
        );

        return $resetCount;
    }

    private function writeGeneratedData(
        string $studentCode,
        array $attempts,
        array $planRows,
        float $totalCredits,
    ): void {
        $standing = $this->studentStanding($studentCode);
        $entryYear = $this->entryYear($studentCode, $standing, $attempts, $planRows);
        $attempts = array_map(
            fn (array $attempt) => [
                ...$attempt,
                'study_year' => max(1, ((int) $attempt['academic_year']) - $entryYear + 1),
            ],
            $attempts,
        );
        $this->writeJson("data/grade_attempts/{$studentCode}.json", $attempts);
        $aggregates = $this->aggregateCourses($attempts);
        $slots = $this->slots($planRows);
        [$slots, $assigned, $over] = $this->allocate($slots, $aggregates);
        $latestImportedPeriod = $this->latestPeriod($attempts);
        $enrollments = $this->enrollments($slots, $assigned, $over);

        $notPass = $this->notPass($slots, $assigned, $latestImportedPeriod);
        $passedAfterFailure = $this->passedAfterFailure($aggregates, $assigned);
        $overRecords = array_map(
            fn (array $item) => $this->record($item['aggregate'], $item['possible_slot']),
            $over,
        );

        usort($enrollments, $this->recordSorter(...));
        usort($notPass, $this->recordSorter(...));
        usort($passedAfterFailure, $this->recordSorter(...));
        usort($overRecords, $this->recordSorter(...));

        $this->writeJson("data/enrollments/{$studentCode}.json", $enrollments);
        $this->writeJson("data/enrollments_not_pass/{$studentCode}.json", $notPass);
        $this->writeJson("data/enrollments_pass/{$studentCode}.json", $passedAfterFailure);
        $this->writeJson("data/enrollments_over/{$studentCode}.json", $overRecords);
        $this->writeGraphs(
            $studentCode,
            $attempts,
            $slots,
            $assigned,
            $over,
            $totalCredits,
        );
    }

    private function storedAttempts(string $studentCode): ?array
    {
        $path = "data/grade_attempts/{$studentCode}.json";
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_array'))
            : [];
    }

    private function deleteGeneratedData(string $studentCode): void
    {
        $disk = Storage::disk('local');
        $disk->delete([
            "data/grade_attempts/{$studentCode}.json",
            "data/enrollments/{$studentCode}.json",
            "data/enrollments_not_pass/{$studentCode}.json",
            "data/enrollments_pass/{$studentCode}.json",
            "data/enrollments_over/{$studentCode}.json",
            "data/graph/by_credit/{$studentCode}.json",
            "data/graph/by_semester/{$studentCode}.json",
        ]);

        foreach ($disk->files('data/graph/by_group') as $path) {
            if (preg_match('/\/'.preg_quote($studentCode, '/').'_\d+\.json$/', $path) === 1) {
                $disk->delete($path);
            }
        }
    }

    private function writeStudyPlanSyncLog(
        string $studentCode,
        ?int $importId,
        array $planResponse,
    ): void {
        if ($importId === null) {
            return;
        }

        $this->writeJson(
            "data/study_plan_sync_logs/{$studentCode}_{$importId}.json",
            $planResponse,
        );
    }

    private function plan(array $response, int $studyPlanId): array
    {
        $plans = array_is_list($response) ? $response : [$response];
        $selected = null;

        foreach ($plans as $plan) {
            if ((int) ($plan['curriculum_plan_id'] ?? 0) === $studyPlanId) {
                $selected = $plan;
                break;
            }
        }

        $selected ??= $plans[0] ?? null;

        if (! is_array($selected) || ! isset($selected['data']) || ! is_array($selected['data'])) {
            throw new RuntimeException('ไม่พบข้อมูลรายวิชาในแผนการเรียนที่เลือก');
        }

        return [
            array_values(array_filter($selected['data'], 'is_array')),
            (float) ($selected['total_credits_min'] ?? 0),
        ];
    }

    private function withPlanCredits(array $attempts, array $planRows): array
    {
        return array_map(function (array $attempt) use ($planRows): array {
            $courseCode = $this->normalizeCode($attempt['course_code'] ?? '');
            $planCourse = $this->planCourse($planRows, $courseCode);
            $attempt['_is_in_plan'] = $planCourse !== null;

            if (! isset($attempt['credit']) || ! is_numeric($attempt['credit'])) {
                $attempt['credit'] = is_numeric($planCourse['credit'] ?? null)
                    ? (float) $planCourse['credit']
                    : null;
            }

            return $attempt;
        }, $attempts);
    }

    private function planCourse(array $planRows, string $courseCode): ?array
    {
        foreach ($planRows as $row) {
            if ($this->normalizeCode($row['course_code'] ?? '') === $courseCode) {
                return $row;
            }

            foreach (($row['courses'] ?? []) as $course) {
                if (is_array($course)
                    && $this->normalizeCode($course['course_code'] ?? '') === $courseCode) {
                    return $course;
                }
            }
        }

        return null;
    }

    private function mergeAttempts(string $studentCode, array $newAttempts): array
    {
        $path = "data/grade_attempts/{$studentCode}.json";
        $existing = [];

        if (Storage::disk('local')->exists($path)) {
            $decoded = json_decode(Storage::disk('local')->get($path), true);
            $existing = is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
        }

        $merged = [];

        foreach ([...$existing, ...$newAttempts] as $attempt) {
            $key = $this->attemptKey($attempt, $studentCode);
            $merged[$key] = $attempt;
        }

        $attempts = array_values($merged);
        usort($attempts, fn (array $left, array $right) => $this->attemptOrder($left) <=> $this->attemptOrder($right));

        return $attempts;
    }

    private function attemptKey(array $attempt, string $studentCode): string
    {
        return implode('|', [
            $attempt['student_code'] ?? $studentCode,
            $this->normalizeCode($attempt['course_code'] ?? ''),
            $attempt['academic_year'] ?? 0,
            $attempt['semester_order'] ?? 0,
        ]);
    }

    private function studentStanding(string $studentCode): ?array
    {
        if (! Schema::hasTable('students')) {
            return null;
        }

        return Student::query()
            ->where('student_code', $studentCode)
            ->first(['entry_year', 'study_year', 'study_semester'])
            ?->only(['entry_year', 'study_year', 'study_semester']);
    }

    private function entryYear(
        string $studentCode,
        ?array $standing,
        array $attempts,
        array $planRows,
    ): int {
        $planEntryYear = $this->planEntryYear($planRows);

        if ($planEntryYear !== null) {
            return $planEntryYear;
        }

        $entryYear = is_numeric($standing['entry_year'] ?? null)
            ? (int) $standing['entry_year']
            : 0;
        $normalizedEntryYear = $this->normalizeAcademicYear($entryYear);

        if ($normalizedEntryYear !== null) {
            return $normalizedEntryYear;
        }

        $studentEntryYear = preg_match('/^(\d{2})/', $studentCode, $matches) === 1
            ? $this->normalizeAcademicYear((int) $matches[1])
            : null;

        if ($studentEntryYear !== null) {
            return $studentEntryYear;
        }

        return min(array_column($attempts, 'academic_year'));
    }

    private function planEntryYear(array $planRows): ?int
    {
        $entryYears = [];

        foreach ($planRows as $row) {
            if (! is_numeric($row['plan_year_be'] ?? null)) {
                continue;
            }

            $planYear = $this->normalizeAcademicYear((int) $row['plan_year_be']);

            if ($planYear === null) {
                continue;
            }

            $studyYear = is_numeric($row['plan_study_year'] ?? null)
                ? max(1, (int) $row['plan_study_year'])
                : 1;
            $entryYears[] = $planYear - $studyYear + 1;
        }

        return $entryYears === [] ? null : min($entryYears);
    }

    private function normalizeAcademicYear(int $year): ?int
    {
        if ($year >= 2400) {
            return $year - 543;
        }

        if ($year >= 1900) {
            return $year;
        }

        if ($year >= 1 && $year <= 99) {
            return $year + 1957;
        }

        return null;
    }

    private function aggregateCourses(array $attempts): array
    {
        $groups = [];

        foreach ($attempts as $attempt) {
            $groups['course:'.$attempt['course_code']][] = $attempt;
        }

        $aggregates = [];

        foreach ($groups as $courseAttempts) {
            usort($courseAttempts, fn (array $left, array $right) => $this->attemptOrder($left) <=> $this->attemptOrder($right));
            $latest = $courseAttempts[array_key_last($courseAttempts)];
            $courseCode = (string) $latest['course_code'];
            $aggregates['course:'.$courseCode] = [
                'course_code' => $courseCode,
                'credit' => is_numeric($latest['credit'] ?? null)
                    ? (float) $latest['credit']
                    : null,
                'enrollment_type' => $latest['enrollment_type'],
                'is_in_plan' => (bool) ($latest['_is_in_plan'] ?? true),
                'attempts' => $courseAttempts,
                'latest' => $latest,
                'is_passed' => count(array_filter(
                    $courseAttempts,
                    fn (array $attempt) => in_array($attempt['grade_letter'], self::PASS_GRADES, true),
                )) > 0,
            ];
        }

        return $aggregates;
    }

    private function slots(array $planRows): array
    {
        return array_map(
            fn (array $row, int $index) => [
                ...$row,
                '_index' => $index,
                '_required_credit' => (float) ($row['credit'] ?? 0),
                '_completed_credit' => 0.0,
                '_allocated_credit' => 0.0,
                '_assigned_codes' => [],
            ],
            $planRows,
            array_keys($planRows),
        );
    }

    private function allocate(array $slots, array $aggregates): array
    {
        $assigned = [];

        foreach ($aggregates as $aggregate) {
            $courseCode = $aggregate['course_code'];
            if (! $aggregate['is_in_plan']) {
                continue;
            }

            foreach ($slots as $slotIndex => $slot) {
                if ($this->isFixedSlot($slot) && $this->normalizeCode($slot['course_code'] ?? '') === $courseCode) {
                    $this->assign($slots, $slotIndex, $aggregate, $assigned);
                    break;
                }
            }
        }

        $remaining = array_filter(
            $aggregates,
            fn (array $aggregate) => $aggregate['is_in_plan']
                && ! isset($assigned[$aggregate['course_code']]),
        );
        uasort($remaining, function (array $left, array $right): int {
            if ($left['is_passed'] !== $right['is_passed']) {
                return $left['is_passed'] ? -1 : 1;
            }

            return $this->attemptOrder($left['latest']) <=> $this->attemptOrder($right['latest']);
        });

        foreach ($remaining as $aggregate) {
            $courseCode = $aggregate['course_code'];
            foreach ($slots as $slotIndex => $slot) {
                $remainingCredit = $this->slotAllocationRemaining($slot);

                if (! $this->isFlexibleSlot($slot)
                    || ! $this->matchesSlot($courseCode, $slot)
                    || $remainingCredit <= 0
                    || (float) $aggregate['credit'] > $remainingCredit) {
                    continue;
                }

                $this->assign($slots, $slotIndex, $aggregate, $assigned);
                break;
            }
        }

        foreach ($aggregates as $aggregate) {
            $courseCode = $aggregate['course_code'];
            if (isset($assigned[$courseCode])
                || ! $aggregate['is_in_plan']
                || ! $this->completesCredit($aggregate)) {
                continue;
            }

            foreach ($slots as $slotIndex => $slot) {
                if ($this->normalizeCode($slot['course_code'] ?? '') !== 'FREE'
                    || $this->slotAllocationRemaining($slot) <= 0
                    || (float) $aggregate['credit'] > $this->slotAllocationRemaining($slot)) {
                    continue;
                }

                $this->assign($slots, $slotIndex, $aggregate, $assigned);
                break;
            }
        }

        $over = [];

        foreach ($aggregates as $aggregate) {
            $courseCode = $aggregate['course_code'];
            if (isset($assigned[$courseCode])) {
                continue;
            }

            $over[] = [
                'aggregate' => $aggregate,
                'possible_slot' => $aggregate['is_in_plan']
                    ? $this->possibleSlot($courseCode, $slots)
                    : null,
            ];
        }

        return [$slots, $assigned, $over];
    }

    private function assign(array &$slots, int $slotIndex, array $aggregate, array &$assigned): void
    {
        $courseCode = $aggregate['course_code'];
        $slots[$slotIndex]['_assigned_codes'][] = $courseCode;
        $slots[$slotIndex]['_allocated_credit'] += min(
            (float) $aggregate['credit'],
            $this->slotAllocationRemaining($slots[$slotIndex]),
        );

        if ($this->completesCredit($aggregate)) {
            $slots[$slotIndex]['_completed_credit'] += min(
                (float) $aggregate['credit'],
                $this->slotRemaining($slots[$slotIndex]),
            );
        }

        $assigned[$courseCode] = [
            'aggregate' => $aggregate,
            'slot' => $slots[$slotIndex],
            'slot_index' => $slotIndex,
        ];
    }

    private function isFixedSlot(array $slot): bool
    {
        $code = $this->normalizeCode($slot['course_code'] ?? '');

        return preg_match('/^\d+$/', $code) === 1 && ! is_array($slot['courses'] ?? null);
    }

    private function isFlexibleSlot(array $slot): bool
    {
        $code = $this->normalizeCode($slot['course_code'] ?? '');

        return str_contains($code, 'X') || (is_array($slot['courses'] ?? null) && $slot['courses'] !== []);
    }

    private function matchesSlot(string $courseCode, array $slot): bool
    {
        $plannedCode = $this->normalizeCode($slot['course_code'] ?? '');

        if (str_contains($plannedCode, 'X')) {
            $pattern = '/^'.str_replace('X', '\\d', preg_quote($plannedCode, '/')).'$/';

            if (preg_match($pattern, $courseCode) === 1) {
                return true;
            }
        }

        foreach (($slot['courses'] ?? []) as $course) {
            if (is_array($course) && $this->normalizeCode($course['course_code'] ?? '') === $courseCode) {
                return true;
            }
        }

        return false;
    }

    private function possibleSlot(string $courseCode, array $slots): ?array
    {
        foreach ($slots as $slot) {
            if (($this->isFixedSlot($slot) && $this->normalizeCode($slot['course_code'] ?? '') === $courseCode)
                || ($this->isFlexibleSlot($slot) && $this->matchesSlot($courseCode, $slot))) {
                return $slot;
            }
        }

        return null;
    }

    private function slotRemaining(array $slot): float
    {
        return max(0, ((float) $slot['_required_credit']) - ((float) $slot['_completed_credit']));
    }

    private function slotAllocationRemaining(array $slot): float
    {
        return max(0, ((float) $slot['_required_credit']) - ((float) $slot['_allocated_credit']));
    }

    private function completesCredit(array $aggregate): bool
    {
        return $aggregate['is_passed'] && $aggregate['enrollment_type'] === 'credit';
    }

    private function record(array $aggregate, ?array $slot): array
    {
        $latest = $aggregate['latest'];
        $isUnplanned = ! ($aggregate['is_in_plan'] ?? true);
        $gradeLetters = array_column($aggregate['attempts'], 'grade_letter');
        $gradePoints = array_map(
            fn (array $attempt) => $attempt['grade_point'] === null
                ? 'null'
                : $this->number($attempt['grade_point']),
            $aggregate['attempts'],
        );

        return [
            'study_year' => (int) $latest['study_year'],
            'semester' => $latest['semester'],
            'semester_year' => (int) $latest['academic_year'],
            'semester_year_be' => (int) $latest['academic_year'] + 543,
            'semester_order' => (int) $latest['semester_order'],
            'study_period' => 'ปีที่ '.((int) $latest['study_year']).' '.$latest['semester'],
            'course_code' => $this->mappedCourseCode($aggregate['course_code']),
            'course_name' => $isUnplanned ? null : $this->courseName($aggregate['course_code'], $slot),
            'course_category' => $isUnplanned ? null : ($slot['course_category'] ?? 'นอกหลักสูตร'),
            'course_sub_category' => $isUnplanned ? null : ($slot['course_sub_category'] ?? null),
            'course_group' => $isUnplanned ? null : $this->courseGroup($slot),
            'course_requirement' => $isUnplanned ? null : $this->courseRequirement($slot),
            'grade_letter' => implode(', ', $gradeLetters),
            'grade_point' => count($gradePoints) === 1 ? $gradePoints[0] : implode(', ', $gradePoints),
            'enrollment_type' => $aggregate['enrollment_type'],
            'credit' => is_numeric($aggregate['credit'] ?? null)
                ? $this->number($aggregate['credit'])
                : null,
        ];
    }

    private function plannedRecord(array $slot, float $remainingCredit): array
    {
        return [
            'study_year' => (int) ($slot['plan_study_year'] ?? 0),
            'semester' => $slot['plan_semester'] ?? '-',
            'semester_year' => isset($slot['plan_year']) ? (int) $slot['plan_year'] : null,
            'semester_year_be' => isset($slot['plan_year_be']) ? (int) $slot['plan_year_be'] : null,
            'semester_order' => (int) ($slot['plan_semester_order'] ?? 0),
            'study_period' => $slot['plan_study_period'] ?? null,
            'course_code' => $this->mappedCourseCode($slot['course_code'] ?? null),
            'course_name' => $slot['course_name'] ?? '-',
            'course_category' => $slot['course_category'] ?? '-',
            'course_sub_category' => $slot['course_sub_category'] ?? null,
            'course_group' => $this->courseGroup($slot),
            'course_requirement' => $this->courseRequirement($slot),
            'grade_letter' => null,
            'grade_point' => null,
            'enrollment_type' => $slot['enrollment_type'] ?? 'credit',
            'credit' => $this->number($remainingCredit),
        ];
    }

    private function notPass(array $slots, array $assigned, array $latestPeriod): array
    {
        $rows = [];

        foreach ($slots as $slot) {
            if (! $this->isDue($slot, $latestPeriod) || $this->slotRemaining($slot) <= 0) {
                continue;
            }

            $failedRows = [];

            foreach ($slot['_assigned_codes'] as $courseCode) {
                $aggregate = $assigned[$courseCode]['aggregate'] ?? null;

                if ($aggregate !== null && ! $this->completesCredit($aggregate)) {
                    $failedRows[] = $this->record($aggregate, $slot);
                }
            }

            if ($failedRows !== []) {
                array_push($rows, ...$failedRows);
            } else {
                $rows[] = $this->plannedRecord($slot, $this->slotRemaining($slot));
            }
        }

        return $rows;
    }

    private function passedAfterFailure(array $aggregates, array $assigned): array
    {
        $rows = [];

        foreach ($aggregates as $aggregate) {
            $courseCode = $aggregate['course_code'];
            $seenFailure = false;
            $recovered = false;

            foreach ($aggregate['attempts'] as $attempt) {
                if (in_array($attempt['grade_letter'], self::FAIL_GRADES, true)) {
                    $seenFailure = true;
                } elseif ($seenFailure && in_array($attempt['grade_letter'], self::PASS_GRADES, true)) {
                    $recovered = true;
                }
            }

            if ($recovered) {
                $rows[] = $this->record($aggregate, $assigned[$courseCode]['slot'] ?? null);
            }
        }

        return $rows;
    }

    private function enrollments(
        array $slots,
        array $assigned,
        array $over,
    ): array {
        $rows = [];

        foreach ($slots as $slot) {
            foreach ($slot['_assigned_codes'] as $courseCode) {
                $aggregate = $assigned[$courseCode]['aggregate'] ?? null;

                if ($aggregate !== null) {
                    $rows[] = $this->record($aggregate, $slot);
                }
            }

            $remainingCredit = $this->slotAllocationRemaining($slot);

            if ($remainingCredit > 0) {
                $rows[] = $this->plannedRecord($slot, $remainingCredit);
            }
        }

        foreach ($over as $item) {
            $rows[] = $this->record($item['aggregate'], $item['possible_slot']);
        }

        return $rows;
    }

    private function latestPeriod(array $attempts): array
    {
        $latest = $attempts[array_key_last($attempts)];

        return [(int) $latest['study_year'], (int) $latest['semester_order']];
    }

    private function isDue(array $slot, array $latestPeriod): bool
    {
        $slotPeriod = [
            (int) ($slot['plan_study_year'] ?? PHP_INT_MAX),
            (int) ($slot['plan_semester_order'] ?? PHP_INT_MAX),
        ];

        return $slotPeriod[0] < $latestPeriod[0]
            || ($slotPeriod[0] === $latestPeriod[0] && $slotPeriod[1] <= $latestPeriod[1]);
    }

    private function courseName(string $courseCode, ?array $slot): string
    {
        if ($slot === null) {
            return $courseCode;
        }

        foreach (($slot['courses'] ?? []) as $course) {
            if (is_array($course) && $this->normalizeCode($course['course_code'] ?? '') === $courseCode) {
                return (string) ($course['course_name'] ?? $courseCode);
            }
        }

        return (string) ($slot['course_name'] ?? $courseCode);
    }

    private function courseGroup(?array $slot): ?string
    {
        if ($slot === null) {
            return 'นอกหลักสูตร';
        }

        return $slot['course_group']
            ?? $slot['course_sub_category']
            ?? $slot['course_category']
            ?? null;
    }

    private function courseRequirement(?array $slot): ?string
    {
        if ($slot === null) {
            return null;
        }

        return $this->isFixedSlot($slot)
            ? 'รายวิชาบังคับ'
            : 'เลือกเรียนให้ครบตามหน่วยกิตที่กำหนด';
    }

    private function recordSorter(array $left, array $right): int
    {
        return [
            $left['study_year'] ?? 0,
            $left['semester_order'] ?? 0,
            $left['course_code'] ?? '',
        ] <=> [
            $right['study_year'] ?? 0,
            $right['semester_order'] ?? 0,
            $right['course_code'] ?? '',
        ];
    }

    private function writeGraphs(
        string $studentCode,
        array $attempts,
        array $slots,
        array $assigned,
        array $over,
        float $totalCredits,
    ): void {
        $creditsStudy = array_sum(array_column($slots, '_completed_credit'));
        $creditsOver = array_sum(array_map(
            fn (array $item) => $this->completesCredit($item['aggregate'])
                ? (float) $item['aggregate']['credit']
                : 0,
            $over,
        ));
        $assignedAggregates = array_column($assigned, 'aggregate');

        $this->writeJson("data/graph/by_credit/{$studentCode}.json", [
            [
                'type' => 'credit_study',
                'credits_study' => $this->number($creditsStudy),
                'credits_all' => $this->number($totalCredits),
                'gpa' => $this->gpa($assignedAggregates),
            ],
            [
                'type' => 'credit_over',
                'credits_study' => $this->number($creditsOver),
                'credits_all' => $this->number($totalCredits),
                'gpa' => $this->gpa(array_column($over, 'aggregate')),
            ],
        ]);

        $this->writeJson(
            "data/graph/by_semester/{$studentCode}.json",
            $this->semesterGraph($attempts, $assigned, $over),
        );

        $groupFiles = [];

        foreach ($this->mainCategories($slots, $assigned, $over) as $index => $category) {
            $path = 'data/graph/by_group/'.$studentCode.'_'.($index + 1).'.json';
            $groupFiles[] = $path;
            $this->writeJson(
                $path,
                $this->groupGraph($category, $slots, $assigned, $over),
            );
        }

        foreach (Storage::disk('local')->files('data/graph/by_group') as $path) {
            if (preg_match('/\/'.preg_quote($studentCode, '/').'_\d+\.json$/', $path) === 1
                && ! in_array($path, $groupFiles, true)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function semesterGraph(array $attempts, array $assigned, array $over): array
    {
        $groups = [];
        $slotByCourse = [];

        foreach ($assigned as $courseCode => $item) {
            $slotByCourse[$courseCode] = $item['slot'];
        }

        foreach ($over as $item) {
            $slotByCourse[$item['aggregate']['course_code']] = $item['possible_slot'];
        }

        foreach ($attempts as $attempt) {
            $key = ((int) $attempt['academic_year']).'-'.((int) $attempt['semester_order']);
            $groups[$key][] = $attempt;
        }

        uksort($groups, function (string $left, string $right): int {
            [$leftYear, $leftSemester] = array_map('intval', explode('-', $left));
            [$rightYear, $rightSemester] = array_map('intval', explode('-', $right));

            return [$leftYear, $leftSemester] <=> [$rightYear, $rightSemester];
        });

        $rows = [];
        $cumulative = [];
        $previousGpax = null;

        foreach ($groups as $termAttempts) {
            array_push($cumulative, ...$termAttempts);
            $first = $termAttempts[0];
            $gpax = $this->gpaFromAttempts($cumulative);
            $diff = $previousGpax === null ? 0 : round($gpax - $previousGpax, 2);
            $credits = array_sum(array_map(
                fn (array $attempt) => $attempt['enrollment_type'] === 'credit'
                    && in_array($attempt['grade_letter'], self::PASS_GRADES, true)
                        ? (float) $attempt['credit']
                        : 0,
                $termAttempts,
            ));
            $enrollments = array_map(function (array $attempt) use ($slotByCourse): array {
                $slot = $slotByCourse[$attempt['course_code']] ?? null;
                $isUnplanned = ! ($attempt['_is_in_plan'] ?? true);

                return [
                    'course_name' => $isUnplanned ? null : $this->courseName($attempt['course_code'], $slot),
                    'grade_letter' => $attempt['grade_letter'],
                    'credit' => is_numeric($attempt['credit'] ?? null)
                        ? $this->number($attempt['credit'])
                        : null,
                ];
            }, $termAttempts);

            $rows[] = [
                'study_year' => (int) $first['study_year'],
                'semester' => $first['semester'],
                'semester_year' => (int) $first['academic_year'],
                'semester_year_be' => (int) $first['academic_year'] + 543,
                'credits' => $this->number($credits),
                'gpa' => $this->gpaFromAttempts($termAttempts),
                'gpax' => $gpax,
                'diff_gpax' => $diff > 0 ? '+'.number_format($diff, 2, '.', '') : $this->number($diff),
                'enrollments' => $enrollments,
            ];
            $previousGpax = $gpax;
        }

        return $rows;
    }

    private function mainCategories(array $slots, array $assigned, array $over): array
    {
        $categories = [];
        $gradedSlotIndexes = $this->gradedSlotIndexes($assigned, $over);

        foreach ($slots as $slot) {
            if (! isset($gradedSlotIndexes[$slot['_index']])) {
                continue;
            }

            $category = trim((string) ($slot['course_category'] ?? ''));

            if ($category !== '' && ! in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    private function groupGraph(string $category, array $slots, array $assigned, array $over): array
    {
        $categorySlots = array_values(array_filter(
            $slots,
            fn (array $slot) => ($slot['course_category'] ?? null) === $category,
        ));
        $gradedSlotIndexes = $this->gradedSlotIndexes($assigned, $over);

        $nodes = [[$category, $categorySlots]];
        $seen = [$category => true];

        foreach (['course_sub_category', 'course_group'] as $field) {
            foreach ($categorySlots as $slot) {
                $label = trim((string) ($slot[$field] ?? ''));

                if ($label === ''
                    || isset($seen[$label])
                    || ! isset($gradedSlotIndexes[$slot['_index']])) {
                    continue;
                }

                $seen[$label] = true;
                $nodes[] = [$label, array_values(array_filter(
                    $categorySlots,
                    fn (array $candidate) => ($candidate[$field] ?? null) === $label,
                ))];
            }
        }

        return array_map(
            fn (array $node) => $this->groupSummary($node[0], $node[1], $assigned, $over),
            $nodes,
        );
    }

    private function gradedSlotIndexes(array $assigned, array $over): array
    {
        $indexes = [];

        foreach ($assigned as $item) {
            if ($this->hasAcademicGrade($item['aggregate'])) {
                $indexes[$item['slot_index']] = true;
            }
        }

        foreach ($over as $item) {
            $possibleIndex = $item['possible_slot']['_index'] ?? null;

            if ($possibleIndex !== null && $this->hasAcademicGrade($item['aggregate'])) {
                $indexes[$possibleIndex] = true;
            }
        }

        return $indexes;
    }

    private function hasAcademicGrade(array $aggregate): bool
    {
        foreach ($aggregate['attempts'] as $attempt) {
            if (in_array($attempt['grade_letter'] ?? null, self::ACADEMIC_GRADES, true)) {
                return true;
            }
        }

        return false;
    }

    private function groupSummary(string $label, array $slots, array $assigned, array $over): array
    {
        $slotIndexes = array_flip(array_column($slots, '_index'));
        $required = array_sum(array_column($slots, '_required_credit'));
        $completed = array_sum(array_column($slots, '_completed_credit'));
        $aggregates = [];

        foreach ($assigned as $item) {
            if (isset($slotIndexes[$item['slot_index']])) {
                $aggregates[] = $item['aggregate'];
            }
        }

        $overCredits = array_sum(array_map(function (array $item) use ($slotIndexes): float {
            $possibleIndex = $item['possible_slot']['_index'] ?? null;

            return $possibleIndex !== null
                && isset($slotIndexes[$possibleIndex])
                && $this->completesCredit($item['aggregate'])
                    ? (float) $item['aggregate']['credit']
                    : 0;
        }, $over));

        return [
            'course_group' => $label,
            'gpa' => $this->gpa($aggregates),
            'credits' => $this->number($required),
            'completed_credits' => $this->number($completed),
            'remaining_credits' => $this->number(max(0, $required - $completed)),
            'overed_credits' => $this->number($overCredits),
        ];
    }

    private function gpa(array $aggregates): float|int
    {
        $attempts = [];

        foreach ($aggregates as $aggregate) {
            array_push($attempts, ...$aggregate['attempts']);
        }

        return $this->gpaFromAttempts($attempts);
    }

    private function gpaFromAttempts(array $attempts): float|int
    {
        $points = 0.0;
        $credits = 0.0;

        foreach ($attempts as $attempt) {
            if (($attempt['enrollment_type'] ?? 'credit') !== 'credit' || $attempt['grade_point'] === null) {
                continue;
            }

            $credit = (float) $attempt['credit'];
            $points += ((float) $attempt['grade_point']) * $credit;
            $credits += $credit;
        }

        return $credits > 0 ? round($points / $credits, 2) : 0;
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        if (! $disk->put($path, $json."\n")) {
            throw new RuntimeException("ไม่สามารถเขียนไฟล์ {$path} ได้");
        }
    }

    private function attemptOrder(array $attempt): int
    {
        return ((int) ($attempt['academic_year'] ?? 0) * 10) + (int) ($attempt['semester_order'] ?? 0);
    }

    private function normalizeCode(mixed $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $code)) ?? '');
    }

    private function mappedCourseCode(mixed $code): string
    {
        $value = preg_replace('/\s+/', '', trim((string) $code)) ?? '';

        return preg_match('/^(?:\d{8}|\d{5}x{3})$/i', $value) === 1
            ? $value
            : '-';
    }

    private function number(float|int|string|null $value): float|int
    {
        $number = round((float) $value, 2);

        return floor($number) === $number ? (int) $number : $number;
    }
}
