<?php

namespace App\Services;

use App\Contracts\CurriculumApi;
use Illuminate\Validation\ValidationException;

class MockCurriculumApiService implements CurriculumApi
{
    public function getStudyPlans(): array
    {
        return config('curriculum_api.mock.study_plans', []);
    }

    public function findStudyPlan(int $studyPlanId): ?array
    {
        $studyPlan = collect($this->getStudyPlans())->firstWhere('id', $studyPlanId);

        if ($studyPlan === null) {
            return null;
        }

        return [
            ...$studyPlan,
            ...config('curriculum_api.mock.study_plan_context.'.$studyPlanId, []),
        ];
    }

    public function getCurriculumCategories(int $studyPlanId): array
    {
        $studyPlan = $this->findStudyPlan($studyPlanId);

        if ($studyPlan === null) {
            throw ValidationException::withMessages([
                'study_plan_id' => ['The selected study plan id is invalid.'],
            ]);
        }

        return $this->categoryTree($studyPlan['curriculum_id']);
    }

    private function categoryTree(int $curriculumId): array
    {
        $categories = collect(config('curriculum_api.mock.curriculum_categories', []))
            ->where('curriculum_id', $curriculumId)
            ->groupBy(fn (array $category) => $category['parent_id'] ?? 'root');

        $buildTree = function ($parentId) use (&$buildTree, $categories): array {
            return $categories->get($parentId, collect())
                ->map(function (array $category) use (&$buildTree): array {
                    unset($category['parent_id']);
                    $category['children'] = $buildTree($category['id']);

                    return $category;
                })
                ->values()
                ->all();
        };

        return $buildTree('root');
    }
}
