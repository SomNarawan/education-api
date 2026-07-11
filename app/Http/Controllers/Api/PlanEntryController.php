<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanEntry;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class PlanEntryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PlanEntry::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load plan entries successfully'
        );
    }
}
