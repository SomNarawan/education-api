<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentStatus;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class StudentStatusController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StudentStatus::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load student statuses successfully'
        );
    }
}
