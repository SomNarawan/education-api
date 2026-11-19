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
            ->with('systemFaculty')
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemDepartmentResponse::collection($items)->resolve(),
            __('success.organization.departments_loaded')
        );
    }

    /**
     * API: GET /api/system-departments/all
     */
    public function all(): JsonResponse
    {
        $items = SystemDepartment::withInactive()
            ->with([
                'systemFaculty' => fn ($query) => $query->withInactive(),
            ])
            ->orderBy('th_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemDepartmentResponse::collection($items)->resolve(),
            __('success.organization.all_departments_loaded')
        );
    }
}
