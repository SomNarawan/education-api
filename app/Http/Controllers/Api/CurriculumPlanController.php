<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CurriculumApi;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CurriculumPlanController extends Controller
{
    /**
     * API: GET /api/study-plans
     */
    public function index(CurriculumApi $curriculumApi): JsonResponse
    {
        return ApiResponse::success(
            $curriculumApi->getStudyPlans(),
            'Load curriculum plans successfully'
        );
    }
}
