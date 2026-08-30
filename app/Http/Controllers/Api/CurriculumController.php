<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CurriculumApi;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    /**
     * API: GET /api/curriculums
     */
    public function index(CurriculumApi $curriculumApi): JsonResponse
    {
        return ApiResponse::success(
            $curriculumApi->getCurriculums(),
            'Load curriculums successfully',
        );
    }
}
