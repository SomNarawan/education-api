<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyPlanTrack;
use Illuminate\Http\JsonResponse;
use App\Helpers\ApiResponse;

class StudyPlanTrackController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StudyPlanTrack::orderBy('id')->get();

        return ApiResponse::success(
            $items,
            'Load study plan tracks successfully'
        );
    }
}
