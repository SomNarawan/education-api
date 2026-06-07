<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class DistrictController extends Controller
{
    public function index(): JsonResponse
    {
        $items = District::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load districts successfully'
        );
    }
}
