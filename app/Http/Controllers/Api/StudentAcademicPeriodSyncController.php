<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\StudentAcademicPeriodSyncResponse;
use App\Models\StudentAcademicPeriodSync;
use App\Services\StudentAcademicPeriodSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class StudentAcademicPeriodSyncController extends Controller
{
    public function __construct(
        private readonly StudentAcademicPeriodSyncService $syncService
    ) {}

    /**
     * API: GET /api/student-academic-period-sync/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $referenceDate = isset($validated['reference_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['reference_date'])
            : CarbonImmutable::now('Asia/Bangkok')->startOfDay();

        return ApiResponse::success(
            $this->syncService->preview($referenceDate),
            'Load student academic period sync preview successfully'
        );
    }

    /**
     * API: GET /api/student-academic-period-sync/history
     */
    public function history(): JsonResponse
    {
        $history = StudentAcademicPeriodSync::query()
            ->latest('id')
            ->limit(50)
            ->get();

        return ApiResponse::success(
            StudentAcademicPeriodSyncResponse::collection($history),
            'Load student academic period sync history successfully'
        );
    }

    /**
     * API: POST /api/student-academic-period-sync
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $referenceDate = isset($validated['reference_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['reference_date'])
            : CarbonImmutable::now('Asia/Bangkok')->startOfDay();

        try {
            $actor = $this->actorFromJwt($request) ?? 'system';
            $history = $this->syncService->sync($referenceDate, $actor);

            return ApiResponse::success(
                new StudentAcademicPeriodSyncResponse($history),
                'Sync student academic periods successfully'
            );
        } catch (Throwable $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }
    }
}
