<?php

namespace App\Http\Controllers\Api;

use App\Constants\HttpStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SyncResponse;
use App\Services\AcademicCalendarJsonStore;
use App\Services\AcademicCalendarSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AcademicCalendarController extends Controller
{
    public function __construct(
        private readonly AcademicCalendarJsonStore $calendarStore,
    ) {}

    /**
     * API: GET /api/academic-calendars
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academicYear' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $availableAcademicYears = $this->calendarStore->years();

        $academicYear = isset($validated['academicYear'])
            ? (int) $validated['academicYear']
            : ($availableAcademicYears[0] ?? null);

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
        Request $request,
        AcademicCalendarSyncService $syncService
    ): JsonResponse {
        $validated = $request->validate([
            'academic_years' => ['required', 'array', 'min:1', 'max:10'],
            'academic_years.*' => [
                'required',
                'integer',
                'distinct',
                'between:2000,2100',
            ],
        ]);

        $academicYears = collect($validated['academic_years'])
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->all();

        try {
            $actor = $this->actorFromJwt($request) ?? 'system';
            $sync = $syncService->sync($academicYears, $actor);

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
