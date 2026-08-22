<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Http\Responses\TeacherListResponse;
use App\Models\Sync;
use App\Models\SystemDepartment;
use App\Models\Teacher;
use App\Services\PersonnelApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TeacherController extends Controller
{
    /**
     * API: GET /api/teachers?department_id={id}&include_deleted={boolean}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['sometimes', 'integer', 'min:1'],
            'include_deleted' => ['sometimes', 'in:true,false,1,0'],
        ]);

        $items = Teacher::query()
            ->when(
                $request->boolean('include_deleted'),
                fn ($query) => $query->withTrashed()
            )
            ->when(
                isset($validated['department_id']),
                fn ($query) => $query->where('department_id', $validated['department_id'])
            )
            ->orderBy('full_name_th')
            ->orderBy('id')
            ->get();

        $data = TeacherListResponse::collection($items);

        return ApiResponse::success($data, 'Load teachers successfully');
    }

    /**
     * API: POST /api/teachers/sync
     */
    public function sync(
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        $sync = null;

        try {
            $sync = Sync::start(Sync::TYPE_TEACHER);

            $response = $personnelApiService->getTeachers();
            $teachers = $response['users'] ?? $response;

            $synced = 0;
            $deleted = 0;
            $activeNontriIds = [];

            $departmentIds = collect($teachers)
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

            foreach ($teachers as $teacher) {
                if (
                    empty($teacher['nontri_id']) ||
                    empty($teacher['full_name'])
                ) {
                    continue;
                }

                if (
                    ! isset($teacher['department_id']) ||
                    ! is_numeric($teacher['department_id']) ||
                    (int) $teacher['department_id'] <= 0
                ) {
                    continue;
                }

                $departmentId = (int) $teacher['department_id'];

                if (! isset($existingDepartmentIds[$departmentId])) {
                    continue;
                }

                $nontriId = trim($teacher['nontri_id']);
                $fullName = trim($teacher['full_name']);

                $activeNontriIds[] = $nontriId;

                Teacher::updateOrCreate(
                    [
                        'nontri_id' => $nontriId,
                    ],
                    [
                        'full_name_th' => $fullName,
                        'department_id' => $departmentId,

                        // ถ้าเคยถูกลบ แล้ว API ส่งกลับมาอีก ให้กลับมาใช้งาน
                        'deleted_at' => null,
                    ]
                );

                $synced++;
            }

            if (! empty($activeNontriIds)) {
                $deleted = Teacher::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('nontri_id', $activeNontriIds)
                    ->update([
                        'deleted_at' => now(),
                    ]);
            }

            $skipped = max(count($teachers) - $synced, 0);
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
