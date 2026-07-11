<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanTerm;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class PlanTermController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PlanTerm::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load plan terms successfully'
        );
    }
}
