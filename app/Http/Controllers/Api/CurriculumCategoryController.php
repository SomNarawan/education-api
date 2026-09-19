<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CmisApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumCategoryController extends Controller
{
    public function __construct(
        private readonly CmisApi $cmisApi,
    ) {}

    /**
     * API: GET /api/curriculum-categories?study_plan_id={id}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_plan_id' => ['required', 'integer'],
        ]);

        return response()->json(
            $this->cmisApi->getCurriculumCategories((int) $validated['study_plan_id']),
            options: JSON_UNESCAPED_UNICODE,
        );
    }
}
