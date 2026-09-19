<?php

namespace App\Contracts;

interface CmisApi
{
    /**
     * Return only the curriculums from the CMIS response data field.
     */
    public function getCurriculums(): array;

    /**
     * Return the complete response envelope from CMIS, including data and meta.
     */
    public function getCurriculumCategories(int $studyPlanId): array;

    /**
     * Return only the curriculum plans from the CMIS response data field.
     */
    public function getCurriculumPlans(int $curriculumId): array;

    /**
     * Find a study plan with the curriculum context required by student flows.
     */
    public function findStudyPlan(int $studyPlanId): ?array;

    /**
     * Return the complete curriculum personnel response envelope from CMIS.
     */
    public function getCurriculumPersonnel(int $curriculumId): array;
}
