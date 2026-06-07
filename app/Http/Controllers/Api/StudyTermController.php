<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyTerm;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class StudyTermController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StudyTerm::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load study terms successfully'
        );
    }
}
