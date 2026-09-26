<?php

namespace App\Http\Requests\Student;

use App\Constants\Status;
use App\Contracts\CmisApi;
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
            'first_name_en' => $this->requiredRules(['string', 'max:50']),
            'last_name_en' => $this->requiredRules(['string', 'max:50']),
            'phone' => $this->requiredRules(['string', 'max:10']),
            'email' => $this->requiredRules(['email', 'max:50']),

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
            'system_department_id' => [
                'sometimes',
                'integer',
                Rule::exists('system_departments', 'id')->where('status', Status::ACTIVE),
            ],
            'entry_year' => $this->requiredRules(['integer', 'between:1901,2155']),
            'guardian_title_id' => ['sometimes', 'nullable', 'integer', 'exists:titles,id'],
            'guardian_first_name_th' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guardian_last_name_th' => ['sometimes', 'nullable', 'string', 'max:50'],
            'guardian_relationship_id' => ['sometimes', 'nullable', 'integer', 'exists:relationships,id'],
            'guardian_phone' => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }

    private function requiredRules(array $rules): array
    {
        return [$this->isMethod('post') ? 'required' : 'sometimes', ...$rules];
    }
}
