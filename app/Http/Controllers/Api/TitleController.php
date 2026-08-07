<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Title;
use Illuminate\Http\JsonResponse;

class TitleController extends Controller
{
    /**
     * API: GET /api/titles
     */
    public function index(): JsonResponse
    {
        $items = Title::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load titles successfully'
        );
    }
}
