<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CurriculumApi;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumPlanController extends Controller
{
    /**
     * API: GET /api/study-plans
     */
    public function index(Request $request, CurriculumApi $curriculumApi): JsonResponse
    {
        $validated = $request->validate([
            'curriculum_id' => ['nullable', 'integer'],
        ]);

        return ApiResponse::success(
            $curriculumApi->getStudyPlans(
                isset($validated['curriculum_id'])
                    ? (int) $validated['curriculum_id']
                    : null,
            ),
            'Load curriculum plans successfully'
        );
    }
}
