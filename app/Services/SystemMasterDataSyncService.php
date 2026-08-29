<?php

namespace App\Services;

use App\Models\Sync;
use App\Models\SystemDepartment;
use App\Models\SystemFaculty;
use App\Models\SystemTeacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SystemMasterDataSyncService
{
    public function __construct(
        private readonly PersonnelApiService $personnelApiService
    ) {}

    /**
     * API paths:
     * POST /api/system-faculties/sync
     * POST /api/system-departments/sync
     * POST /api/system-teachers/sync
     *
     * Sync types:
     * 1 = system faculties
     * 2 = system departments
     * 3 = system teachers
     */
    public function sync(int $syncType, string $actor): Sync
    {
        $sync = Sync::start($syncType);

        try {
            $items = $this->fetchItems($syncType);

            return DB::transaction(function () use ($sync, $syncType, $items, $actor): Sync {
                [$synced, $deleted] = match ($syncType) {
                    Sync::TYPE_SYSTEM_FACULTY => $this->syncFaculties($items, $sync, $actor),
                    Sync::TYPE_SYSTEM_DEPARTMENT => $this->syncDepartments($items, $sync, $actor),
                    Sync::TYPE_SYSTEM_TEACHER => $this->syncTeachers($items, $sync, $actor),
                    default => throw new InvalidArgumentException('Unsupported sync type'),
                };

                $skipped = max(count($items) - $synced, 0);

                return $sync->markAsSuccess($synced, $deleted, $skipped);
            });
        } catch (Throwable $exception) {
            try {
                $sync->markAsFailed($exception->getMessage());
            } catch (Throwable) {
                // Keep the original sync error for the API response.
            }

            throw $exception;
        }
    }

    private function fetchItems(int $syncType): array
    {
        return match ($syncType) {
            Sync::TYPE_SYSTEM_FACULTY => $this->facultyItems(),
            Sync::TYPE_SYSTEM_DEPARTMENT => $this->departmentItems(),
            Sync::TYPE_SYSTEM_TEACHER => $this->teacherItems(),
            default => throw new InvalidArgumentException('Unsupported sync type'),
        };
    }

    private function facultyItems(): array
    {
        $response = $this->personnelApiService->getFaculties();

        return $response['faculties'] ?? $response;
    }

    private function departmentItems(): array
    {
        $response = $this->personnelApiService->getDepartments();

        return $response['departments'] ?? $response;
    }

    private function teacherItems(): array
    {
        $response = $this->personnelApiService->getSystemTeachers();

        return $response['users'] ?? $response;
    }

    private function syncFaculties(array $faculties, Sync $sync, string $actor): array
    {
        $synced = 0;
        $activeFacultyIds = [];

        foreach ($faculties as $faculty) {
            if (empty($faculty['id']) || empty($faculty['th_name'])) {
                continue;
            }

            $facultyId = (int) $faculty['id'];
            $activeFacultyIds[] = $facultyId;

            $model = SystemFaculty::withTrashed()->whereKey($facultyId)->first()
                ?? new SystemFaculty;

            if (! $model->exists) {
                $model->setAttribute('id', $facultyId);
            }

            $this->saveModel($model, [
                'th_name' => $faculty['th_name'],
                'en_name' => $faculty['en_name'] ?? '-',
                'th_short_name' => $faculty['th_short_name'] ?? '-',
                'en_short_name' => $faculty['en_short_name'] ?? '-',
            ], $sync, $actor);

            $synced++;
        }

        return [
            $synced,
            $this->softDeleteMissing(
                SystemFaculty::class,
                'id',
                $activeFacultyIds,
                $sync,
                $actor
            ),
        ];
    }

    private function syncDepartments(array $departments, Sync $sync, string $actor): array
    {
        $synced = 0;
        $activeDepartmentIds = [];
        $existingFacultyIds = $this->existingIds(
            SystemFaculty::class,
            collect($departments)->pluck('system_faculty_id')->all()
        );

        foreach ($departments as $department) {
            if (empty($department['id']) || empty($department['th_name'])) {
                continue;
            }

            $systemFacultyId = $this->positiveInteger($department['system_faculty_id'] ?? null);

            if ($systemFacultyId === null || ! isset($existingFacultyIds[$systemFacultyId])) {
                continue;
            }

            $departmentId = (int) $department['id'];
            $activeDepartmentIds[] = $departmentId;

            $model = SystemDepartment::withTrashed()->whereKey($departmentId)->first()
                ?? new SystemDepartment;

            if (! $model->exists) {
                $model->setAttribute('id', $departmentId);
            }

            $this->saveModel($model, [
                'th_name' => $department['th_name'],
                'en_name' => $department['en_name'] ?? '-',
                'th_short_name' => $department['th_short_name'] ?? '-',
                'en_short_name' => $department['en_short_name'] ?? '-',
                'system_faculty_id' => $systemFacultyId,
            ], $sync, $actor);

            $synced++;
        }

        return [
            $synced,
            $this->softDeleteMissing(
                SystemDepartment::class,
                'id',
                $activeDepartmentIds,
                $sync,
                $actor
            ),
        ];
    }

    private function syncTeachers(array $teachers, Sync $sync, string $actor): array
    {
        $synced = 0;
        $activeNontriIds = [];
        $existingDepartmentIds = $this->existingIds(
            SystemDepartment::class,
            collect($teachers)->pluck('department_id')->all()
        );

        foreach ($teachers as $teacher) {
            if (empty($teacher['nontri_id']) || empty($teacher['full_name'])) {
                continue;
            }

            $departmentId = $this->positiveInteger($teacher['department_id'] ?? null);

            if ($departmentId === null || ! isset($existingDepartmentIds[$departmentId])) {
                continue;
            }

            $nontriId = trim($teacher['nontri_id']);
            $activeNontriIds[] = $nontriId;

            $model = SystemTeacher::withTrashed()
                ->where('nontri_id', $nontriId)
                ->first() ?? new SystemTeacher;

            $this->saveModel($model, [
                'nontri_id' => $nontriId,
                'full_name_th' => trim($teacher['full_name']),
                'department_id' => $departmentId,
            ], $sync, $actor);

            $synced++;
        }

        return [
            $synced,
            $this->softDeleteMissing(
                SystemTeacher::class,
                'nontri_id',
                $activeNontriIds,
                $sync,
                $actor
            ),
        ];
    }

    private function saveModel(Model $model, array $attributes, Sync $sync, string $actor): void
    {
        if (! $model->exists) {
            $model->setAttribute('created_by', $actor);
        }

        $model->fill([
            ...$attributes,
            'updated_by' => $actor,
            'deleted_at' => null,
            'deleted_by' => '',
            'sync_id' => $sync->getKey(),
        ])->save();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function softDeleteMissing(
        string $modelClass,
        string $key,
        array $activeValues,
        Sync $sync,
        string $actor
    ): int {
        if ($activeValues === []) {
            return 0;
        }

        return $modelClass::query()
            ->whereNotIn($key, array_values(array_unique($activeValues)))
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $actor,
                'updated_by' => $actor,
                'sync_id' => $sync->getKey(),
            ]);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function existingIds(string $modelClass, array $values): array
    {
        $ids = collect($values)
            ->map(fn ($value) => $this->positiveInteger($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $modelClass::query()
            ->whereIn('id', $ids, 'and', false)
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
