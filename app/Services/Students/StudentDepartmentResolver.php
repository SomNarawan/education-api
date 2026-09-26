<?php

namespace App\Services\Students;

use App\Contracts\CmisApi;
use App\Models\SystemDepartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StudentDepartmentResolver
{
    public function __construct(private readonly CmisApi $cmisApi) {}

    public function resolve(array $attributes): int
    {
        if (isset($attributes['system_department_id'])) {
            return (int) $attributes['system_department_id'];
        }

        $payload = $this->cmisApi->getCurriculumCategories((int) $attributes['study_plan_id']);
        $curriculumDepartmentId = data_get($payload, 'meta.study_plan.department_id')
            ?? data_get($payload, 'meta.curriculum.department_id')
            ?? data_get($payload, 'meta.curriculum.department.id');

        if ($curriculumDepartmentId !== null) {
            $mappedDepartmentId = $this->mappedDepartmentId((int) $curriculumDepartmentId);

            if ($mappedDepartmentId !== null) {
                return $mappedDepartmentId;
            }

            $matchedDepartmentId = $this->matchDepartmentByName(
                data_get($payload, 'meta.study_plan.department_name_th')
                    ?? data_get($payload, 'meta.curriculum.department.name_th')
                    ?? ''
            );

            if ($matchedDepartmentId !== null) {
                return $matchedDepartmentId;
            }
        }

        throw ValidationException::withMessages([
            'system_department_id' => 'Unable to determine the system department from the selected study plan.',
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

    private function matchDepartmentByName(string $name): ?int
    {
        if (trim($name) === '') {
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
