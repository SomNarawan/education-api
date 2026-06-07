<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoursePrerequisite;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CoursePrerequisiteController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CoursePrerequisite::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load course prerequisites successfully'
        );
    }
}
