<?php

namespace App\Services;

use App\Contracts\TeacherApi;
use App\Models\SystemDepartment;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Name, department and faculty of a staff member at portal login. The CMIS
 * teacher list gives only department_name, which is matched to
 * system_departments.th_name. What cannot be found stays null: a CMIS outage
 * must not block the login.
 */
class StaffProfileResolver
{
    public function __construct(private readonly TeacherApi $teachers) {}

    /**
     * @return array{name: string, department_id: int|null, faculty_id: int|null}
     */
    public function resolve(string $nontriId): array
    {
        $profile = ['name' => $nontriId, 'department_id' => null, 'faculty_id' => null];

        try {
            $teacher = collect($this->teachers->getTeachers())
                ->first(fn (array $teacher): bool => strcasecmp($teacher['nontri_id'], $nontriId) === 0);
        } catch (RuntimeException $exception) {
            Log::warning("Portal login of {$nontriId}: teacher lookup failed: {$exception->getMessage()}");

            return $profile;
        }

        if (! $teacher) {
            return $profile;
        }

        $profile['name'] = $teacher['full_name_th'];
        $departmentName = trim((string) $teacher['department_name']);

        if ($departmentName === '') {
            return $profile;
        }

        $department = SystemDepartment::query()->where('th_name', $departmentName)->first();

        if (! $department) {
            Log::warning("Portal login of {$nontriId}: no active system department named {$departmentName}");

            return $profile;
        }

        $profile['department_id'] = $department->id;
        $profile['faculty_id'] = $department->system_faculty_id;

        return $profile;
    }
}
