<?php

namespace App\Services\Students;

use App\Models\CurriculumPlan;
use App\Models\Department;
use App\Models\SystemDepartment;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StudentDepartmentResolver
{
    public function resolve(array $attributes): int
    {
        if (isset($attributes['department_id'])) {
            return (int) $attributes['department_id'];
        }

        if (isset($attributes['teacher_id'])) {
            $teacherDepartmentId = Teacher::query()
                ->whereKey($attributes['teacher_id'])
                ->value('department_id');

            if ($teacherDepartmentId !== null) {
                return (int) $teacherDepartmentId;
            }
        }

        $curriculumDepartmentId = CurriculumPlan::query()
            ->whereKey($attributes['study_plan_id'])
            ->with('curriculum:id,department_id')
            ->first()
            ?->curriculum
            ?->department_id;

        if ($curriculumDepartmentId !== null) {
            $mappedDepartmentId = $this->mappedDepartmentId((int) $curriculumDepartmentId);

            if ($mappedDepartmentId !== null) {
                return $mappedDepartmentId;
            }

            $matchedDepartmentId = $this->matchDepartmentByName((int) $curriculumDepartmentId);

            if ($matchedDepartmentId !== null) {
                return $matchedDepartmentId;
            }
        }

        throw ValidationException::withMessages([
            'department_id' => 'Unable to determine the system department from the selected study plan.',
        ]);
    }

    private function mappedDepartmentId(int $curriculumDepartmentId): ?int
    {
        if (! Schema::hasTable('departments_map')) {
            return null;
        }

        $mappedId = DB::table('departments_map')
            ->where('id_in', $curriculumDepartmentId)
            ->value('id_out');

        if ($mappedId === null) {
            return null;
        }

        return SystemDepartment::query()
            ->whereKey($mappedId)
            ->exists()
                ? (int) $mappedId
                : null;
    }

    private function matchDepartmentByName(int $curriculumDepartmentId): ?int
    {
        $name = Department::query()
            ->whereKey($curriculumDepartmentId)
            ->value('name_th');

        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $normalizedName = $this->normalizeName($name);

        return SystemDepartment::query()
            ->get(['id', 'th_name'])
            ->first(
                fn (SystemDepartment $department) => $this->normalizeName($department->th_name) === $normalizedName
            )
            ?->id;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/^ภาควิชา/u', '', trim($name)) ?? trim($name);
    }
}
