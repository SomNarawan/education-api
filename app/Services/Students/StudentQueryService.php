<?php

namespace App\Services\Students;

use App\Contracts\CurriculumApi;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentQueryService
{
    public function __construct(private readonly CurriculumApi $curriculumApi) {}

    public function list(array $filters): Collection
    {
        $students = $this->listQuery()
            ->when(
                isset($filters['teacher_id']),
                fn (Builder $query) => $query
                    ->where('teacher_id', $filters['teacher_id'])
                    ->studying()
            )
            ->when(
                isset($filters['department_id']),
                fn (Builder $query) => $query->where('system_department_id', $filters['department_id'])
            )
            ->when(
                isset($filters['faculty_id']),
                fn (Builder $query) => $query->whereHas(
                    'systemDepartment',
                    fn (Builder $departmentQuery) => $departmentQuery
                        ->where('system_faculty_id', $filters['faculty_id'])
                )
            )
            ->when(
                isset($filters['student_status_id']),
                fn (Builder $query) => $query->where('student_status_id', $filters['student_status_id'])
            )
            ->when(
                isset($filters['search_text']),
                fn (Builder $query) => $this->applyTextSearch($query, trim($filters['search_text']))
            )
            ->when(
                isset($filters['search_note']),
                fn (Builder $query) => $this->applyNoteSearch($query, trim($filters['search_note']))
            )
            ->orderBy('id')
            ->get();

        $students->each(fn (Student $student) => $this->attachStudyPlan($student));

        return $students;
    }

    public function detail(int $id): ?Student
    {
        $student = $this->detailQuery()->find($id);

        return $student === null ? null : $this->attachStudyPlan($student);
    }

    private function listQuery(): Builder
    {
        return Student::query()->with([
            'title',
            'systemTeacher',
            'studentStatus',
            'systemDepartment.systemFaculty',
        ]);
    }

    private function detailQuery(): Builder
    {
        return Student::query()->with([
            'title',
            'systemTeacher',
            'studentStatus',
            'admissionChannel',
            'highSchool.subdistrict.district.province',
            'systemDepartment.systemFaculty',
            'guardianTitle',
            'guardianRelationship',
        ]);
    }

    private function attachStudyPlan(Student $student): Student
    {
        $studyPlan = $student->study_plan_id === null
            ? null
            : $this->curriculumApi->findStudyPlan((int) $student->study_plan_id);

        $student->setAttribute('study_plan_data', $studyPlan);

        return $student;
    }

    private function applyTextSearch(Builder $query, string $searchText): void
    {
        $query->where(function (Builder $searchQuery) use ($searchText): void {
            $pattern = "%{$searchText}%";

            $searchQuery
                ->where('student_code', 'like', $pattern)
                ->orWhere('first_name_th', 'like', $pattern)
                ->orWhere('last_name_th', 'like', $pattern)
                ->orWhere('student_id_card', 'like', $pattern);
        });
    }

    private function applyNoteSearch(Builder $query, string $searchNote): void
    {
        $query->whereHas('notes', function (Builder $noteQuery) use ($searchNote): void {
            $noteQuery->withTrashed()->where(function (Builder $searchQuery) use ($searchNote): void {
                $pattern = "%{$searchNote}%";

                $searchQuery
                    ->where('remark', 'like', $pattern)
                    ->orWhereHas(
                        'noteType',
                        fn (Builder $noteTypeQuery) => $noteTypeQuery->where('note', 'like', $pattern)
                    );
            });
        });
    }
}
