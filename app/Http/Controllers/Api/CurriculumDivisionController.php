<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CurriculumDivision;
use Illuminate\Http\JsonResponse;

class CurriculumDivisionController extends Controller
{
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
