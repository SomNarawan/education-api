<?php

namespace App\Jobs;

use App\Constants\Status;
use App\Models\DataImport;
use App\Models\Student;
use App\Services\Grades\GradeImportFileReader;
use App\Services\Grades\GradeImportResultWriter;
use App\Services\Grades\GradeImportStudentRowsProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessGradeImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public readonly int $importId,
        public readonly string $sourcePath,
    ) {}

    public function handle(
        GradeImportFileReader $reader,
        GradeImportResultWriter $resultWriter,
        GradeImportStudentRowsProcessor $studentRowsProcessor,
    ): void {
        $import = DataImport::query()->find($this->importId);

        if ($import === null) {
            return;
        }

        $import->update([
            'status' => Status::PROCESSING,
            'error_message' => null,
        ]);

        try {
            $readResult = $reader->readWithFailures(
                Storage::disk('local')->path($this->sourcePath),
            );
            $rows = $readResult['rows'];
            $groupedRows = [];

            foreach ($rows as $row) {
                $groupedRows[$row['student_code']][] = $row;
            }

            $students = Student::query()
                ->whereIn('student_code', array_keys($groupedRows))
                ->get(['student_code', 'curriculum_id', 'study_plan_id'])
                ->keyBy(fn (Student $student): string => (string) $student->student_code);
            $successRows = [];
            $failedRows = array_map(
                fn (array $failure): array => [
                    ...$failure['values'],
                    $failure['error'],
                ],
                $readResult['failed_rows'],
            );

            foreach ($groupedRows as $studentCode => $studentRows) {
                $processed = $studentRowsProcessor->process(
                    $students->get((string) $studentCode),
                    (int) $import->curriculum_id,
                    $import->id,
                    $studentRows,
                );
                array_push($successRows, ...$processed['successful_rows']);
                array_push($failedRows, ...$processed['failed_rows']);
            }

            $resultPath = $resultWriter->write($import->id, $successRows, $failedRows);
            $successCount = count($successRows);
            $failedCount = count($failedRows);
            $errors = array_column($failedRows, 6);

            $import->update([
                'file_result_path' => $resultPath,
                'total_count' => $successCount + $failedCount,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'status' => $failedCount === 0
                    ? Status::COMPLETED
                    : ($successCount > 0 ? Status::COMPLETED_WITH_ERRORS : Status::FAILED),
                'error_message' => $errors === []
                    ? null
                    : mb_substr(implode("\n", array_slice($errors, 0, 20)), 0, 4000),
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $import->update([
                'status' => Status::FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DataImport::query()->whereKey($this->importId)->update([
            'status' => Status::FAILED,
            'error_message' => mb_substr($exception?->getMessage() ?? 'งานนำเข้าเกรดล้มเหลว', 0, 4000),
            'completed_at' => now(),
        ]);
    }
}
