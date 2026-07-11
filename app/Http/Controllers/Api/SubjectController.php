<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class SubjectController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Subject::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load subjects successfully'
        );
    }
}
