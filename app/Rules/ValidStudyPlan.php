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
        $studyPlan = $this->cmisApi->findStudyPlan((int) $value);

        if (
            $studyPlan === null
            || ($this->curriculumId !== null
                && (int) ($studyPlan['curriculum_id'] ?? 0) !== $this->curriculumId)
        ) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
