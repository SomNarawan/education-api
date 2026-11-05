<?php

namespace App\Services\StudentAcademicPeriods;

use App\Models\StudentAcademicPeriodSync;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentAcademicPeriodSynchronizer
{
    public function __construct(
        private readonly StudentAcademicPeriodPreviewService $previewService,
        private readonly EligibleStudentQuery $students,
        private readonly AcademicStandingCalculator $standingCalculator,
    ) {}

    public function sync(
        CarbonImmutable $referenceDate,
        string $actor,
    ): StudentAcademicPeriodSync {
        $preview = $this->previewService->preview($referenceDate);
        $history = StudentAcademicPeriodSync::query()->create([
            'reference_date' => $referenceDate->toDateString(),
            'academic_year' => $preview['academic_year'],
            'term_code' => $preview['term_code'],
            'term_name_th' => $preview['term_name_th'],
            'total_students' => $preview['total_students'],
            'updated_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'status' => 'running',
            'error_message' => null,
            'created_by' => $actor,
        ]);

        if ($preview['calendar_status'] !== 'ready') {
            $history->update([
                'failed_count' => $preview['total_students'],
                'status' => 'failed',
                'error_message' => $preview['calendar_message'],
            ]);

            return $history->refresh();
        }

        try {
            $counts = DB::transaction(function () use ($preview): array {
                $counts = ['updated' => 0, 'skipped' => 0, 'failed' => 0];

                $this->students->builder()
                    ->orderBy('id')
                    ->chunkById(500, function (Collection $students) use (&$counts, $preview): void {
                        foreach ($students as $student) {
                            $studyYear = $this->standingCalculator->studyYear(
                                (int) $preview['academic_year'],
                                (int) $student->entry_year,
                            );

                            if ($studyYear === null) {
                                $counts['failed']++;

                                continue;
                            }

                            $student->fill([
                                'study_year' => $studyYear,
                                'study_semester' => $preview['study_semester'],
                                'study_period' => $this->standingCalculator->studyPeriod(
                                    $studyYear,
                                    (int) $preview['study_semester'],
                                ),
                            ]);

                            if (! $student->isDirty(['study_year', 'study_semester', 'study_period'])) {
                                $counts['skipped']++;

                                continue;
                            }

                            $student->save();
                            $counts['updated']++;
                        }
                    });

                return $counts;
            });

            $status = $counts['failed'] > 0
                ? ($counts['updated'] > 0 || $counts['skipped'] > 0 ? 'partial' : 'failed')
                : 'success';

            $history->update([
                'updated_count' => $counts['updated'],
                'skipped_count' => $counts['skipped'],
                'failed_count' => $counts['failed'],
                'status' => $status,
                'error_message' => $counts['failed'] > 0
                    ? 'นิสิตบางรายการมีปีการศึกษาที่เข้าเรียนไม่ถูกต้อง'
                    : null,
            ]);

            return $history->refresh();
        } catch (Throwable $exception) {
            $history->update([
                'failed_count' => $preview['total_students'],
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
