<?php

namespace App\Services\Students;

use App\Constants\StudySemester;
use App\Models\Student;
use App\Services\Storage\LocalJsonFileStore;

class StudentAcademicBaselineService
{
    public function __construct(
        private readonly LocalJsonFileStore $jsonFiles,
    ) {}

    public function resolve(Student $student, ?array $historyRows = null): array
    {
        $fallback = [
            'gpa' => $student->gpa === null ? null : (float) $student->gpa,
            'gpax' => (float) ($student->gpax ?? 0),
            'credits' => (int) ($student->passed_credits ?? 0),
        ];
        $studentCode = trim((string) $student->student_code);

        if ($historyRows === null && $studentCode !== '') {
            $historyRows = $this->jsonFiles->read(
                "data/graph/by_semester/{$studentCode}.json"
            );
        }

        $history = array_values(array_filter(
            $historyRows ?? [],
            fn (mixed $row): bool => is_array($row),
        ));

        if ($history === []) {
            return $fallback;
        }

        usort($history, fn (array $left, array $right): int => $this->termOrder($left) <=> $this->termOrder($right));
        $latest = $history[array_key_last($history)];
        $credits = array_sum(array_map(
            fn (array $row): float => $this->termCredits($row),
            $history,
        ));

        return [
            'gpa' => is_numeric($latest['gpa'] ?? null)
                ? (float) $latest['gpa']
                : $fallback['gpa'],
            'gpax' => is_numeric($latest['gpax'] ?? null)
                ? (float) $latest['gpax']
                : $fallback['gpax'],
            'credits' => $credits > 0
                ? (int) round($credits)
                : $fallback['credits'],
        ];
    }

    private function termCredits(array $row): float
    {
        $credits = $row['gpa_credits'] ?? $row['credits'] ?? 0;

        return is_numeric($credits) ? max(0, (float) $credits) : 0;
    }

    private function termOrder(array $row): array
    {
        $year = $row['academic_year'] ?? $row['semester_year'] ?? $row['study_year'] ?? 0;
        $semesterOrder = $row['semester_order'] ?? null;

        if (! is_numeric($semesterOrder)) {
            $semesterOrder = StudySemester::fromInput((string) ($row['semester'] ?? '')) ?? 0;
        }

        return [(int) $year, (int) $semesterOrder];
    }
}
