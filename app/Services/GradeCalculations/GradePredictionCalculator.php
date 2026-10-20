<?php

namespace App\Services\GradeCalculations;

class GradePredictionCalculator
{
    public const GRADE_POINTS = [
        'A' => 4.0,
        'B+' => 3.5,
        'B' => 3.0,
        'C+' => 2.5,
        'C' => 2.0,
        'D+' => 1.5,
        'D' => 1.0,
        'F' => 0.0,
    ];

    public function calculate(array $courses, float $currentGpax, int $currentCredits): array
    {
        $courses = array_map($this->normalizeCourse(...), $courses);
        $termCredits = (int) array_sum(array_column($courses, 'credits'));
        $termQualityPoints = (float) array_sum(array_column($courses, 'grade_points'));
        $cumulativeCredits = $currentCredits + $termCredits;
        $cumulativeQualityPoints = ($currentGpax * $currentCredits) + $termQualityPoints;

        return [
            'current_gpax' => round($currentGpax, 4),
            'current_credits' => $currentCredits,
            'term_gpa' => round($termQualityPoints / $termCredits, 4),
            'term_credits' => $termCredits,
            'cumulative_gpa' => $cumulativeCredits > 0
                ? round($cumulativeQualityPoints / $cumulativeCredits, 4)
                : 0,
            'cumulative_credits' => $cumulativeCredits,
            'courses' => array_values($courses),
        ];
    }

    private function normalizeCourse(array $course): array
    {
        $credits = (int) $course['credits'];
        $group = isset($course['course_group']) ? trim((string) $course['course_group']) : '';

        return [
            'code' => trim($course['code']),
            'name' => trim($course['name']),
            'credits' => $credits,
            'source' => $course['source'],
            'course_group' => $group === '' ? null : $group,
            'course_group_gpa' => isset($course['course_group_gpa'])
                ? round((float) $course['course_group_gpa'], 4)
                : null,
            'grade' => $course['grade'],
            'grade_points' => round(self::GRADE_POINTS[$course['grade']] * $credits, 2),
        ];
    }
}
