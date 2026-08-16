<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'กรุณาแนบไฟล์ Excel',
            'file.file' => 'ไฟล์ที่แนบไม่ถูกต้อง',
            'file.mimes' => 'รองรับเฉพาะไฟล์ .xlsx',
            'file.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 20 MB',
        ];
    }
}
