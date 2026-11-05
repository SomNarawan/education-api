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
        $students = $this->students->get();
        $calendar = $this->calendarResolver->resolve($referenceDate);
        $summaries = $this->buildSummaries($students, $calendar);

        return [
            'reference_date' => $referenceDate->toDateString(),
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
            'ready_students' => collect($summaries)->sum('ready_count'),
            'update_students' => collect($summaries)->sum('update_count'),
            'unchanged_students' => collect($summaries)->sum('unchanged_count'),
            'missing_calendar_students' => collect($summaries)
                ->whereIn('status', ['missing_calendar', 'calendar_conflict'])
                ->sum('student_count'),
            'invalid_students' => collect($summaries)
                ->where('status', 'invalid_entry_year')
                ->sum('student_count'),
            'summaries' => $summaries,
        ];
    }

    private function buildSummaries(Collection $students, array $calendar): array
    {
        return $students
            ->groupBy(fn (Student $student): int => (int) $student->entry_year)
            ->map(function (Collection $entryYearStudents, int $entryYear) use ($calendar): array {
                $studentCount = $entryYearStudents->count();

                if ($calendar['status'] !== 'ready') {
                    return $this->summaryRow(
                        $entryYear,
                        $studentCount,
                        null,
                        $calendar,
                        $calendar['status'],
                        0,
                        0,
                    );
                }

                $studyYear = $this->standingCalculator->studyYear(
                    (int) $calendar['academic_year'],
                    $entryYear,
                );

                if ($studyYear === null) {
                    return $this->summaryRow(
                        $entryYear,
                        $studentCount,
                        null,
                        $calendar,
                        'invalid_entry_year',
                        0,
                        0,
                    );
                }

                $studyPeriod = $this->standingCalculator->studyPeriod(
                    $studyYear,
                    (int) $calendar['study_semester'],
                );
                $updateCount = $entryYearStudents->filter(
                    fn (Student $student): bool => (int) $student->study_year !== $studyYear
                        || (int) $student->study_semester !== (int) $calendar['study_semester']
                        || $student->study_period !== $studyPeriod
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
        $hasCalendar = $status !== 'missing_calendar';

        return [
            'entry_year' => $entryYear,
            'entry_year_be' => $entryYear + 543,
            'student_count' => $studentCount,
            'study_year' => $studyYear,
            'academic_year' => $hasCalendar ? $calendar['academic_year'] : null,
            'academic_year_be' => ! $hasCalendar || $calendar['academic_year'] === null
                ? null
                : $calendar['academic_year'] + 543,
            'term_code' => $hasCalendar ? $calendar['term_code'] : null,
            'term_name_th' => $hasCalendar ? $calendar['term_name_th'] : null,
            'ready_count' => $status === 'ready' ? $studentCount : 0,
            'update_count' => $updateCount,
            'unchanged_count' => $unchangedCount,
            'status' => $status,
        ];
    }
}
