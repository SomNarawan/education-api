<?php

namespace App\Services;

use App\Models\SystemDepartment;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Name, department and faculty of a staff member at portal login, from the
 * personnel API (portal-student-map/all-user: lecturers and support staff).
 * Its department_id is the id system_departments is synced with. What cannot
 * be found stays null: a personnel API outage must not block the login.
 */
class StaffProfileResolver
{
    public function __construct(private readonly PersonnelApiService $personnel) {}

    /**
     * @return array{name: string, department_id: int|null, faculty_id: int|null}
     */
    public function resolve(string $nontriId): array
    {
        $profile = ['name' => $nontriId, 'department_id' => null, 'faculty_id' => null];

        try {
            // Not the 30 s of the master data sync: the user is waiting.
            $user = collect($this->personnel->getUsers((int) config('portal_auth.timeout', 10)))
                ->first(fn (array $user): bool => strcasecmp($user['nontri_id'], $nontriId) === 0);
        } catch (Throwable $exception) {
            // Throwable: a body that is not a JSON object makes get() fail on its return type.
            Log::warning("Portal login of {$nontriId}: personnel lookup failed: {$exception->getMessage()}");

            return $profile;
        }

        if (! $user) {
            return $profile;
        }

        $profile['name'] = $user['full_name'];

        if ($user['department_id'] === null) {
            return $profile;
        }

        $department = SystemDepartment::query()->find($user['department_id']);

        if (! $department) {
            Log::warning("Portal login of {$nontriId}: no active system department with id {$user['department_id']}");

            return $profile;
        }

        $profile['department_id'] = $department->id;
        $profile['faculty_id'] = $department->system_faculty_id;

        return $profile;
    }
}
