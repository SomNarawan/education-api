<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Geography\ListDistrictsRequest;
use App\Models\District;
use Illuminate\Http\JsonResponse;

class DistrictController extends Controller
{
    public function index(ListDistrictsRequest $request): JsonResponse
    {
        $items = District::query()
            ->where('province_id', $request->integer('province_id'))
            ->orderBy('district_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load districts successfully'
        );
    }
}
