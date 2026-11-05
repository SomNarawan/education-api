<?php

namespace App\Services\AcademicCalendars;

use App\Services\AcademicCalendarJsonStore;
use App\Services\StudentAcademicPeriods\AcademicStandingCalculator;
use Carbon\CarbonImmutable;
use Throwable;

class AcademicCalendarPeriodResolver
{
    public function __construct(
        private readonly AcademicCalendarJsonStore $calendarStore,
        private readonly AcademicStandingCalculator $standingCalculator,
    ) {}

    public function resolve(CarbonImmutable $referenceDate): array
    {
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

            $thaiCurriculum = $curricula->firstWhere('curriculum', 'thai');

            if ($thaiCurriculum === null || $thaiCurriculum['status'] !== 'ready') {
                continue;
            }

            $academicYear = $calendar['academic_year'];
            $semester = $this->standingCalculator->semesterFromTermCode(
                $thaiCurriculum['term_code'],
            );

            if ($semester === null) {
                return $this->result(
                    'calendar_conflict',
                    'รหัสภาคการศึกษาในปฏิทินไม่รองรับการ Sync',
                    $academicYear,
                    null,
                    $curricula->all(),
                );
            }

            return $this->result(
                'ready',
                'พบปฏิทินการศึกษาที่พร้อมใช้สำหรับ Sync',
                $academicYear,
                [
                    'code' => $thaiCurriculum['term_code'],
                    'name_th' => $thaiCurriculum['term_name_th'],
                    'semester' => $semester,
                ],
                $curricula->all(),
            );
        }

        return $this->result(
            'missing_calendar',
            'ไม่พบปฏิทินการศึกษาที่ครอบคลุมวันที่ปัจจุบัน กรุณาไป Sync ปฏิทินการศึกษาก่อน',
            null,
            null,
            [],
        );
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
        $startDate = $term['startDate'] ?? null;
        $endDate = $term['endDate'] ?? null;

        if (! is_string($startDate) || ! is_string($endDate)) {
            return false;
        }

        try {
            return $referenceDate->betweenIncluded(
                CarbonImmutable::createFromFormat('!Y-m-d', $startDate),
                CarbonImmutable::createFromFormat('!Y-m-d', $endDate),
            );
        } catch (Throwable) {
            return false;
        }
    }
}
