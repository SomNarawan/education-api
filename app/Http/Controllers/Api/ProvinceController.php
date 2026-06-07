<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class ProvinceController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Province::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load provinces successfully'
        );
    }
}
