<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CurriculumDivision;
use Illuminate\Http\JsonResponse;

class CurriculumDivisionController extends Controller
{
    /**
     * API: GET /api/curriculum-divisions
     */
    public function index(): JsonResponse
    {
        $divisionsByParent = CurriculumDivision::query()
            ->select(['id', 'parent_id', 'name_th', 'division_type'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CurriculumDivision $division) => $division->parent_id ?? 'root');

        $buildTree = function ($parentId) use (&$buildTree, $divisionsByParent) {
            return $divisionsByParent
                ->get($parentId, collect())
                ->map(fn (CurriculumDivision $division) => [
                    'id' => $division->id,
                    'name_th' => $division->name_th,
                    'division_type' => $division->division_type,
                    'children' => $buildTree($division->id),
                ])
                ->values()
                ->all();
        };

        $items = $buildTree('root');

        return ApiResponse::success(
            $items,
            'Load curriculum divisions successfully'
        );
    }
}
