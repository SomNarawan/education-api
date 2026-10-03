<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CmisApi;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumPlanCourseController extends Controller
{
    public function __construct(
        private readonly CmisApi $cmisApi,
    ) {}

    /**
     * API: GET /api/curriculum-plan-courses?study_plan_id={id}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_plan_id' => ['required', 'integer', 'min:1'],
        ]);

        return ApiResponse::success(
            $this->cmisApi->getCurriculumPlanCourses((int) $validated['study_plan_id']),
            'Load curriculum plan courses successfully',
        );
    }
}
