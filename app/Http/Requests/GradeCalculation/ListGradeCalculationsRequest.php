<?php

namespace App\Http\Requests\GradeCalculation;

use Illuminate\Foundation\Http\FormRequest;

class ListGradeCalculationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['sometimes', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
