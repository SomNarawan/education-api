<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CmisApi;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    public function __construct(
        private readonly CmisApi $cmisApi,
    ) {}

    /**
     * API: GET /api/curriculums
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            $this->cmisApi->getCurriculums(),
            'Load curriculums successfully',
        );
    }
}
