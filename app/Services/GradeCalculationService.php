<?php

namespace App\Services;

use App\Models\GradeCalculation;
use App\Models\Student;

class GradeCalculationService
{
    private const GRADE_POINTS = [
        'A' => 4.0,
        'B+' => 3.5,
        'B' => 3.0,
        'C+' => 2.5,
        'C' => 2.0,
        'D+' => 1.5,
        'D' => 1.0,
        'F' => 0.0,
    ];

    public function store(Student $student, array $attributes): GradeCalculation
    {
        $courses = collect($attributes['courses'])
            ->map(function (array $course): array {
                $credits = (int) $course['credits'];
                $gradePoints = self::GRADE_POINTS[$course['grade']] * $credits;

                return [
                    'code' => trim($course['code']),
                    'name' => trim($course['name']),
                    'credits' => $credits,
                    'source' => $course['source'],
                    'grade' => $course['grade'],
                    'grade_points' => round($gradePoints, 2),
                ];
            })
            ->values();
        $termCredits = (int) $courses->sum('credits');
        $termQualityPoints = (float) $courses->sum('grade_points');
        $currentGpax = (float) ($student->gpax ?? 0);
        $currentCredits = (int) ($student->passed_credits ?? 0);
        $cumulativeCredits = $currentCredits + $termCredits;
        $termGpa = $termQualityPoints / $termCredits;
        $cumulativeGpa = $cumulativeCredits > 0
            ? (($currentGpax * $currentCredits) + $termQualityPoints) / $cumulativeCredits
            : 0;

        return GradeCalculation::query()->create([
            'student_id' => $student->getKey(),
            'study_year' => $attributes['study_year'],
            'semester' => trim($attributes['semester']),
            'semester_order' => $attributes['semester_order'] ?? null,
            'current_gpax' => round($currentGpax, 4),
            'current_credits' => $currentCredits,
            'term_gpa' => round($termGpa, 4),
            'term_credits' => $termCredits,
            'cumulative_gpa' => round($cumulativeGpa, 4),
            'cumulative_credits' => $cumulativeCredits,
            'courses' => $courses->all(),
        ]);
    }
}
