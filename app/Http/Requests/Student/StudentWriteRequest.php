<?php

namespace App\Http\Requests\Student;

use App\Constants\HttpStatus;
use App\Constants\Status;
use App\Constants\StudySemester;
use App\Contracts\CmisApi;
use App\Helpers\ApiResponse;
use App\Rules\ValidStudyPlan;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StudentWriteRequest extends FormRequest
{
    public function rules(): array
    {
        $studentId = $this->route('id');

        return [
            'student_code' => $this->requiredRules([
                'string',
                'max:10',
                'regex:/^\d+$/',
                Rule::unique('students', 'student_code')
                    ->ignore($studentId),
            ]),
            'student_id_card' => [
                'sometimes',
                'nullable',
                'string',
                'max:13',
                Rule::unique('students', 'student_id_card')
                    ->ignore($studentId),
            ],
            'title_id' => $this->requiredRules(['integer', 'exists:titles,id']),
            'first_name_th' => $this->requiredRules(['string', 'max:50']),
            'last_name_th' => $this->requiredRules(['string', 'max:50']),
            'first_name_en' => ['sometimes', 'nullable', 'string', 'max:50'],
            'last_name_en' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:10'],
            'email' => ['sometimes', 'nullable', 'email', 'max:50'],

            'teacher_id' => [
                'sometimes',
                'nullable',
                'required_with:teacher_full_name',
                'string',
                'max:50',
            ],
            'teacher_full_name' => [
                'sometimes',
                'nullable',
                'required_with:teacher_id',
                'string',
                'max:255',
            ],
            'student_status_id' => $this->requiredRules(['integer', 'exists:student_statuses,id']),
            'admission_channel_id' => $this->requiredRules(['integer', 'exists:admission_channels,id']),
            'high_school_id' => ['sometimes', 'nullable', 'integer', 'exists:high_schools,id'],
            'curriculum_id' => $this->requiredRules(['integer', 'min:1']),
            'curriculum_code' => $this->requiredRules(['string', 'max:255']),
            'study_plan_id' => $this->requiredRules([
                'integer',
                new ValidStudyPlan(
                    app(CmisApi::class),
                    $this->filled('curriculum_id')
                        ? (int) $this->input('curriculum_id')
                        : null,
                ),
            ]),
            'study_plan_name_th' => $this->requiredRules(['string', 'max:255']),
            'system_department_id' => $this->requiredRules([
                'integer',
                Rule::exists('system_departments', 'id')->where('status', Status::ACTIVE),
            ]),
            'entry_year' => $this->requiredRules(['integer', 'between:1901,2155']),
            'study_year' => $this->requiredRules(['integer', 'min:1']),
            'study_semester' => $this->requiredRules([
                'integer',
                Rule::in(StudySemester::values()),
            ]),
            'guardian_title_id' => ['sometimes', 'nullable', 'integer', 'exists:titles,id'],
            'guardian_first_name_th' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guardian_last_name_th' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guardian_relationship_id' => ['sometimes', 'nullable', 'integer', 'exists:relationships,id'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'กรุณาระบุ :attribute',
            'required_with' => 'กรุณาระบุ :attribute เมื่อมีการระบุ :values',
            'string' => ':attribute ต้องเป็นข้อความ',
            'integer' => ':attribute ต้องเป็นจำนวนเต็ม',
            'max.string' => ':attribute ต้องยาวไม่เกิน :max ตัวอักษร',
            'min.numeric' => ':attribute ต้องมีค่าอย่างน้อย :min',
            'between.numeric' => ':attribute ต้องมีค่าระหว่าง :min ถึง :max',
            'email' => 'รูปแบบ :attribute ไม่ถูกต้อง',
            'exists' => 'ไม่พบ :attribute ที่เลือก',
            'in' => ':attribute ที่เลือกไม่ถูกต้อง',
            'student_code.regex' => 'รหัสนิสิตต้องเป็นตัวเลขเท่านั้น',
            'student_code.unique' => 'รหัสนิสิตนี้มีอยู่ในระบบแล้ว',
            'student_id_card.unique' => 'เลขประจำตัวประชาชนนี้มีอยู่ในระบบแล้ว',
        ];
    }

    public function attributes(): array
    {
        return [
            'student_code' => 'รหัสนิสิต',
            'student_id_card' => 'เลขประจำตัวประชาชน',
            'title_id' => 'คำนำหน้า',
            'first_name_th' => 'ชื่อภาษาไทย',
            'last_name_th' => 'นามสกุลภาษาไทย',
            'first_name_en' => 'ชื่อภาษาอังกฤษ',
            'last_name_en' => 'นามสกุลภาษาอังกฤษ',
            'phone' => 'เบอร์โทรศัพท์',
            'email' => 'อีเมล',
            'teacher_id' => 'รหัสอาจารย์ที่ปรึกษา',
            'teacher_full_name' => 'ชื่ออาจารย์ที่ปรึกษา',
            'student_status_id' => 'สถานภาพนิสิต',
            'admission_channel_id' => 'ช่องทางการรับเข้า',
            'high_school_id' => 'โรงเรียนเดิม',
            'curriculum_id' => 'หลักสูตร',
            'curriculum_code' => 'รหัสหลักสูตร',
            'study_plan_id' => 'แผนการเรียน',
            'study_plan_name_th' => 'ชื่อแผนการเรียน',
            'system_department_id' => 'ภาควิชา',
            'entry_year' => 'ปีที่เข้าศึกษา',
            'study_year' => 'ชั้นปี',
            'study_semester' => 'ภาคการศึกษา',
            'guardian_title_id' => 'คำนำหน้าผู้ปกครอง',
            'guardian_first_name_th' => 'ชื่อผู้ปกครอง',
            'guardian_last_name_th' => 'นามสกุลผู้ปกครอง',
            'guardian_relationship_id' => 'ความสัมพันธ์ผู้ปกครอง',
            'guardian_phone' => 'เบอร์โทรศัพท์ผู้ปกครอง',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                'ข้อมูลนิสิตไม่ถูกต้อง',
                HttpStatus::UNPROCESSABLE_ENTITY['code'],
                $validator->errors()->toArray(),
            ),
        );
    }

    private function requiredRules(array $rules): array
    {
        return [$this->isMethod('post') ? 'required' : 'sometimes', ...$rules];
    }
}
