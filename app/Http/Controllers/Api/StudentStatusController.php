<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\StudentStatus;
use Illuminate\Http\JsonResponse;

class StudentStatusController extends Controller
{
    /**
     * API: GET /api/student-statuses
     */
    public function index(): JsonResponse
    {
        $items = StudentStatus::query()
            ->orderBy('status_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load student statuses successfully'
        );
    }
}
