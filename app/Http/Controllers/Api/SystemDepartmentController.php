<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemDepartment;
use App\Services\PersonnelApiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemDepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $items = SystemDepartment::query()
            ->where('deleted_at', null)
            ->orderBy('id')
            ->get();

        $data = collection($items);

        return ApiResponse::success($data, 'Load system departments successfully');
    }

    public function sync(
        Request $request,
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        $departmentId = $request->query('department_id');

        if (!$departmentId) {
            return ApiResponse::error(
                'department_id is required',
                422
            );
        }

        try {
            $departmentId = (int) $departmentId;
            $teachers = $personnelApiService->getTeachers($departmentId);

            $synced = 0;
            $deleted = 0;
            $activeNontriIds = [];

            foreach ($teachers['users'] ?? [] as $teacher) {
                if (
                    empty($teacher['nontri_id']) ||
                    empty($teacher['full_name'])
                ) {
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
                    ->where('department_id', $departmentId)
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