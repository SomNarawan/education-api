<?php

namespace App\Http\Requests\GradeCalculation;

use App\Services\GradeCalculations\GradePredictionCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeCalculationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'study_year' => ['required', 'integer', 'min:1', 'max:20'],
            'semester' => ['required', 'string', 'max:50'],
            'semester_order' => ['nullable', 'integer', 'min:1', 'max:3'],
            'courses' => ['required', 'array', 'min:1', 'max:100'],
            'courses.*.code' => ['required', 'string', 'max:50'],
            'courses.*.name' => ['required', 'string', 'max:255'],
            'courses.*.credits' => ['required', 'integer', 'min:1', 'max:99'],
            'courses.*.source' => [
                'required',
                'string',
                Rule::in(['backlog', 'next-semester', 'other']),
            ],
            'courses.*.course_group' => ['nullable', 'string', 'max:255'],
            'courses.*.course_group_gpa' => ['nullable', 'numeric', 'between:0,4'],
            'courses.*.grade' => [
                'required',
                'string',
                Rule::in(array_keys(GradePredictionCalculator::GRADE_POINTS)),
            ],
        ];
    }
}
