<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemFaculty;
use App\Services\PersonnelApiService;
use Exception;
use Illuminate\Http\JsonResponse;

class SystemFacultyController extends Controller
{
    public function index(): JsonResponse
    {
        $items = SystemFaculty::query()
            ->where('deleted_at', null)
            ->orderBy('id')
            ->get();

        return ApiResponse::success($items, 'Load system faculties successfully');
    }

    public function sync(
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        try {
            $response = $personnelApiService->getFaculties();

            $faculties = $response['faculties'] ?? $response ?? [];

            $synced = 0;
            $deleted = 0;
            $activeFacultyIds = [];

            foreach ($faculties as $faculty) {
                if (
                    empty($faculty['id']) ||
                    empty($faculty['th_name'])
                ) {
                    continue;
                }

                $facultyId = (int) $faculty['id'];
                $activeFacultyIds[] = $facultyId;

                SystemFaculty::updateOrCreate(
                    [
                        'id' => $facultyId,
                    ],
                    [
                        'th_name' => $faculty['th_name'],
                        'en_name' => $faculty['en_name'] ?? '-',
                        'th_short_name' => $faculty['th_short_name'] ?? '-',
                        'en_short_name' => $faculty['en_short_name'] ?? '-',
                        'deleted_at' => null,
                    ]
                );

                $synced++;
            }

            if (!empty($activeFacultyIds)) {
                $deleted = SystemFaculty::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('id', $activeFacultyIds)
                    ->update([
                        'deleted_at' => now(),
                    ]);
            }

            return ApiResponse::success(
                [
                    'synced' => $synced,
                    'deleted' => $deleted,
                ],
                'Sync system faculties successfully'
            );
        } catch (Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                500
            );
        }
    }
}