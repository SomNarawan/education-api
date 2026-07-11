<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumPlan;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CurriculumPlan::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculum plans successfully'
        );
    }
}
