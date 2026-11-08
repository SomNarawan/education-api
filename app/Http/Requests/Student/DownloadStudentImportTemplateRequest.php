<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class DownloadStudentImportTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'curriculum_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'curriculum_id.required' => 'กรุณาเลือกหลักสูตร',
            'curriculum_id.integer' => 'หลักสูตรไม่ถูกต้อง',
            'curriculum_id.min' => 'หลักสูตรไม่ถูกต้อง',
        ];
    }
}
