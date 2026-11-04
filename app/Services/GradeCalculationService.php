<?php

namespace App\Services;

use App\Models\Student;
use App\Services\GradeCalculations\GradeCalculationJsonStore;
use App\Services\GradeCalculations\GradePredictionCalculator;
use App\Services\Students\StudentAcademicBaselineService;

class GradeCalculationService
{
    public function __construct(
        private readonly GradeCalculationJsonStore $jsonStore,
        private readonly GradePredictionCalculator $calculator,
        private readonly StudentAcademicBaselineService $academicBaseline,
    ) {}

    public function history(?string $studentCode = null): array
    {
        return $this->jsonStore->history($studentCode);
    }

    public function latest(string $studentCode): ?array
    {
        return $this->jsonStore->latest($studentCode);
    }

    public function store(Student $student, array $attributes): array
    {
        $baseline = $this->academicBaseline->resolve($student);
        $prediction = $this->calculator->calculate(
            $attributes['courses'],
            $baseline['gpax'],
            $baseline['credits'],
        );
        $calculation = [
            'student' => [
                'id' => (int) $student->getKey(),
                'student_code' => (string) $student->student_code,
                'full_name_th' => trim(
                    ($student->first_name_th ?? '').' '.($student->last_name_th ?? '')
                ),
            ],
            'study_year' => (int) $attributes['study_year'],
            'semester' => trim($attributes['semester']),
            'semester_order' => isset($attributes['semester_order'])
                ? (int) $attributes['semester_order']
                : null,
            'current_gpa' => $baseline['gpa'] === null
                ? null
                : round($baseline['gpa'], 4),
            ...$prediction,
        ];

        return $this->jsonStore->append((string) $student->student_code, $calculation);
    }
}
