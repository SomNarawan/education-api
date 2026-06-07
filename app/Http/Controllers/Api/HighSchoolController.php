<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HighSchool;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class HighSchoolController extends Controller
{
    public function index(): JsonResponse
    {
        $items = HighSchool::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load high schools successfully'
        );
    }
}
