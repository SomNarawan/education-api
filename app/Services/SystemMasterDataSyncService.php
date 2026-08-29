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
        return $this->syncRecords(
            $faculties,
            SystemFaculty::class,
            'id',
            static function (mixed $faculty): ?array {
                if (! is_array($faculty) || empty($faculty['id']) || empty($faculty['th_name'])) {
                    return null;
                }

                return [(int) $faculty['id'], [
                    'th_name' => $faculty['th_name'],
                    'en_name' => $faculty['en_name'] ?? '-',
                    'th_short_name' => $faculty['th_short_name'] ?? '-',
                    'en_short_name' => $faculty['en_short_name'] ?? '-',
                ]];
            },
            $sync,
            $actor,
            'Personnel API returned faculties, but none could be mapped.'
        );
    }

    private function syncDepartments(array $departments, Sync $sync, string $actor): array
    {
        $existingFacultyIds = $this->existingIds(
            SystemFaculty::class,
            collect($departments)->pluck('system_faculty_id')->all()
        );

        return $this->syncRecords(
            $departments,
            SystemDepartment::class,
            'id',
            function (mixed $department) use ($existingFacultyIds): ?array {
                if (! is_array($department) || empty($department['id']) || empty($department['th_name'])) {
                    return null;
                }

                $facultyId = $this->positiveInteger($department['system_faculty_id'] ?? null);

                if ($facultyId === null || ! isset($existingFacultyIds[$facultyId])) {
                    return null;
                }

                return [(int) $department['id'], [
                    'th_name' => $department['th_name'],
                    'en_name' => $department['en_name'] ?? '-',
                    'th_short_name' => $department['th_short_name'] ?? '-',
                    'en_short_name' => $department['en_short_name'] ?? '-',
                    'system_faculty_id' => $facultyId,
                ]];
            },
            $sync,
            $actor,
            'Personnel API returned departments, but none could be mapped to an active system faculty.'
        );
    }

    private function syncTeachers(array $teachers, Sync $sync, string $actor): array
    {
        $existingDepartmentIds = $this->existingIds(
            SystemDepartment::class,
            collect($teachers)->pluck('department_id')->all()
        );

        return $this->syncRecords(
            $teachers,
            SystemTeacher::class,
            'nontri_id',
            function (mixed $teacher) use ($existingDepartmentIds): ?array {
                if (! is_array($teacher) || empty($teacher['nontri_id']) || empty($teacher['full_name'])) {
                    return null;
                }

                $departmentId = $this->positiveInteger($teacher['department_id'] ?? null);

                if ($departmentId === null || ! isset($existingDepartmentIds[$departmentId])) {
                    return null;
                }

                $nontriId = trim($teacher['nontri_id']);

                return [$nontriId, [
                    'full_name_th' => trim($teacher['full_name']),
                    'department_id' => $departmentId,
                ]];
            },
            $sync,
            $actor,
            'Personnel API returned system teachers, but none could be mapped to an active system department.'
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(mixed): ?array{0: int|string, 1: array<string, mixed>}  $mapItem
     */
    private function syncRecords(
        array $items,
        string $modelClass,
        string $keyColumn,
        callable $mapItem,
        Sync $sync,
        string $actor,
        string $emptyMessage
    ): array {
        $counts = $this->emptyCounts();
        $records = [];

        foreach ($items as $item) {
            $mapped = $mapItem($item);

            if ($mapped === null) {
                $counts['skipped']++;

                continue;
            }

            [$key, $attributes] = $mapped;
            $index = (string) $key;

            if (array_key_exists($index, $records)) {
                $counts['skipped']++;
            }

            $records[$index] = compact('key', 'attributes');
        }

        if ($items !== [] && $records === []) {
            throw new UnexpectedValueException($emptyMessage);
        }

        foreach ($records as ['key' => $key, 'attributes' => $attributes]) {
            $model = $modelClass::withInactive()->where($keyColumn, $key)->first()
                ?? new $modelClass;

            if (! $model->exists) {
                $model->setAttribute($keyColumn, $key);
            }

            $counts[$this->saveModel($model, $attributes, $sync, $actor)]++;
        }

        $counts['inactivated'] = $this->deactivateMissing(
            $modelClass,
            $keyColumn,
            array_column($records, 'key'),
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
