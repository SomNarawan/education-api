<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SystemDepartmentResponse;
use App\Models\SystemDepartment;
use Illuminate\Http\JsonResponse;

class SystemDepartmentController extends Controller
{
    /**
     * API: GET /api/system-departments
     */
    public function index(): JsonResponse
    {
        $items = SystemDepartment::query()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemDepartmentResponse::collection($items)->resolve(),
            'Load system departments successfully'
        );
    }

    /**
     * API: GET /api/system-departments/all
     */
    public function all(): JsonResponse
    {
        $items = SystemDepartment::withTrashed()
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemDepartmentResponse::collection($items)->resolve(),
            'Load all system departments successfully'
        );
    }
}
