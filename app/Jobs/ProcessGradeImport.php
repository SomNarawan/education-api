<?php

namespace App\Jobs;

use App\Constants\Status;
use App\Models\DataImport;
use App\Models\Student;
use App\Services\Grades\GradeImportFileReader;
use App\Services\Grades\StudentGradeJsonGenerator;
use App\Services\Grades\StudentGradePlanResolver;
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
        StudentGradePlanResolver $planResolver,
        StudentGradeJsonGenerator $generator,
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
            $rows = $reader->read(Storage::disk('local')->path($this->sourcePath));
            $groupedRows = [];

            foreach ($rows as $row) {
                $groupedRows[$row['student_code']][] = $row;
            }

            $students = Student::query()
                ->whereIn('student_code', array_keys($groupedRows))
                ->get(['student_code', 'curriculum_id', 'study_plan_id'])
                ->keyBy(fn (Student $student): string => (string) $student->student_code);
            $import->update([
                'total_count' => count($groupedRows),
                'success_count' => 0,
                'failed_count' => 0,
            ]);

            $successCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($groupedRows as $studentCode => $studentRows) {
                try {
                    $resolvedPlan = $planResolver->resolve(
                        $students->get((string) $studentCode),
                        (int) $import->curriculum_id,
                    );

                    $generator->generate(
                        $studentCode,
                        $studentRows,
                        $resolvedPlan['courses'],
                        $resolvedPlan['study_plan_id'],
                    );
                    $successCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failedCount++;
                    $errors[] = "{$studentCode}: {$exception->getMessage()}";
                }

                $import->update([
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                ]);
            }

            $import->update([
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
