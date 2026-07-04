<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use App\Services\PersonnelApiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Responses\TeacherListResponse;

class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Teacher::query()
            ->where('deleted_at', null)
            ->when(
                $request->filled('department_id'),
                fn($query) => $query->where(
                    'department_id',
                    $request->query('department_id')
                )
            )
            ->orderBy('id')
            ->get();

        $data = TeacherListResponse::collection($items);

        return ApiResponse::success($data, 'Load teachers successfully');
    }

    public function sync(
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        try {
            $response = $personnelApiService->getTeachers();
            $teachers = $response['users'] ?? $response;

            $synced = 0;
            $deleted = 0;
            $skippedWithoutDepartment = 0;
            $skippedUnknownDepartment = 0;
            $activeNontriIds = [];

            $departmentIds = collect($teachers)
                ->pluck('department_id')
                ->filter(fn($departmentId) => is_numeric($departmentId) && (int) $departmentId > 0)
                ->map(fn($departmentId) => (int) $departmentId)
                ->unique()
                ->values()
                ->all();

            $existingDepartmentIds = Department::query()
                ->whereIn('id', $departmentIds, 'and', false)
                ->pluck('id')
                ->mapWithKeys(fn($departmentId) => [(int) $departmentId => true])
                ->all();

            foreach ($teachers as $teacher) {
                if (
                    empty($teacher['nontri_id']) ||
                    empty($teacher['full_name'])
                ) {
                    continue;
                }

                if (
                    !isset($teacher['department_id']) ||
                    !is_numeric($teacher['department_id']) ||
                    (int) $teacher['department_id'] <= 0
                ) {
                    $skippedWithoutDepartment++;
                    continue;
                }

                $departmentId = (int) $teacher['department_id'];

                if (!isset($existingDepartmentIds[$departmentId])) {
                    $skippedUnknownDepartment++;
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

            if (!empty($activeNontriIds)) {
                $deleted = Teacher::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('nontri_id', $activeNontriIds)
                    ->update([
                        'deleted_at' => now(),
                    ]);
            }

            return ApiResponse::success(
                [
                    'synced' => $synced,
                    'deleted' => $deleted,
                    'skipped_without_department' => $skippedWithoutDepartment,
                    'skipped_unknown_department' => $skippedUnknownDepartment,
                ],
                'Sync teachers successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500
            );
        }
    }
}
