<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SystemFacultyResponse;
use App\Models\SystemFaculty;
use Illuminate\Http\JsonResponse;

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
        $items = SystemFaculty::withInactive()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemFacultyResponse::collection($items)->resolve(),
            'Load all system faculties successfully'
        );
    }
}
