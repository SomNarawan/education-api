<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CampusController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Campus::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load campuses successfully'
        );
    }
}
