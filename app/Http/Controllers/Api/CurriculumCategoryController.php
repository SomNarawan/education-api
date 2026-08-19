<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CurriculumCategory;
use App\Models\CurriculumPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumCategoryController extends Controller
{
    /**
     * API: GET /api/curriculum-categories?study_plan_id={id}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_plan_id' => ['required', 'integer', 'exists:curriculum_plans,id'],
        ]);

        $curriculumId = CurriculumPlan::query()
            ->findOrFail($validated['study_plan_id'])
            ->curriculum_id;

        $categoriesByParent = CurriculumCategory::query()
            ->select([
                'id',
                'curriculum_id',
                'parent_id',
                'category_type',
                'code',
                'name_th',
                'name_en',
                'course_source_type',
                'status',
            ])
            ->where('curriculum_id', $curriculumId)
            ->orderBy('name_th')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CurriculumCategory $category) => $category->parent_id ?? 'root');

        $buildTree = function ($parentId) use (&$buildTree, $categoriesByParent) {
            return $categoriesByParent
                ->get($parentId, collect())
                ->map(fn (CurriculumCategory $category) => [
                    'id' => $category->id,
                    'curriculum_id' => $category->curriculum_id,
                    'category_type' => $category->category_type,
                    'code' => $category->code,
                    'name_th' => $category->name_th,
                    'name_en' => $category->name_en,
                    'course_source_type' => $category->course_source_type,
                    'status' => $category->status,
                    'children' => $buildTree($category->id),
                ])
                ->values()
                ->all();
        };

        return ApiResponse::success(
            $buildTree('root'),
            'Load curriculum categories successfully'
        );
    }
}
