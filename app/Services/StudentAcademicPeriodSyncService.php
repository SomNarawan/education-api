<?php

namespace App\Services;

use App\Constants\StudySemester;
use App\Models\Student;
use App\Models\StudentAcademicPeriodSync;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentAcademicPeriodSyncService
{
    private const APPROVED_THAI_CURRICULUM_CODES = [
        'CURR-KPS-ENG-COMP-2565',
    ];

    public function __construct(
        private readonly AcademicCalendarJsonStore $calendarStore,
    ) {}

    public function requireCurrentPeriod(
        ?CarbonImmutable $referenceDate = null
    ): array {
        $calendar = $this->resolveCalendarPeriod(
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

        $studyYear = $this->studyYear(
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
        $students = $this->studyingStudents();
        $calendar = $this->resolveCalendarPeriod($referenceDate);
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

    public function sync(
        CarbonImmutable $referenceDate,
        string $actor
    ): StudentAcademicPeriodSync {
        $preview = $this->preview($referenceDate);
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
                $counts = [
                    'updated' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ];

                $this->approvedThaiCurriculumStudents()
                    ->orderBy('id')
                    ->chunkById(500, function (Collection $students) use (&$counts, $preview): void {
                        foreach ($students as $student) {
                            $studyYear = $this->studyYear(
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
                                'study_period' => $this->studyPeriod(
                                    $studyYear,
                                    (int) $preview['study_semester'],
                                ),
                            ]);

                            if (! $student->isDirty([
                                'study_year',
                                'study_semester',
                                'study_period',
                            ])) {
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

    private function studyingStudents(): Collection
    {
        return $this->approvedThaiCurriculumStudents()
            ->orderByDesc('entry_year')
            ->orderBy('id')
            ->get([
                'id',
                'entry_year',
                'study_year',
                'study_semester',
                'study_period',
            ]);
    }

    private function approvedThaiCurriculumStudents(): Builder
    {
        return Student::query()
            ->studying()
            ->whereIn(
                'curriculum_code',
                self::APPROVED_THAI_CURRICULUM_CODES,
            );
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

                $studyYear = $this->studyYear(
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

                $studyPeriod = $this->studyPeriod(
                    $studyYear,
                    (int) $calendar['study_semester'],
                );
                $updateCount = $entryYearStudents->filter(
                    fn (Student $student): bool => (int) $student->study_year !== $studyYear ||
                        (int) $student->study_semester !== (int) $calendar['study_semester'] ||
                        $student->study_period !== $studyPeriod
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
        int $unchangedCount
    ): array {
        $hasCalendar = $status !== 'missing_calendar';

        return [
            'entry_year' => $entryYear,
            'entry_year_be' => $entryYear + 543,
            'student_count' => $studentCount,
            'study_year' => $studyYear,
            'academic_year' => $hasCalendar
                ? $calendar['academic_year']
                : null,
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

    private function resolveCalendarPeriod(CarbonImmutable $referenceDate): array
    {
        foreach ($this->calendarStore->all() as $calendar) {
            $terms = collect($calendar['payload']['terms'] ?? [])
                ->filter(fn (mixed $term): bool => is_array($term) &&
                    is_string($term['curriculum'] ?? null)
                );

            if ($terms->isEmpty()) {
                continue;
            }

            $curricula = $terms
                ->groupBy('curriculum')
                ->map(function ($curriculumTerms, string $curriculum) use ($referenceDate): array {
                    $activeTerm = collect($curriculumTerms)->first(
                        fn (array $term): bool => $this->dateIsWithinTerm(
                            $referenceDate,
                            $term,
                        )
                    );
                    $firstTerm = collect($curriculumTerms)->first();

                    return [
                        'curriculum' => $curriculum,
                        'curriculum_name_th' => $firstTerm['curriculumNameTh'] ?? $curriculum,
                        'term_code' => $activeTerm['code'] ?? null,
                        'term_name_th' => $activeTerm['nameTh'] ?? null,
                        'start_date' => $activeTerm['startDate'] ?? null,
                        'end_date' => $activeTerm['endDate'] ?? null,
                        'status' => $activeTerm === null ? 'missing' : 'ready',
                    ];
                })
                ->values();

            $thaiCurriculum = $curricula->firstWhere('curriculum', 'thai');

            if (
                $thaiCurriculum === null ||
                $thaiCurriculum['status'] !== 'ready'
            ) {
                continue;
            }

            $academicYear = $calendar['academic_year'];
            $semester = $this->semesterFromTermCode(
                $thaiCurriculum['term_code'],
            );

            if ($semester === null) {
                return $this->calendarResult(
                    'conflict',
                    'รหัสภาคการศึกษาในปฏิทินไม่รองรับการ Sync',
                    $academicYear,
                    null,
                    $curricula->all(),
                );
            }

            return $this->calendarResult(
                'ready',
                'พบปฏิทินการศึกษาที่พร้อมใช้สำหรับ Sync',
                $academicYear,
                [
                    'code' => $thaiCurriculum['term_code'],
                    'name_th' => $thaiCurriculum['term_name_th'],
                    'semester' => $semester,
                ],
                $curricula->all(),
            );
        }

        return $this->calendarResult(
            'missing',
            'ไม่พบปฏิทินการศึกษาที่ครอบคลุมวันที่ปัจจุบัน กรุณาไป Sync ปฏิทินการศึกษาก่อน',
            null,
            null,
            [],
        );
    }

    private function calendarResult(
        string $status,
        string $message,
        ?int $academicYear,
        ?array $term,
        array $curricula
    ): array {
        return [
            'status' => $status === 'conflict' ? 'calendar_conflict' : ($status === 'missing' ? 'missing_calendar' : 'ready'),
            'message' => $message,
            'academic_year' => $academicYear,
            'term_code' => $term['code'] ?? null,
            'term_name_th' => $term['name_th'] ?? null,
            'study_semester' => $term['semester'] ?? null,
            'curricula' => $curricula,
        ];
    }

    private function dateIsWithinTerm(
        CarbonImmutable $referenceDate,
        array $term
    ): bool {
        $startDate = $term['startDate'] ?? null;
        $endDate = $term['endDate'] ?? null;

        if (! is_string($startDate) || ! is_string($endDate)) {
            return false;
        }

        try {
            return $referenceDate->betweenIncluded(
                CarbonImmutable::createFromFormat('Y-m-d', $startDate),
                CarbonImmutable::createFromFormat('Y-m-d', $endDate),
            );
        } catch (Throwable) {
            return false;
        }
    }

    private function semesterFromTermCode(?string $termCode): ?int
    {
        return match ($termCode) {
            'first' => StudySemester::FIRST,
            'second' => StudySemester::SECOND,
            'summer' => StudySemester::SUMMER,
            default => null,
        };
    }

    private function studyYear(int $academicYear, int $entryYear): ?int
    {
        $studyYear = $academicYear - $entryYear + 1;

        return $entryYear > 0 && $studyYear > 0 ? $studyYear : null;
    }

    private function studyPeriod(int $studyYear, int $semester): string
    {
        return sprintf(
            'ปีที่ %d %s',
            $studyYear,
            StudySemester::nameTh($semester),
        );
    }
}
