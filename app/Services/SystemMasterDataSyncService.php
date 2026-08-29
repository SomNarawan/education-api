<?php

namespace App\Services;

use App\Constants\Status;
use App\Models\Sync;
use App\Models\SystemDepartment;
use App\Models\SystemFaculty;
use App\Models\SystemTeacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

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
        $sync = Sync::start($syncType, $actor);

        try {
            $items = $this->fetchItems($syncType);

            return DB::transaction(function () use ($sync, $syncType, $items, $actor): Sync {
                $counts = match ($syncType) {
                    Sync::TYPE_SYSTEM_FACULTY => $this->syncFaculties($items, $sync, $actor),
                    Sync::TYPE_SYSTEM_DEPARTMENT => $this->syncDepartments($items, $sync, $actor),
                    Sync::TYPE_SYSTEM_TEACHER => $this->syncTeachers($items, $sync, $actor),
                    default => throw new InvalidArgumentException('Unsupported sync type'),
                };

                return $sync->markAsSuccess(
                    $counts['inserted'],
                    $counts['updated'],
                    $counts['inactivated'],
                    $counts['skipped'],
                    $actor
                );
            });
        } catch (Throwable $exception) {
            try {
                $sync->markAsFailed($exception->getMessage(), $actor);
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

        $items = $response['departments'] ?? $response;
        $departments = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! array_key_exists('departments', $item)) {
                $departments[] = $item;

                continue;
            }

            if (! is_array($item['departments'])) {
                continue;
            }

            $facultyId = $item['faculty_id'] ?? $item['system_faculty_id'] ?? null;

            foreach ($item['departments'] as $department) {
                if (! is_array($department)) {
                    continue;
                }

                $departments[] = [
                    ...$department,
                    'system_faculty_id' => $department['system_faculty_id']
                        ?? $department['faculty_id']
                        ?? $facultyId,
                ];
            }
        }

        return $departments;
    }

    private function teacherItems(): array
    {
        $response = $this->personnelApiService->getSystemTeachers();

        return $response['users'] ?? $response;
    }

    private function syncFaculties(array $faculties, Sync $sync, string $actor): array
    {
        $counts = $this->emptyCounts();
        $validFaculties = [];

        foreach ($faculties as $faculty) {
            if (! is_array($faculty) || empty($faculty['id']) || empty($faculty['th_name'])) {
                $counts['skipped']++;

                continue;
            }

            $facultyId = (int) $faculty['id'];

            if (array_key_exists($facultyId, $validFaculties)) {
                $counts['skipped']++;
            }

            $validFaculties[$facultyId] = [
                'th_name' => $faculty['th_name'],
                'en_name' => $faculty['en_name'] ?? '-',
                'th_short_name' => $faculty['th_short_name'] ?? '-',
                'en_short_name' => $faculty['en_short_name'] ?? '-',
            ];
        }

        foreach ($validFaculties as $facultyId => $attributes) {

            $model = SystemFaculty::withInactive()->whereKey($facultyId)->first()
                ?? new SystemFaculty;

            if (! $model->exists) {
                $model->setAttribute('id', $facultyId);
            }

            $result = $this->saveModel($model, $attributes, $sync, $actor);

            $counts[$result]++;
        }

        if ($faculties !== [] && $validFaculties === []) {
            throw new UnexpectedValueException(
                'Personnel API returned faculties, but none could be mapped.'
            );
        }

        $counts['inactivated'] = $this->deactivateMissing(
            SystemFaculty::class,
            'id',
            array_keys($validFaculties),
            $sync,
            $actor
        );

        return $counts;
    }

    private function syncDepartments(array $departments, Sync $sync, string $actor): array
    {
        $counts = $this->emptyCounts();
        $validDepartments = [];
        $existingFacultyIds = $this->existingIds(
            SystemFaculty::class,
            collect($departments)->pluck('system_faculty_id')->all()
        );

        foreach ($departments as $department) {
            if (empty($department['id']) || empty($department['th_name'])) {
                $counts['skipped']++;

                continue;
            }

            $systemFacultyId = $this->positiveInteger($department['system_faculty_id'] ?? null);

            if ($systemFacultyId === null || ! isset($existingFacultyIds[$systemFacultyId])) {
                $counts['skipped']++;

                continue;
            }

            $departmentId = (int) $department['id'];

            if (array_key_exists($departmentId, $validDepartments)) {
                $counts['skipped']++;
            }

            $validDepartments[$departmentId] = [
                'th_name' => $department['th_name'],
                'en_name' => $department['en_name'] ?? '-',
                'th_short_name' => $department['th_short_name'] ?? '-',
                'en_short_name' => $department['en_short_name'] ?? '-',
                'system_faculty_id' => $systemFacultyId,
            ];
        }

        foreach ($validDepartments as $departmentId => $attributes) {

            $model = SystemDepartment::withInactive()->whereKey($departmentId)->first()
                ?? new SystemDepartment;

            if (! $model->exists) {
                $model->setAttribute('id', $departmentId);
            }

            $result = $this->saveModel($model, $attributes, $sync, $actor);

            $counts[$result]++;
        }

        if ($departments !== [] && $validDepartments === []) {
            throw new UnexpectedValueException(
                'Personnel API returned departments, but none could be mapped to an active system faculty.'
            );
        }

        $counts['inactivated'] = $this->deactivateMissing(
            SystemDepartment::class,
            'id',
            array_keys($validDepartments),
            $sync,
            $actor
        );

        return $counts;
    }

    private function syncTeachers(array $teachers, Sync $sync, string $actor): array
    {
        $counts = $this->emptyCounts();
        $validTeachers = [];
        $existingDepartmentIds = $this->existingIds(
            SystemDepartment::class,
            collect($teachers)->pluck('department_id')->all()
        );

        foreach ($teachers as $teacher) {
            if (empty($teacher['nontri_id']) || empty($teacher['full_name'])) {
                $counts['skipped']++;

                continue;
            }

            $departmentId = $this->positiveInteger($teacher['department_id'] ?? null);

            if ($departmentId === null || ! isset($existingDepartmentIds[$departmentId])) {
                $counts['skipped']++;

                continue;
            }

            $nontriId = trim($teacher['nontri_id']);

            if (array_key_exists($nontriId, $validTeachers)) {
                $counts['skipped']++;
            }

            $validTeachers[$nontriId] = [
                'nontri_id' => $nontriId,
                'full_name_th' => trim($teacher['full_name']),
                'department_id' => $departmentId,
            ];
        }

        foreach ($validTeachers as $nontriId => $attributes) {
            $model = SystemTeacher::withInactive()
                ->where('nontri_id', $nontriId)
                ->first() ?? new SystemTeacher;

            $result = $this->saveModel($model, $attributes, $sync, $actor);

            $counts[$result]++;
        }

        if ($teachers !== [] && $validTeachers === []) {
            throw new UnexpectedValueException(
                'Personnel API returned system teachers, but none could be mapped to an active system department.'
            );
        }

        $counts['inactivated'] = $this->deactivateMissing(
            SystemTeacher::class,
            'nontri_id',
            array_keys($validTeachers),
            $sync,
            $actor
        );

        return $counts;
    }

    private function saveModel(Model $model, array $attributes, Sync $sync, string $actor): string
    {
        $isInsert = ! $model->exists;

        $model->fill([
            ...$attributes,
            'status' => Status::ACTIVE,
        ]);

        if (! $isInsert && ! $model->isDirty()) {
            return 'skipped';
        }

        if ($isInsert) {
            $model->setAttribute('created_by', $actor);
        }

        $model->fill([
            'updated_by' => $actor,
            'sync_id' => $sync->getKey(),
        ])->save();

        return $isInsert ? 'inserted' : 'updated';
    }

    private function emptyCounts(): array
    {
        return [
            'inserted' => 0,
            'updated' => 0,
            'inactivated' => 0,
            'skipped' => 0,
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deactivateMissing(
        string $modelClass,
        string $key,
        array $activeValues,
        Sync $sync,
        string $actor
    ): int {
        return $modelClass::query()
            ->whereNotIn($key, array_values(array_unique($activeValues)))
            ->update([
                'status' => Status::INACTIVE,
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
