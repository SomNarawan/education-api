<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Http\Responses\SystemFacultyResponse;
use App\Models\Sync;
use App\Models\SystemFaculty;
use App\Services\PersonnelApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SystemFacultyController extends Controller
{
    /**
     * API: GET /api/system-faculties
     */
    public function index(): JsonResponse
    {
        $items = SystemFaculty::query()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemFacultyResponse::collection($items)->resolve(),
            'Load system faculties successfully'
        );
    }

    /**
     * API: GET /api/system-faculties/all
     */
    public function all(): JsonResponse
    {
        $items = SystemFaculty::withTrashed()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemFacultyResponse::collection($items)->resolve(),
            'Load all system faculties successfully'
        );
    }

    /**
     * API: POST /api/system-faculties/sync
     */
    public function sync(
        Request $request,
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
            $actor = $this->actorFromJwt($request) ?? 'system';

            foreach ($faculties as $faculty) {
                if (
                    empty($faculty['id']) ||
                    empty($faculty['th_name'])
                ) {
                    continue;
                }

                $facultyId = (int) $faculty['id'];
                $activeFacultyIds[] = $facultyId;

                $model = SystemFaculty::withTrashed()->whereKey($facultyId)->first()
                    ?? new SystemFaculty;

                if (! $model->exists) {
                    $model->setAttribute('id', $facultyId);
                    $model->setAttribute('created_by', $actor);
                }

                $model->fill([
                    'th_name' => $faculty['th_name'],
                    'en_name' => $faculty['en_name'] ?? '-',
                    'th_short_name' => $faculty['th_short_name'] ?? '-',
                    'en_short_name' => $faculty['en_short_name'] ?? '-',
                    'updated_by' => $actor,
                    'deleted_at' => null,
                    'deleted_by' => '',
                    'sync_id' => $sync->getKey(),
                ])->save();

                $synced++;
            }

            if (! empty($activeFacultyIds)) {
                $deleted = SystemFaculty::query()
                    ->where('deleted_at', null)
                    ->whereNotIn('id', $activeFacultyIds)
                    ->update([
                        'deleted_at' => now(),
                        'deleted_by' => $actor,
                        'updated_by' => $actor,
                        'sync_id' => $sync->getKey(),
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
