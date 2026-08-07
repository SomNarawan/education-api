<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class ListNotesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ];
    }
}
