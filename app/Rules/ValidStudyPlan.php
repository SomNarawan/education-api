<?php

namespace App\Rules;

use App\Contracts\CurriculumApi;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidStudyPlan implements ValidationRule
{
    public function __construct(
        private readonly CurriculumApi $curriculumApi,
        private readonly ?int $curriculumId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $studyPlan = $this->curriculumApi->findStudyPlan((int) $value);

        if (
            $studyPlan === null
            || ($this->curriculumId !== null
                && (int) ($studyPlan['curriculum_id'] ?? 0) !== $this->curriculumId)
        ) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
