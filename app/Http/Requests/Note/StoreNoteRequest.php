<?php

namespace App\Http\Requests\Note;

use App\Models\NoteType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'note_type_id' => ['required', 'integer', 'exists:note_types,id'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    NoteType::isOther($this->integer('note_type_id')) &&
                    blank($this->input('remark'))
                ) {
                    $validator->errors()->add('remark', 'กรุณากรอกรายละเอียด');
                }
            },
        ];
    }
}
