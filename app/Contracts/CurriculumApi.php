<?php

namespace App\Contracts;

interface CurriculumApi
{
    public function getStudyPlans(): array;

    public function findStudyPlan(int $studyPlanId): ?array;

    public function getCurriculumCategories(int $studyPlanId): array;
}
