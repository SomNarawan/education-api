<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['sometimes', 'integer', 'min:1'],
            'department_id' => ['sometimes', 'integer', 'min:1'],
            'faculty_id' => ['sometimes', 'integer', 'min:1'],
            'student_status_id' => ['sometimes', 'integer', 'min:1'],
            'search_text' => ['sometimes', 'string', 'max:255'],
            'search_note' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
