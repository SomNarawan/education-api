<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurriculumDivision;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class CurriculumDivisionController extends Controller
{
    public function index(): JsonResponse
    {
        $items = CurriculumDivision::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load curriculum divisions successfully'
        );
    }

    public function categories(): JsonResponse
    {
        $items = CurriculumDivision::query()
            ->select(['id', 'name_th'])
            ->where('division_type', 'category')
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load curriculum categories successfully'
        );
    }

    public function groups(): JsonResponse
    {
        $items = CurriculumDivision::query()
            ->where('division_type', 'group')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            $items,
            'Load curriculum groups successfully'
        );
    }
}
