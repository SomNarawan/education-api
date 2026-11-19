<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentAcademicPeriod\StudentAcademicPeriodSyncRequest;
use App\Http\Responses\StudentAcademicPeriodSyncResponse;
use App\Models\StudentAcademicPeriodSync;
use App\Services\StudentAcademicPeriodSyncService;
use Illuminate\Http\JsonResponse;
use Throwable;

class StudentAcademicPeriodSyncController extends Controller
{
    public function __construct(
        private readonly StudentAcademicPeriodSyncService $syncService
    ) {}

    /**
     * API: GET /api/student-academic-period-sync/preview
     */
    public function preview(StudentAcademicPeriodSyncRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->syncService->preview($request->referenceDate()),
            __('success.student_academic_period.preview_loaded')
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
            __('success.student_academic_period.history_loaded')
        );
    }

    /**
     * API: POST /api/student-academic-period-sync
     */
    public function store(StudentAcademicPeriodSyncRequest $request): JsonResponse
    {
        try {
            $actor = $this->actorFromJwt($request) ?? 'system';
            $history = $this->syncService->sync($request->referenceDate(), $actor);

            return ApiResponse::success(
                StudentAcademicPeriodSyncResponse::collection($history),
                __('success.student_academic_period.synced')
            );
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(
                __('errors.sync.student_academic_period_failed'),
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }
    }
}
