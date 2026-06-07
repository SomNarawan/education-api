<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class FacultyController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Faculty::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load faculties successfully'
        );
    }
}
