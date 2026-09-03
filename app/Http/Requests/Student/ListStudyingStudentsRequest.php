<?php

namespace App\Http\Requests\Student;

use App\Rules\ValidStudyPlan;
use Illuminate\Foundation\Http\FormRequest;

class ListStudyingStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'min:1'],
            'study_plan_id' => [
                'required',
                'integer',
                app(ValidStudyPlan::class),
            ],
        ];
    }
}
