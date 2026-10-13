<?php

namespace App\Services\Grades;

use App\Exceptions\GradeImportRowException;
use App\Models\Student;
use Throwable;

class GradeImportStudentRowsProcessor
{
    public function __construct(
        private readonly StudentGradePlanResolver $planResolver,
        private readonly StudentGradeJsonGenerator $generator,
    ) {}

    public function process(
        ?Student $student,
        int $curriculumId,
        int $importId,
        array $rows,
    ): array {
        $successfulRows = [];
        $failedRows = [];
        $pendingRows = array_values($rows);

        try {
            $resolvedPlan = $this->planResolver->resolve($student, $curriculumId);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'successful_rows' => [],
                'failed_rows' => $this->failedRows($pendingRows, $exception),
            ];
        }

        while ($pendingRows !== []) {
            try {
                $this->generator->generate(
                    (string) $pendingRows[0]['student_code'],
                    $pendingRows,
                    $resolvedPlan['courses'],
                    $resolvedPlan['study_plan_id'],
                    $importId,
                );

                $successfulRows = array_map(
                    fn (array $row): array => $row['_source_values'],
                    $pendingRows,
                );
                break;
            } catch (GradeImportRowException $exception) {
                $failedIndex = $this->rowIndex($pendingRows, $exception->sourceRow);

                if ($failedIndex === null) {
                    $failedRows = [
                        ...$failedRows,
                        ...$this->failedRows($pendingRows, $exception),
                    ];
                    break;
                }

                $failedRow = $pendingRows[$failedIndex];
                $failedRows[] = [
                    ...$failedRow['_source_values'],
                    $exception->getMessage(),
                ];
                unset($pendingRows[$failedIndex]);
                $pendingRows = array_values($pendingRows);
            } catch (Throwable $exception) {
                report($exception);
                $failedRows = [
                    ...$failedRows,
                    ...$this->failedRows($pendingRows, $exception),
                ];
                break;
            }
        }

        return [
            'successful_rows' => $successfulRows,
            'failed_rows' => $failedRows,
        ];
    }

    private function rowIndex(array $rows, int $sourceRow): ?int
    {
        foreach ($rows as $index => $row) {
            if ((int) ($row['_source_row'] ?? 0) === $sourceRow) {
                return $index;
            }
        }

        return null;
    }

    private function failedRows(array $rows, Throwable $exception): array
    {
        return array_map(
            fn (array $row): array => [
                ...$row['_source_values'],
                $this->failureReason($row, $exception),
            ],
            $rows,
        );
    }

    private function failureReason(array $row, Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if (preg_match('/^แถว\s+\d+:/u', $message) === 1) {
            return $message;
        }

        return "แถว {$row['_source_row']}: ".($message !== ''
            ? $message
            : 'ไม่สามารถนำเข้าข้อมูลได้');
    }
}
