<?php

namespace App\Services\StudentAcademicPeriods;

use App\Models\Student;
use App\Services\AcademicCalendars\AcademicCalendarPeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class StudentAcademicPeriodPreviewService
{
    public function __construct(
        private readonly EligibleStudentQuery $students,
        private readonly AcademicCalendarPeriodResolver $calendarResolver,
        private readonly AcademicStandingCalculator $standingCalculator,
    ) {}

    public function preview(CarbonImmutable $referenceDate): array
    {
        $groups = collect([false, true])
            ->map(fn (bool $isInternational): array => $this->previewGroup(
                $referenceDate,
                $isInternational,
            ))
            ->all();
        $groupCollection = collect($groups);

        return [
            'reference_date' => $referenceDate->toDateString(),
            'total_students' => $groupCollection->sum('total_students'),
            'ready_students' => $groupCollection->sum('ready_students'),
            'update_students' => $groupCollection->sum('update_students'),
            'unchanged_students' => $groupCollection->sum('unchanged_students'),
            'missing_calendar_students' => $groupCollection->sum('missing_calendar_students'),
            'invalid_students' => $groupCollection->sum('invalid_students'),
            'groups' => $groups,
        ];
    }

    private function previewGroup(
        CarbonImmutable $referenceDate,
        bool $isInternational,
    ): array {
        $students = $this->students->get($isInternational);
        $calendarKey = $isInternational ? 'international' : 'thai';

        if ($students->isEmpty()) {
            return $this->groupResult(
                $isInternational,
                $calendarKey,
                $this->emptyCalendar(),
                $students,
                [],
            );
        }

        $calendar = $this->calendarResolver->resolve($referenceDate, $calendarKey);
        $summaries = $this->buildSummaries($students, $calendar);

        return $this->groupResult(
            $isInternational,
            $calendarKey,
            $calendar,
            $students,
            $summaries,
        );
    }

    private function groupResult(
        bool $isInternational,
        string $calendarKey,
        array $calendar,
        Collection $students,
        array $summaries,
    ): array {
        $summaryCollection = collect($summaries);

        return [
            'is_international' => $isInternational,
            'calendar_key' => $calendarKey,
            'calendar_status' => $calendar['status'],
            'calendar_message' => $calendar['message'],
            'academic_year' => $calendar['academic_year'],
            'academic_year_be' => $calendar['academic_year'] === null
                ? null
                : $calendar['academic_year'] + 543,
            'term_code' => $calendar['term_code'],
            'term_name_th' => $calendar['term_name_th'],
            'study_semester' => $calendar['study_semester'],
            'calendar_curricula' => $calendar['curricula'],
            'total_students' => $students->count(),
            'ready_students' => $summaryCollection->sum('ready_count'),
            'update_students' => $summaryCollection->sum('update_count'),
            'unchanged_students' => $summaryCollection->sum('unchanged_count'),
            'missing_calendar_students' => $summaryCollection
                ->whereIn('status', ['missing_calendar', 'calendar_conflict'])
                ->sum('student_count'),
            'invalid_students' => $summaryCollection
                ->where('status', 'invalid_entry_year')
                ->sum('student_count'),
            'summaries' => $summaries,
        ];
    }

    private function emptyCalendar(): array
    {
        return [
            'status' => 'not_required',
            'message' => 'ไม่มีนิสิตในกลุ่มนี้',
            'academic_year' => null,
            'term_code' => null,
            'term_name_th' => null,
            'study_semester' => null,
            'curricula' => [],
        ];
    }

    private function buildSummaries(Collection $students, array $calendar): array
    {
        return $students
            ->groupBy(fn (Student $student): int => (int) $student->entry_year)
            ->map(function (Collection $entryYearStudents, int $entryYear) use ($calendar): array {
                $studentCount = $entryYearStudents->count();

                if ($calendar['status'] !== 'ready') {
                    return $this->summaryRow($entryYear, $studentCount, null, $calendar, $calendar['status'], 0, 0);
                }

                $studyYear = $this->standingCalculator->studyYear(
                    (int) $calendar['academic_year'],
                    $entryYear,
                );
                if ($studyYear === null) {
                    return $this->summaryRow($entryYear, $studentCount, null, $calendar, 'invalid_entry_year', 0, 0);
                }

                $studyPeriod = $this->standingCalculator->studyPeriod(
                    $studyYear,
                    (int) $calendar['study_semester'],
                );
                $updateCount = $entryYearStudents->filter(
                    fn (Student $student): bool => (int) $student->study_year !== $studyYear
                        || (int) $student->study_semester !== (int) $calendar['study_semester']
                        || $student->study_period !== $studyPeriod,
                )->count();

                return $this->summaryRow(
                    $entryYear,
                    $studentCount,
                    $studyYear,
                    $calendar,
                    'ready',
                    $updateCount,
                    $studentCount - $updateCount,
                );
            })
            ->sortByDesc('entry_year')
            ->values()
            ->all();
    }

    private function summaryRow(
        int $entryYear,
        int $studentCount,
        ?int $studyYear,
        array $calendar,
        string $status,
        int $updateCount,
        int $unchangedCount,
    ): array {
        $hasCalendar = ! in_array($status, ['missing_calendar', 'not_required'], true);

        return [
            'entry_year' => $entryYear,
            'entry_year_be' => $entryYear + 543,
            'student_count' => $studentCount,
            'study_year' => $studyYear,
            'academic_year' => $hasCalendar ? $calendar['academic_year'] : null,
            'academic_year_be' => $hasCalendar && $calendar['academic_year'] !== null
                ? $calendar['academic_year'] + 543
                : null,
            'term_code' => $hasCalendar ? $calendar['term_code'] : null,
            'term_name_th' => $hasCalendar ? $calendar['term_name_th'] : null,
            'ready_count' => $status === 'ready' ? $studentCount : 0,
            'update_count' => $updateCount,
            'unchanged_count' => $unchangedCount,
            'status' => $status,
        ];
    }
}
