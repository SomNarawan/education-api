<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Http\Responses\SystemDepartmentResponse;
use App\Models\Sync;
use App\Models\SystemDepartment;
use App\Models\SystemFaculty;
use App\Services\PersonnelApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SystemDepartmentController extends Controller
{
    /**
     * API: GET /api/system-departments
     */
    public function index(): JsonResponse
    {
        $items = SystemDepartment::query()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemDepartmentResponse::collection($items)->resolve(),
            'Load system departments successfully'
        );
    }

    /**
     * API: GET /api/system-departments/all
     */
    public function all(): JsonResponse
    {
        $items = SystemDepartment::withTrashed()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemDepartmentResponse::collection($items)->resolve(),
            'Load all system departments successfully'
        );
    }

    /**
     * API: POST /api/system-departments/sync
     */
    public function sync(
        Request $request,
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        $sync = null;

        try {
            $sync = Sync::start(Sync::TYPE_SYSTEM_DEPARTMENT);

            $response = $personnelApiService->getDepartments();

            $departments = $response['departments'] ?? $response;

            $synced = 0;
            $deleted = 0;
            $activeDepartmentIds = [];
            $actor = $this->actorFromJwt($request) ?? 'system';

            $facultyIds = collect($departments)
                ->pluck('system_faculty_id')
                ->filter(fn ($facultyId) => is_numeric($facultyId) && (int) $facultyId > 0)
                ->map(fn ($facultyId) => (int) $facultyId)
                ->unique()
                ->values()
                ->all();

            $existingFacultyIds = SystemFaculty::query()
                ->where('deleted_at', null)
                ->whereIn('id', $facultyIds, 'and', false)
                ->pluck('id')
                ->mapWithKeys(fn ($facultyId) => [(int) $facultyId => true])
                ->all();

            foreach ($departments as $department) {
                if (
                    empty($department['id']) ||
                    empty($department['th_name'])
                ) {
                    continue;
                }

                if (
                    empty($department['system_faculty_id']) ||
                    ! is_numeric($department['system_faculty_id']) ||
                    (int) $department['system_faculty_id'] <= 0
                ) {
                    continue;
                }

                $departmentId = (int) $department['id'];
                $systemFacultyId = (int) $department['system_faculty_id'];

                if (! isset($existingFacultyIds[$systemFacultyId])) {
                    continue;
                }

                $activeDepartmentIds[] = $departmentId;

                $model = SystemDepartment::withTrashed()->whereKey($departmentId)->first()
                    ?? new SystemDepartment;

                if (! $model->exists) {
                    $model->setAttribute('id', $departmentId);
                    $model->setAttribute('created_by', $actor);
                }

                $model->fill([
                    'th_name' => $department['th_name'],
                    'en_name' => $department['en_name'] ?? '-',
                    'th_short_name' => $department['th_short_name'] ?? '-',
                    'en_short_name' => $department['en_short_name'] ?? '-',
                    'system_faculty_id' => $systemFacultyId,
                    'updated_by' => $actor,
                    'deleted_at' => null,
                    'deleted_by' => '',
                    'sync_id' => $sync->getKey(),
                ])->save();

                $synced++;
            }

            if (! empty($activeDepartmentIds)) {
                $deleted = SystemDepartment::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('id', $activeDepartmentIds)
                    ->update([
                        'deleted_at' => now(),
                        'deleted_by' => $actor,
                        'updated_by' => $actor,
                        'sync_id' => $sync->getKey(),
                    ]);
            }

            $skipped = max(count($departments) - $synced, 0);
            $sync->markAsSuccess($synced, $deleted, $skipped);

            return ApiResponse::success(
                new SyncResponse($sync),
                'Sync successfully'
            );
        } catch (Throwable $e) {
            if ($sync !== null) {
                try {
                    $sync->markAsFailed($e->getMessage());
                } catch (Throwable) {
                    // Keep the original sync error in the API response.
                }
            }

            return ApiResponse::error(
                $e->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }
    }
}
