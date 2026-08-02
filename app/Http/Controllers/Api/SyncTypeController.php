<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SyncType;
use Illuminate\Http\JsonResponse;

class SyncTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $items = SyncType::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load sync types successfully'
        );
    }
}
