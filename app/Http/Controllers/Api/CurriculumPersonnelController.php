<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CmisApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumPersonnelController extends Controller
{
    public function __construct(
        private readonly CmisApi $cmisApi,
    ) {}

    /**
     * API: GET /api/curriculum-personnel?curriculum_id={id}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'curriculum_id' => ['required', 'integer'],
        ]);

        return response()->json(
            $this->cmisApi->getCurriculumPersonnel((int) $validated['curriculum_id']),
            options: JSON_UNESCAPED_UNICODE,
        );
    }
}
