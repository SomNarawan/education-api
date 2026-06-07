<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumCategory;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CurriculumCategory::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculum categories successfully'
        );
    }
}
