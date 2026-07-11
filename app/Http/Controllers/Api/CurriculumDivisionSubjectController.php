<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumDivisionSubject;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumDivisionSubjectController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CurriculumDivisionSubject::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculum division subjects successfully'
        );
    }
}
