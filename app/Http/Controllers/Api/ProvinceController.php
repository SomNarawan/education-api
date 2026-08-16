<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class ProvinceController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Province::query()
            ->orderBy('province_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load provinces successfully'
        );
    }
}
