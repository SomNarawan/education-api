<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumGroup;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CurriculumGroup::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculum groups successfully'
        );
    }
}
