<?php

namespace App\Http\Requests\Student;

use App\Constants\Status;
use App\Rules\ValidStudyPlan;
use Illuminate\Foundation\Http\FormRequest;
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
                Rule::unique('students', 'student_code')
                    ->ignore($studentId),
            ]),
            'student_id_card' => $this->requiredRules([
                'string',
                'max:13',
                Rule::unique('students', 'student_id_card')
                    ->ignore($studentId),
            ]),
            'title_id' => $this->requiredRules(['integer', 'exists:titles,id']),
            'first_name_th' => $this->requiredRules(['string', 'max:50']),
            'last_name_th' => $this->requiredRules(['string', 'max:50']),
            'first_name_en' => $this->requiredRules(['string', 'max:50']),
            'last_name_en' => $this->requiredRules(['string', 'max:50']),
            'phone' => $this->requiredRules(['string', 'max:10']),
            'email' => $this->requiredRules(['email', 'max:50']),

            'teacher_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('system_teachers', 'id')->where('status', Status::ACTIVE),
            ],
            'student_status_id' => $this->requiredRules(['integer', 'exists:student_statuses,id']),
            'admission_channel_id' => $this->requiredRules(['integer', 'exists:admission_channels,id']),
            'high_school_id' => $this->requiredRules(['integer', 'exists:high_schools,id']),
            'study_plan_id' => $this->requiredRules(['integer', app(ValidStudyPlan::class)]),
            'department_id' => [
                'sometimes',
                'integer',
                Rule::exists('system_departments', 'id')->where('status', Status::ACTIVE),
            ],
            'entry_year' => $this->requiredRules(['integer', 'between:1901,2155']),
            'study_year' => ['sometimes', 'integer', 'min:1'],
            'study_semester' => ['sometimes', 'integer', 'between:1,3'],
            'study_period' => ['sometimes', 'string', 'max:100'],
            'guardian_title_id' => $this->requiredRules(['integer', 'exists:titles,id']),
            'guardian_first_name_th' => $this->requiredRules(['string', 'max:50']),
            'guardian_last_name_th' => $this->requiredRules(['string', 'max:50']),
            'guardian_relationship_id' => $this->requiredRules(['integer', 'exists:relationships,id']),
            'guardian_phone' => $this->requiredRules(['string', 'max:10']),
        ];
    }

    private function requiredRules(array $rules): array
    {
        return [$this->isMethod('post') ? 'required' : 'sometimes', ...$rules];
    }
}
