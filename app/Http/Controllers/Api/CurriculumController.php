<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Curriculum::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculums successfully'
        );
    }
}
