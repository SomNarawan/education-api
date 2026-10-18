<?php

namespace App\Services;

use App\Models\Student;
use App\Services\Storage\LocalJsonFileStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GradeCalculationService
{
    private const DIRECTORY = 'data/grade_calculations';

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

    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
    ) {}

    public function history(?string $studentCode = null): array
    {
        $directory = self::DIRECTORY;

        if ($studentCode !== null) {
            $directory .= '/'.$this->normalizeStudentCode($studentCode);
        }

        $calculations = collect(Storage::disk('local')->allFiles($directory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.json'))
            ->map(fn (string $path): ?array => $this->jsonFiles->read($path))
            ->filter(fn (?array $calculation): bool => $calculation !== null)
            ->values()
            ->all();

        usort($calculations, function (array $left, array $right): int {
            $createdAtComparison = strcmp(
                (string) ($right['created_at'] ?? ''),
                (string) ($left['created_at'] ?? '')
            );

            return $createdAtComparison !== 0
                ? $createdAtComparison
                : ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
        });

        return $calculations;
    }

    public function store(Student $student, array $attributes): array
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
        $createdAt = now();
        $calculation = [
            'id' => (int) $createdAt->format('Uu'),
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
            'current_gpax' => round($currentGpax, 4),
            'current_credits' => $currentCredits,
            'term_gpa' => round($termGpa, 4),
            'term_credits' => $termCredits,
            'cumulative_gpa' => round($cumulativeGpa, 4),
            'cumulative_credits' => $cumulativeCredits,
            'courses' => $courses->all(),
            'created_at' => $createdAt->toISOString(),
        ];

        $studentCode = $this->normalizeStudentCode((string) $student->student_code);
        $path = self::DIRECTORY."/{$studentCode}/{$calculation['id']}.json";

        while (Storage::disk('local')->exists($path)) {
            usleep(1);
            $createdAt = now();
            $calculation['id'] = (int) $createdAt->format('Uu');
            $calculation['created_at'] = $createdAt->toISOString();
            $path = self::DIRECTORY."/{$studentCode}/{$calculation['id']}.json";
        }

        $this->jsonFiles->write($path, $calculation);

        return $calculation;
    }

    private function normalizeStudentCode(string $studentCode): string
    {
        $studentCode = trim($studentCode);

        if ($studentCode === '' || ! ctype_digit($studentCode)) {
            throw new RuntimeException('Student code is invalid');
        }

        return $studentCode;
    }
}
