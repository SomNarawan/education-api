<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyTermCourse;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class StudyTermCourseController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StudyTermCourse::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load study term courses successfully'
        );
    }
}
