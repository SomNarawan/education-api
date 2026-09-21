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
     * Return only the curriculum personnel from the CMIS response data field.
     */
    public function getCurriculumPersonnel(int $curriculumId): array;
}
