<?php

namespace App\Services;

use App\Constants\StudySemester;
use App\Models\AcademicTermRollover;
use App\Models\Student;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class AcademicTermRolloverService
{
    public function preview(): array
    {
        $students = $this->eligibleStudents()->get();

        return $this->buildPreview($students);
    }

    public function advance(string $previewToken, string $actor): AcademicTermRollover
    {
        return DB::transaction(function () use ($previewToken, $actor): AcademicTermRollover {
            $students = $this->eligibleStudents()->lockForUpdate()->get();
            $preview = $this->buildPreview($students);

            if ($preview['eligible_count'] === 0) {
                throw new UnexpectedValueException('No studying students found');
            }

            if ($preview['invalid_semester_count'] > 0) {
                throw new UnexpectedValueException(
                    'Some studying students have an invalid semester'
                );
            }

            if (! hash_equals($preview['preview_token'], $previewToken)) {
                throw new DomainException(
                    'Student semester data changed. Please review the latest preview.'
                );
            }

            $students
                ->groupBy(fn (Student $student): string => sprintf(
                    '%d:%d',
                    $student->study_year,
                    $student->study_semester,
                ))
                ->each(function (Collection $group): void {
                    $student = $group->first();
                    $currentSemester = (int) $student->study_semester;
                    $currentYear = (int) $student->study_year;
                    $nextSemester = $this->nextSemester($currentSemester);
                    $nextYear = $currentSemester === StudySemester::SUMMER
                        ? $currentYear + 1
                        : $currentYear;

                    Student::query()
                        ->whereKey($group->modelKeys())
                        ->update([
                            'study_year' => $nextYear,
                            'study_semester' => $nextSemester,
                            'study_period' => sprintf(
                                'ปีที่ %d %s',
                                $nextYear,
                                StudySemester::nameTh($nextSemester),
                            ),
                        ]);
                });

            return AcademicTermRollover::query()->create([
                'transition_summary' => $preview['transitions'],
                'affected_count' => $preview['eligible_count'],
                'created_by' => $actor,
            ]);
        });
    }

    private function eligibleStudents()
    {
        return Student::query()
            ->studying()
            ->orderBy('id')
            ->select(['id', 'study_year', 'study_semester']);
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function buildPreview(Collection $students): array
    {
        $validSemesters = StudySemester::values();
        $invalidSemesterCount = $students->filter(
            fn (Student $student): bool => ! in_array(
                (int) $student->study_semester,
                $validSemesters,
                true,
            )
        )->count();
        $transitions = collect($validSemesters)
            ->map(function (int $semester) use ($students): array {
                $nextSemester = $this->nextSemester($semester);

                return [
                    'from_semester' => $semester,
                    'from_name' => StudySemester::nameTh($semester),
                    'to_semester' => $nextSemester,
                    'to_name' => StudySemester::nameTh($nextSemester),
                    'increments_study_year' => $semester === StudySemester::SUMMER,
                    'affected_count' => $students->where('study_semester', $semester)->count(),
                ];
            })
            ->filter(fn (array $transition): bool => $transition['affected_count'] > 0)
            ->values()
            ->all();

        return [
            'eligible_count' => $students->count(),
            'invalid_semester_count' => $invalidSemesterCount,
            'can_advance' => $students->isNotEmpty() && $invalidSemesterCount === 0,
            'transitions' => $transitions,
            'preview_token' => $this->previewToken($students),
        ];
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function previewToken(Collection $students): string
    {
        $state = $students
            ->map(fn (Student $student): array => [
                (int) $student->id,
                (int) $student->study_year,
                (int) $student->study_semester,
            ])
            ->all();

        return hash_hmac(
            'sha256',
            json_encode($state, JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );
    }

    private function nextSemester(int $semester): int
    {
        return match ($semester) {
            StudySemester::FIRST => StudySemester::SECOND,
            StudySemester::SECOND => StudySemester::SUMMER,
            StudySemester::SUMMER => StudySemester::FIRST,
            default => throw new UnexpectedValueException('Invalid study semester'),
        };
    }
}
