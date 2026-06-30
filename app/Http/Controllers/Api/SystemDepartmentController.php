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

        return ApiResponse::success($items, 'Load system departments successfully');
    }

    public function sync(
        Request $request,
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        $facultyId = $request->query('system_faculty_id');

        if (!$facultyId) {
            return ApiResponse::error(
                'system_faculty_id is required',
                422
            );
        }

        try {
            $facultyId = (int) $facultyId;

            $response = $personnelApiService->getDepartments($facultyId);

            $departments = $response['departments'] ?? [];
            $systemFacultyId = (int) ($response['system_faculty_id'] ?? $facultyId);

            $synced = 0;
            $deleted = 0;
            $activeDepartmentIds = [];

            foreach ($departments as $department) {
                if (
                    empty($department['id']) ||
                    empty($department['th_name'])
                ) {
                    continue;
                }

                $departmentId = (int) $department['id'];
                $activeDepartmentIds[] = $departmentId;

                SystemDepartment::updateOrCreate(
                    [
                        'id' => $departmentId,
                    ],
                    [
                        'th_name' => $department['th_name'],
                        'en_name' => $department['en_name'],
                        'th_short_name' => $department['th_short_name'],
                        'en_short_name' => $department['en_short_name'],
                        'system_faculty_id' => $systemFacultyId,
                        'deleted_at' => null,
                    ]
                );

                $synced++;
            }

            if (!empty($activeDepartmentIds)) {
                $deleted = SystemDepartment::query()
                    ->where('system_faculty_id', $systemFacultyId)
                    ->where('deleted_at', null)
                    ->whereNotIn('id', $activeDepartmentIds)
                    ->update([
                        'deleted_at' => now(),
                    ]);
            }

            return ApiResponse::success(
                [
                    'synced' => $synced,
                    'deleted' => $deleted,
                ],
                'Sync system departments successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500
            );
        }
    }
}