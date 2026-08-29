<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\SystemTeacherListResponse;
use App\Models\SystemTeacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemTeacherController extends Controller
{
    /**
     * API: GET /api/system-teachers?department_id={id}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $items = SystemTeacher::query()
            ->when(
                isset($validated['department_id']),
                fn ($query) => $query->where('department_id', $validated['department_id'])
            )
            ->orderBy('full_name_th')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemTeacherListResponse::collection($items),
            'Load system teachers successfully'
        );
    }

    /**
     * API: GET /api/system-teachers/all?department_id={id}
     */
    public function all(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $items = SystemTeacher::withTrashed()
            ->when(
                isset($validated['department_id']),
                fn ($query) => $query->where('department_id', $validated['department_id'])
            )
            ->orderBy('full_name_th')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            SystemTeacherListResponse::collection($items),
            'Load all system teachers successfully'
        );
    }
}
