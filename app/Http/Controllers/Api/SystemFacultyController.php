<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Models\Sync;
use App\Models\SystemFaculty;
use App\Services\PersonnelApiService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SystemFacultyController extends Controller
{
    /**
     * API: GET /api/system-faculties
     */
    public function index(): JsonResponse
    {
        $items = SystemFaculty::query()
            ->where('deleted_at', null)
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($items, 'Load system faculties successfully');
    }

    /**
     * API: POST /api/system-faculties/sync
     */
    public function sync(
        PersonnelApiService $personnelApiService
    ): JsonResponse {
        $sync = null;

        try {
            $sync = Sync::start(Sync::TYPE_SYSTEM_FACULTY);

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

            if (! empty($activeFacultyIds)) {
                $deleted = SystemFaculty::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('id', $activeFacultyIds)
                    ->update([
                        'deleted_at' => now(),
                    ]);
            }

            $skipped = max(count($faculties) - $synced, 0);
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
