<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ListStudyingStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
