<?php

namespace App\Services;

use App\Models\StudentAcademicPeriodSync;
use App\Services\AcademicCalendars\AcademicCalendarPeriodResolver;
use App\Services\StudentAcademicPeriods\AcademicStandingCalculator;
use App\Services\StudentAcademicPeriods\StudentAcademicPeriodPreviewService;
use App\Services\StudentAcademicPeriods\StudentAcademicPeriodSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class StudentAcademicPeriodSyncService
{
    public function __construct(
        private readonly AcademicCalendarPeriodResolver $calendarResolver,
        private readonly AcademicStandingCalculator $standingCalculator,
        private readonly StudentAcademicPeriodPreviewService $previewService,
        private readonly StudentAcademicPeriodSynchronizer $synchronizer,
    ) {}

    public function requireCurrentPeriod(
        ?CarbonImmutable $referenceDate = null
    ): array {
        $calendar = $this->calendarResolver->resolve(
            $referenceDate ?? CarbonImmutable::today(config('app.timezone')),
        );

        if ($calendar['status'] !== 'ready') {
            throw ValidationException::withMessages([
                'academic_calendar' => [
                    $calendar['message'],
                ],
            ]);
        }

        return $calendar;
    }

    public function currentStandingForEntryYear(
        int $entryYear,
        ?CarbonImmutable $referenceDate = null
    ): array {
        $calendar = $this->requireCurrentPeriod($referenceDate);

        $studyYear = $this->standingCalculator->studyYear(
            (int) $calendar['academic_year'],
            $entryYear,
        );

        if ($studyYear === null) {
            throw ValidationException::withMessages([
                'entry_year' => ['ปีการศึกษาที่เข้าศึกษาไม่สอดคล้องกับปฏิทินการศึกษาปัจจุบัน'],
            ]);
        }

        return [
            'study_year' => $studyYear,
            'study_semester' => (int) $calendar['study_semester'],
        ];
    }

    public function preview(CarbonImmutable $referenceDate): array
    {
        return $this->previewService->preview($referenceDate);
    }

    public function sync(
        CarbonImmutable $referenceDate,
        string $actor
    ): StudentAcademicPeriodSync {
        return $this->synchronizer->sync($referenceDate, $actor);
    }
}
