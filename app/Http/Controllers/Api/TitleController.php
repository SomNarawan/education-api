<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Title;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class TitleController extends Controller
{
    /**
     * GET /api/titles
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