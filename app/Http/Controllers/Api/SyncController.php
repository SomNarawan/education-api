<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Constants\Status;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Models\Sync;
use App\Models\SyncType;
use App\Services\SystemMasterDataSyncService;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class SyncController extends Controller
{
    /**
     * API: GET /api/syncs
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_type' => ['nullable', 'integer', 'min:1'],
            'status' => [
                'nullable',
                'string',
                Rule::in(Status::syncStatuses()),
            ],
        ]);

        $latestSyncIds = Sync::query()
            ->selectRaw('sync_type, MAX(id) AS latest_sync_id')
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status'])
            )
            ->groupBy('sync_type');

        $items = SyncType::query()
            ->whereIn('sync_types.id', [
                Sync::TYPE_SYSTEM_FACULTY,
                Sync::TYPE_SYSTEM_DEPARTMENT,
            ])
            ->leftJoinSub(
                $latestSyncIds,
                'latest_syncs',
                fn (JoinClause $join) => $join->on(
                    'sync_types.id',
                    '=',
                    'latest_syncs.sync_type'
                )
            )
            ->leftJoin('syncs', 'syncs.id', '=', 'latest_syncs.latest_sync_id')
            ->when(
                isset($validated['sync_type']),
                fn ($query) => $query->where('sync_types.id', $validated['sync_type'])
            )
            ->when(
                isset($validated['status']),
                fn ($query) => $query->whereNotNull('syncs.id')
            )
            ->select([
                'syncs.id',
                'sync_types.id AS sync_type',
                'sync_types.sync_type AS sync_type_name',
                'syncs.inserted_count',
                'syncs.updated_count',
                'syncs.inactivated_count',
                'syncs.skipped_count',
                'syncs.status',
                'syncs.error_message',
                'syncs.created_at',
                'syncs.created_by',
                'syncs.updated_at',
                'syncs.updated_by',
            ])
            ->orderBy('sync_types.id')
            ->get();

        return ApiResponse::success(
            SyncResponse::collection($items),
            'Load syncs successfully'
        );
    }

    /**
     * API: POST /api/system-faculties/sync
     */
    public function systemFaculties(
        Request $request,
        SystemMasterDataSyncService $syncService
    ): JsonResponse {
        return $this->runSystemSync(
            $request,
            $syncService,
            Sync::TYPE_SYSTEM_FACULTY
        );
    }

    /**
     * API: POST /api/system-departments/sync
     */
    public function systemDepartments(
        Request $request,
        SystemMasterDataSyncService $syncService
    ): JsonResponse {
        return $this->runSystemSync(
            $request,
            $syncService,
            Sync::TYPE_SYSTEM_DEPARTMENT
        );
    }

    private function runSystemSync(
        Request $request,
        SystemMasterDataSyncService $syncService,
        int $syncType
    ): JsonResponse {
        try {
            $actor = $this->actorFromJwt($request) ?? 'system';
            $sync = $syncService->sync($syncType, $actor);

            return ApiResponse::success(
                new SyncResponse($sync),
                'Sync successfully'
            );
        } catch (Throwable $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }
    }
}
