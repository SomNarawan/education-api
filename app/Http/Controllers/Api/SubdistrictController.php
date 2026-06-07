<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subdistrict;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class SubdistrictController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Subdistrict::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load subdistricts successfully'
        );
    }
}
