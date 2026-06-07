<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Course::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load courses successfully'
        );
    }
}
