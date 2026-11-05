<?php

namespace App\Services\StudentAcademicPeriods;

use App\Constants\StudySemester;

class AcademicStandingCalculator
{
    public function studyYear(int $academicYear, int $entryYear): ?int
    {
        $studyYear = $academicYear - $entryYear + 1;

        return $entryYear > 0 && $studyYear > 0 ? $studyYear : null;
    }

    public function semesterFromTermCode(?string $termCode): ?int
    {
        return match ($termCode) {
            'first' => StudySemester::FIRST,
            'second' => StudySemester::SECOND,
            'summer' => StudySemester::SUMMER,
            default => null,
        };
    }

    public function studyPeriod(int $studyYear, int $semester): string
    {
        return sprintf(
            'ปีที่ %d %s',
            $studyYear,
            StudySemester::nameTh($semester),
        );
    }
}
