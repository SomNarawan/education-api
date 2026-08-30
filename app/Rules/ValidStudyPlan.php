<?php

namespace App\Rules;

use App\Contracts\CurriculumApi;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidStudyPlan implements ValidationRule
{
    public function __construct(private readonly CurriculumApi $curriculumApi) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->curriculumApi->findStudyPlan((int) $value) === null) {
            $fail('The selected :attribute is invalid.');
        }
    }
}
