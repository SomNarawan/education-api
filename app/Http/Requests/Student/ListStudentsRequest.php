<?php

namespace App\Http\Requests\Student;

use App\Models\NoteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => ['sometimes', 'string', 'max:50'],
            'department_id' => ['sometimes', 'integer', 'min:1'],
            'faculty_id' => ['sometimes', 'integer', 'min:1'],
            'student_status_id' => ['sometimes', 'integer', 'min:1'],
            'search_text' => ['sometimes', 'string', 'max:255'],
            'search_note_type_id' => ['sometimes', 'integer', 'exists:note_types,id'],
            'search_note' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    NoteType::isOther($this->integer('search_note_type_id')) &&
                    blank($this->input('search_note'))
                ) {
                    $validator->errors()->add('search_note', 'กรุณากรอกรายละเอียด Note');
                }
            },
        ];
    }
}
