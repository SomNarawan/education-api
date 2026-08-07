<?php

namespace App\Services\Students;

use Illuminate\Support\Carbon;

class AcademicStandingCalculator
{
    public function calculate(int $entryYear): array
    {
        $now = Carbon::now();
        $academicYear = $now->month >= 8 ? $now->year : $now->year - 1;
        $studyYear = max(1, $academicYear - $entryYear + 1);
        $semester = match (true) {
            $now->month >= 8 => 1,
            $now->month <= 5 => 2,
            default => 3,
        };
        $semesterLabel = match ($semester) {
            1 => 'ภาคต้น',
            2 => 'ภาคปลาย',
            default => 'ภาคฤดูร้อน',
        };

        return [
            'study_year' => $studyYear,
            'study_semester' => $semester,
            'study_period' => "ปีที่ {$studyYear} {$semesterLabel}",
        ];
    }
}
