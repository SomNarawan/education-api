<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\HighSchool;
use Illuminate\Http\JsonResponse;

class HighSchoolController extends Controller
{
    /**
     * API: GET /api/high-schools
     */
    public function index(): JsonResponse
    {
        $items = HighSchool::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load high schools successfully'
        );
    }
}
