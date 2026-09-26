<?php

namespace App\Http\Requests\Student;

use App\Constants\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportStudentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'system_department_id' => [
                'required',
                'integer',
                Rule::exists('system_departments', 'id')->where('status', Status::ACTIVE),
            ],
            'curriculum_id' => ['required', 'integer', 'min:1'],
            'curriculum_code' => ['required', 'string', 'max:255'],
            'study_plan_id' => ['required', 'integer', 'min:1'],
            'study_plan_name_th' => ['required', 'string', 'max:255'],
            'teacher_id' => ['required', 'string', 'max:50'],
            'teacher_full_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'กรุณาแนบไฟล์ Excel',
            'file.file' => 'ไฟล์ที่แนบไม่ถูกต้อง',
            'file.mimes' => 'รองรับเฉพาะไฟล์ .xlsx',
            'file.max' => 'ไฟล์ต้องมีขนาดไม่เกิน 20 MB',
            'system_department_id.required' => 'กรุณาเลือกภาควิชา',
            'system_department_id.integer' => 'ภาควิชาไม่ถูกต้อง',
            'system_department_id.exists' => 'ไม่พบภาควิชาที่เปิดใช้งาน',
            'curriculum_id.required' => 'กรุณาเลือกหลักสูตร',
            'curriculum_id.integer' => 'หลักสูตรไม่ถูกต้อง',
            'curriculum_id.min' => 'หลักสูตรไม่ถูกต้อง',
            'curriculum_code.required' => 'กรุณาระบุรหัสหลักสูตร',
            'study_plan_id.required' => 'กรุณาเลือกแผนการเรียน',
            'study_plan_id.integer' => 'แผนการเรียนไม่ถูกต้อง',
            'study_plan_id.min' => 'แผนการเรียนไม่ถูกต้อง',
            'study_plan_name_th.required' => 'กรุณาระบุชื่อแผนการเรียน',
            'teacher_id.required' => 'กรุณาเลือกอาจารย์ที่ปรึกษา',
            'teacher_full_name.required' => 'กรุณาระบุชื่ออาจารย์ที่ปรึกษา',
        ];
    }
}
