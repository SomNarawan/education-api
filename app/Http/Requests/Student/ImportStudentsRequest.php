<?php

namespace App\Http\Requests\Student;

use App\Rules\ValidStudyPlan;
use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'curriculum_id' => ['required', 'integer'],
            'study_plan_id' => ['required', 'integer', app(ValidStudyPlan::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'กรุณาแนบไฟล์ Excel',
            'file.file' => 'ไฟล์ที่แนบไม่ถูกต้อง',
            'file.mimes' => 'รองรับเฉพาะไฟล์ .xlsx',
            'file.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 20 MB',
            'curriculum_id.required' => 'กรุณาเลือกหลักสูตร',
            'curriculum_id.integer' => 'หลักสูตรไม่ถูกต้อง',
            'study_plan_id.required' => 'กรุณาเลือกแผนการเรียน',
            'study_plan_id.integer' => 'แผนการเรียนไม่ถูกต้อง',
        ];
    }
}
