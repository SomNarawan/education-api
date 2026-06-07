<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Department::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load departments successfully'
        );
    }
}
