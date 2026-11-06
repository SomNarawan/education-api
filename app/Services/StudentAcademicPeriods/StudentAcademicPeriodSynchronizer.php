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

    public function sync(CarbonImmutable $referenceDate, string $actor): Collection
    {
        $preview = $this->previewService->preview($referenceDate);
        $outcomes = collect($preview['groups'])
            ->map(fn (array $group): array => $this->syncGroup(
                $referenceDate,
                $actor,
                $group,
            ));
        $unexpectedException = $outcomes
            ->pluck('exception')
            ->first(fn (?Throwable $exception): bool => $exception !== null);

        if ($unexpectedException !== null) {
            throw $unexpectedException;
        }

        return new Collection($outcomes->pluck('history')->all());
    }

    private function syncGroup(
        CarbonImmutable $referenceDate,
        string $actor,
        array $group,
    ): array {
        $history = StudentAcademicPeriodSync::query()->create([
            'reference_date' => $referenceDate->toDateString(),
            'is_international' => $group['is_international'],
            'academic_year' => $group['academic_year'],
            'term_code' => $group['term_code'],
            'term_name_th' => $group['term_name_th'],
            'total_students' => $group['total_students'],
            'updated_count' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'status' => 'running',
            'error_message' => null,
            'created_by' => $actor,
        ]);

        if ($group['total_students'] === 0) {
            $history->update(['status' => 'success']);

            return $this->outcome($history->refresh());
        }

        if ($group['calendar_status'] !== 'ready') {
            $history->update([
                'failed_count' => $group['total_students'],
                'status' => 'failed',
                'error_message' => $group['calendar_message'],
            ]);

            return $this->outcome($history->refresh());
        }

        try {
            $counts = $this->updateStudents($group);
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
        } catch (Throwable $exception) {
            $history->update([
                'failed_count' => $group['total_students'],
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return $this->outcome($history->refresh(), $exception);
        }

        return $this->outcome($history->refresh());
    }

    private function outcome(
        StudentAcademicPeriodSync $history,
        ?Throwable $exception = null,
    ): array {
        return compact('history', 'exception');
    }

    private function updateStudents(array $group): array
    {
        return DB::transaction(function () use ($group): array {
            $counts = ['updated' => 0, 'skipped' => 0, 'failed' => 0];

            $this->students->builder($group['is_international'])
                ->orderBy('id')
                ->chunkById(500, function (Collection $students) use (&$counts, $group): void {
                    foreach ($students as $student) {
                        $studyYear = $this->standingCalculator->studyYear(
                            (int) $group['academic_year'],
                            (int) $student->entry_year,
                        );
                        if ($studyYear === null) {
                            $counts['failed']++;

                            continue;
                        }

                        $student->fill([
                            'study_year' => $studyYear,
                            'study_semester' => $group['study_semester'],
                            'study_period' => $this->standingCalculator->studyPeriod(
                                $studyYear,
                                (int) $group['study_semester'],
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
    }
}
