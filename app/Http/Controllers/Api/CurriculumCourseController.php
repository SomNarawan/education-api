<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumCourse;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumCourseController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CurriculumCourse::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculum courses successfully'
        );
    }
}
