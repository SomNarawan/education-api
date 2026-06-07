<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class TeacherController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Teacher::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load teachers successfully'
        );
    }
}
