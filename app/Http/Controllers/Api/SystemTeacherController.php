<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Http\Responses\SystemTeacherListResponse;
use App\Models\Sync;
use App\Models\SystemDepartment;
use App\Models\SystemTeacher;
use App\Services\PersonnelApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SystemTeacherController extends Controller
{
    /**
     * API: GET /api/system-teachers?department_id={id}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $items = SystemTeacher::query()
            ->when(
                isset($validated['department_id']),
                fn ($query) => $query->where('department_id', $validated['department_id'])
            )
            ->orderBy('full_name_th')
            ->orderBy('id')
            ->get();

        $data = SystemTeacherListResponse::collection($items);

        return ApiResponse::success($data, 'Load system teachers successfully');
    }

    /**
     * API: GET /api/system-teachers/all?department_id={id}
     */
    public function all(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $items = SystemTeacher::withTrashed()
            ->when(
                isset($validated['department_id']),
                fn ($query) => $query->where('department_id', $validated['department_id'])
            )
            ->orderBy('full_name_th')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemTeacherListResponse::collection($items),
            'Load all system teachers successfully'
        );
    }

    /**
     * API: POST /api/system-teachers/sync
     */
    public function sync(
        Request $request,
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        $sync = null;

        try {
            $sync = Sync::start(Sync::TYPE_SYSTEM_TEACHER);

            $response = $personnelApiService->getSystemTeachers();
            $systemTeachers = $response['users'] ?? $response;

            $synced = 0;
            $deleted = 0;
            $activeNontriIds = [];
            $actor = $this->actorFromJwt($request) ?? 'system';

            $departmentIds = collect($systemTeachers)
                ->pluck('department_id')
                ->filter(fn ($departmentId) => is_numeric($departmentId) && (int) $departmentId > 0)
                ->map(fn ($departmentId) => (int) $departmentId)
                ->unique()
                ->values()
                ->all();

            $existingDepartmentIds = SystemDepartment::query()
                ->whereNull('deleted_at')
                ->whereIn('id', $departmentIds, 'and', false)
                ->pluck('id')
                ->mapWithKeys(fn ($departmentId) => [(int) $departmentId => true])
                ->all();

            foreach ($systemTeachers as $systemTeacher) {
                if (
                    empty($systemTeacher['nontri_id']) ||
                    empty($systemTeacher['full_name'])
                ) {
                    continue;
                }

                if (
                    ! isset($systemTeacher['department_id']) ||
                    ! is_numeric($systemTeacher['department_id']) ||
                    (int) $systemTeacher['department_id'] <= 0
                ) {
                    continue;
                }

                $departmentId = (int) $systemTeacher['department_id'];

                if (! isset($existingDepartmentIds[$departmentId])) {
                    continue;
                }

                $nontriId = trim($systemTeacher['nontri_id']);
                $fullName = trim($systemTeacher['full_name']);

                $activeNontriIds[] = $nontriId;

                $model = SystemTeacher::withTrashed()
                    ->where('nontri_id', $nontriId)
                    ->first() ?? new SystemTeacher;

                if (! $model->exists) {
                    $model->setAttribute('created_by', $actor);
                }

                $model->fill([
                    'nontri_id' => $nontriId,
                    'full_name_th' => $fullName,
                    'department_id' => $departmentId,
                    'updated_by' => $actor,

                    // ถ้าเคยถูกลบ แล้ว API ส่งกลับมาอีก ให้กลับมาใช้งาน
                    'deleted_at' => null,
                    'deleted_by' => '',
                    'sync_id' => $sync->getKey(),
                ])->save();

                $synced++;
            }

            if (! empty($activeNontriIds)) {
                $deleted = SystemTeacher::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('nontri_id', $activeNontriIds)
                    ->update([
                        'deleted_at' => now(),
                        'deleted_by' => $actor,
                        'updated_by' => $actor,
                        'sync_id' => $sync->getKey(),
                    ]);
            }

            $skipped = max(count($systemTeachers) - $synced, 0);
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
