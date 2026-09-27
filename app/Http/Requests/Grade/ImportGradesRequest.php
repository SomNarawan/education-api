<?php

namespace App\Http\Requests\Grade;

use Illuminate\Foundation\Http\FormRequest;

class ImportGradesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx', 'max:20480'],
            'curriculum_id' => ['required', 'integer', 'min:1'],
            'curriculum_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'กรุณาแนบไฟล์ผลการเรียน',
            'file.file' => 'ไฟล์ที่แนบไม่ถูกต้อง',
            'file.extensions' => 'รองรับเฉพาะไฟล์ .xlsx',
            'file.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 20 MB',
            'curriculum_id.required' => 'กรุณาเลือกหลักสูตร',
            'curriculum_id.integer' => 'หลักสูตรไม่ถูกต้อง',
            'curriculum_id.min' => 'หลักสูตรไม่ถูกต้อง',
            'curriculum_name.required' => 'กรุณาระบุชื่อหลักสูตร',
        ];
    }
}
