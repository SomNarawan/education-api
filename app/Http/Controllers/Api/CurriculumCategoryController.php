<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CurriculumApi;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumCategoryController extends Controller
{
    /**
     * API: GET /api/curriculum-categories?study_plan_id={id}
     */
    public function index(Request $request, CurriculumApi $curriculumApi): JsonResponse
    {
        $validated = $request->validate([
            'study_plan_id' => ['required', 'integer'],
        ]);

        return ApiResponse::success(
            $curriculumApi->getCurriculumCategories($validated['study_plan_id']),
            'Load curriculum categories successfully'
        );
    }
}
