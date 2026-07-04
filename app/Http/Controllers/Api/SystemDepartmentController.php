<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemDepartment;
use App\Services\PersonnelApiService;
use Exception;
use Illuminate\Http\JsonResponse;

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
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        try {
            $response = $personnelApiService->getDepartments();

            $departments = $response['departments'] ?? $response;

            $synced = 0;
            $deleted = 0;
            $skippedWithoutFaculty = 0;
            $activeDepartmentIds = [];

            foreach ($departments as $department) {
                if (
                    empty($department['id']) ||
                    empty($department['th_name'])
                ) {
                    continue;
                }

                if (
                    empty($department['system_faculty_id']) ||
                    !is_numeric($department['system_faculty_id'])
                ) {
                    $skippedWithoutFaculty++;
                    continue;
                }

                $departmentId = (int) $department['id'];
                $systemFacultyId = (int) $department['system_faculty_id'];
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
                    'skipped_without_faculty' => $skippedWithoutFaculty,
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
