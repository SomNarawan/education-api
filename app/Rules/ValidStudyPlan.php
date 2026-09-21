<?php

namespace App\Rules;

use App\Contracts\CmisApi;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidStudyPlan implements ValidationRule
{
    public function __construct(
        private readonly CmisApi $cmisApi,
        private readonly ?int $curriculumId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $payload = $this->cmisApi->getCurriculumCategories((int) $value);
        $studyPlan = data_get($payload, 'meta.study_plan');
        $curriculumId = is_array($studyPlan)
            ? ($studyPlan['curriculum_id'] ?? data_get($payload, 'meta.curriculum.id'))
            : null;

        if (
            ! is_array($studyPlan)
            || ($this->curriculumId !== null
                && (int) $curriculumId !== $this->curriculumId)
        ) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
