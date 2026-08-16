<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Geography\ListSubdistrictsRequest;
use App\Models\Subdistrict;
use Illuminate\Http\JsonResponse;

class SubdistrictController extends Controller
{
    public function index(ListSubdistrictsRequest $request): JsonResponse
    {
        $items = Subdistrict::query()
            ->where('district_id', $request->integer('district_id'))
            ->orderBy('subdistrict_name')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load subdistricts successfully'
        );
    }
}
