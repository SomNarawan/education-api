<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicCalendar\ListAcademicCalendarsRequest;
use App\Http\Requests\AcademicCalendar\SyncAcademicCalendarsRequest;
use App\Http\Responses\SyncResponse;
use App\Services\AcademicCalendarJsonStore;
use App\Services\AcademicCalendarSyncService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AcademicCalendarController extends Controller
{
    public function __construct(
        private readonly AcademicCalendarJsonStore $calendarStore,
    ) {}

    /**
     * API: GET /api/academic-calendars
     */
    public function index(ListAcademicCalendarsRequest $request): JsonResponse
    {
        $availableAcademicYears = $this->calendarStore->years();
        $academicYear = $request->academicYear()
            ?? ($availableAcademicYears[0] ?? null);

        $data = $academicYear === null
            ? null
            : $this->calendarStore->get($academicYear);
        $data = is_array($data) ? $data : [];

        return ApiResponse::success([
            ...$data,
            'academicYear' => $academicYear,
            'availableAcademicYears' => $availableAcademicYears,
            'terms' => is_array($data['terms'] ?? null)
                ? array_values($data['terms'])
                : [],
        ], 'Load academic calendar successfully');
    }

    /**
     * API: POST /api/academic-calendars/sync
     */
    public function sync(
        SyncAcademicCalendarsRequest $request,
        AcademicCalendarSyncService $syncService
    ): JsonResponse {
        try {
            $actor = $this->actorFromJwt($request) ?? 'system';
            $sync = $syncService->sync($request->academicYears(), $actor);

            return ApiResponse::success(
                new SyncResponse($sync),
                'Sync academic calendars successfully'
            );
        } catch (Throwable $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                HttpStatus::INTERNAL_SERVER_ERROR['code']
            );
        }
    }
}
