<?php

namespace App\Services;

use App\Models\Sync;
use App\Models\SystemDepartment;
use App\Models\SystemFaculty;
use App\Services\SystemMasterData\SystemMasterDataRecordSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SystemMasterDataSyncService
{
    public function __construct(
        private readonly PersonnelApiService $personnelApiService,
        private readonly SystemMasterDataRecordSynchronizer $recordSynchronizer,
        private readonly SyncResultSummary $resultSummary,
    ) {}

    /**
     * API paths:
     * POST /api/system-faculties/sync
     * POST /api/system-departments/sync
     *
     * Sync types:
     * 1 = system faculties
     * 2 = system departments
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
                };

                $result = $this->resultSummary->systemMasterData(
                    $counts['skip_reasons']
                );
                $sync = $sync->markAsCompleted(
                    $counts['inserted'],
                    $counts['updated'],
                    $counts['inactivated'],
                    $counts['skipped'],
                    $actor,
                    $result['status'],
                    $result['error_message'],
                );

                $sync->setAttribute(
                    'skip_reasons',
                    $counts['skip_reasons']
                );

                return $sync;
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
                $departments[] = $item;

                continue;
            }

            if (! array_key_exists('departments', $item)) {
                $departments[] = $item;

                continue;
            }

            if (! is_array($item['departments'])) {
                $departments[] = $item;

                continue;
            }

            $facultyId = $item['faculty_id'] ?? $item['system_faculty_id'] ?? null;

            foreach ($item['departments'] as $department) {
                if (! is_array($department)) {
                    $departments[] = $department;

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

    private function syncFaculties(array $faculties, Sync $sync, string $actor): array
    {
        return $this->recordSynchronizer->sync(
            $faculties,
            SystemFaculty::class,
            'id',
            static function (mixed $faculty): array {
                if (! is_array($faculty) || empty($faculty['id']) || empty($faculty['th_name'])) {
                    return ['skip_reason' => 'invalid_source_data'];
                }

                return [
                    'key' => (int) $faculty['id'],
                    'attributes' => [
                        'th_name' => $faculty['th_name'],
                        'en_name' => $faculty['en_name'] ?? '-',
                        'th_short_name' => $faculty['th_short_name'] ?? '-',
                        'en_short_name' => $faculty['en_short_name'] ?? '-',
                    ],
                ];
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
            collect($departments)
                ->filter(fn (mixed $department): bool => is_array($department))
                ->pluck('system_faculty_id')
                ->all()
        );

        return $this->recordSynchronizer->sync(
            $departments,
            SystemDepartment::class,
            'id',
            function (mixed $department) use ($existingFacultyIds): array {
                if (! is_array($department) || empty($department['id']) || empty($department['th_name'])) {
                    return ['skip_reason' => 'invalid_source_data'];
                }

                $facultyId = $this->positiveInteger($department['system_faculty_id'] ?? null);

                if ($facultyId === null || ! isset($existingFacultyIds[$facultyId])) {
                    return ['skip_reason' => 'faculty_not_found'];
                }

                return [
                    'key' => (int) $department['id'],
                    'attributes' => [
                        'th_name' => $department['th_name'],
                        'en_name' => $department['en_name'] ?? '-',
                        'th_short_name' => $department['th_short_name'] ?? '-',
                        'en_short_name' => $department['en_short_name'] ?? '-',
                        'system_faculty_id' => $facultyId,
                    ],
                ];
            },
            $sync,
            $actor,
            'Personnel API returned departments, but none could be mapped to an active system faculty.'
        );
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
