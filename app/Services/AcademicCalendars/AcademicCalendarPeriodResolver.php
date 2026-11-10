<?php

namespace App\Services\AcademicCalendars;

use App\Services\AcademicCalendarJsonStore;
use App\Services\StudentAcademicPeriods\AcademicStandingCalculator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

class AcademicCalendarPeriodResolver
{
    public function __construct(
        private readonly AcademicCalendarJsonStore $calendarStore,
        private readonly AcademicStandingCalculator $standingCalculator,
    ) {}

    public function resolve(
        CarbonImmutable $referenceDate,
        string $calendarKey,
    ): array {
        if (! in_array($calendarKey, ['thai', 'international'], true)) {
            throw new InvalidArgumentException("Unsupported academic calendar key: {$calendarKey}");
        }

        $datedTerms = [];

        foreach ($this->calendarStore->all() as $calendar) {
            $terms = collect($calendar['payload']['terms'] ?? [])
                ->filter(fn (mixed $term): bool => is_array($term)
                    && is_string($term['curriculum'] ?? null));

            if ($terms->isEmpty()) {
                continue;
            }

            $curricula = $terms
                ->groupBy('curriculum')
                ->map(function ($curriculumTerms, string $curriculum) use ($referenceDate): array {
                    $activeTerm = collect($curriculumTerms)->first(
                        fn (array $term): bool => $this->dateIsWithinTerm($referenceDate, $term)
                    );
                    $firstTerm = collect($curriculumTerms)->first();

                    return [
                        'curriculum' => $curriculum,
                        'curriculum_name_th' => $firstTerm['curriculumNameTh'] ?? $curriculum,
                        'term_code' => $activeTerm['code'] ?? null,
                        'term_name_th' => $activeTerm['nameTh'] ?? null,
                        'start_date' => $activeTerm['startDate'] ?? null,
                        'end_date' => $activeTerm['endDate'] ?? null,
                        'status' => $activeTerm === null ? 'missing' : 'ready',
                    ];
                })
                ->values();

            foreach ($terms->where('curriculum', $calendarKey) as $term) {
                $dates = $this->termDates($term);

                if ($dates === null) {
                    continue;
                }

                $datedTerms[] = [
                    'academic_year' => (int) $calendar['academic_year'],
                    'term' => $term,
                    'start_date' => $dates['start'],
                    'end_date' => $dates['end'],
                    'curricula' => $curricula->all(),
                ];
            }
        }

        usort(
            $datedTerms,
            fn (array $left, array $right): int => $left['start_date'] <=> $right['start_date'],
        );

        $selectedTerm = collect($datedTerms)->first(
            fn (array $term): bool => $referenceDate->betweenIncluded(
                $term['start_date'],
                $term['end_date'],
            ),
        );
        $message = 'พบปฏิทินการศึกษาที่พร้อมใช้สำหรับ Sync';

        if ($selectedTerm === null) {
            $previousTerm = collect($datedTerms)
                ->filter(fn (array $term): bool => $term['end_date']->isBefore($referenceDate))
                ->last();
            $nextTerm = collect($datedTerms)->first(
                fn (array $term): bool => $term['start_date']->isAfter($referenceDate),
            );

            if (
                $previousTerm === null
                || $nextTerm === null
                || ! $this->termsAreConsecutive($previousTerm, $nextTerm)
            ) {
                return $this->missingCalendarResult();
            }

            $selectedTerm = $previousTerm;
            $message = 'พบช่วงปิดภาคการศึกษา ใช้ภาคการศึกษาก่อนหน้าสำหรับ Sync';
        }

        $term = $selectedTerm['term'];
        $semester = $this->standingCalculator->semesterFromTermCode($term['code'] ?? null);

        if ($semester === null) {
            return $this->result(
                'calendar_conflict',
                'รหัสภาคการศึกษาในปฏิทินไม่รองรับการ Sync',
                $selectedTerm['academic_year'],
                null,
                $selectedTerm['curricula'],
            );
        }

        $curricula = collect($selectedTerm['curricula'])
            ->map(function (array $curriculum) use ($calendarKey, $term): array {
                if ($curriculum['curriculum'] !== $calendarKey) {
                    return $curriculum;
                }

                return [
                    ...$curriculum,
                    'term_code' => $term['code'],
                    'term_name_th' => $term['nameTh'] ?? null,
                    'start_date' => $term['startDate'],
                    'end_date' => $term['endDate'],
                    'status' => 'ready',
                ];
            })
            ->values()
            ->all();

        return $this->result(
            'ready',
            $message,
            $selectedTerm['academic_year'],
            [
                'code' => $term['code'],
                'name_th' => $term['nameTh'] ?? null,
                'semester' => $semester,
            ],
            $curricula,
        );
    }

    private function missingCalendarResult(): array
    {
        return $this->result(
            'missing_calendar',
            'ไม่พบปฏิทินการศึกษาที่ครอบคลุมวันที่ปัจจุบัน กรุณาไป Sync ปฏิทินการศึกษาก่อน',
            null,
            null,
            [],
        );
    }

    private function termsAreConsecutive(array $previousTerm, array $nextTerm): bool
    {
        $previousCode = $previousTerm['term']['code'] ?? null;
        $nextCode = $nextTerm['term']['code'] ?? null;
        $previousYear = $previousTerm['academic_year'];
        $nextYear = $nextTerm['academic_year'];

        return match ($previousCode) {
            'first' => $nextCode === 'second' && $nextYear === $previousYear,
            'second' => ($nextCode === 'summer' && $nextYear === $previousYear)
                || ($nextCode === 'first' && $nextYear === $previousYear + 1),
            'summer' => $nextCode === 'first' && $nextYear === $previousYear + 1,
            default => false,
        };
    }

    private function result(
        string $status,
        string $message,
        ?int $academicYear,
        ?array $term,
        array $curricula,
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'academic_year' => $academicYear,
            'term_code' => $term['code'] ?? null,
            'term_name_th' => $term['name_th'] ?? null,
            'study_semester' => $term['semester'] ?? null,
            'curricula' => $curricula,
        ];
    }

    private function dateIsWithinTerm(CarbonImmutable $referenceDate, array $term): bool
    {
        $dates = $this->termDates($term);

        return $dates !== null
            && $referenceDate->betweenIncluded($dates['start'], $dates['end']);
    }

    private function termDates(array $term): ?array
    {
        $startDate = $term['startDate'] ?? null;
        $endDate = $term['endDate'] ?? null;

        if (! is_string($startDate) || ! is_string($endDate)) {
            return null;
        }

        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate);
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $endDate);

            return $start->isAfter($end) ? null : compact('start', 'end');
        } catch (Throwable) {
            return null;
        }
    }
}
