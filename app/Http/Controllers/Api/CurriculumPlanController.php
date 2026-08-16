<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CurriculumPlan;
use Illuminate\Http\JsonResponse;

class CurriculumPlanController extends Controller
{
    /**
     * API: GET /api/study-plans
     */
    public function index(): JsonResponse
    {
        $items = CurriculumPlan::query()
            ->orderBy('name_th')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load curriculum plans successfully'
        );
    }
}
