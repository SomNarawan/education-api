<?php

namespace App\Contracts;

interface CurriculumApi
{
    public function getCurriculums(): array;

    public function getStudyPlans(?int $curriculumId = null): array;

    public function findStudyPlan(int $studyPlanId): ?array;

    public function getCurriculumCategories(int $studyPlanId): array;
}
