<?php

namespace App\Http\Requests\Student;

use App\Constants\Status;
use App\Rules\ValidStudyPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStudentAdvisorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'study_plan_id' => [
                'required',
                'integer',
                app(ValidStudyPlan::class),
            ],
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('system_teachers', 'id')->where('status', Status::ACTIVE),
            ],
            'assign_student_ids' => ['present', 'array'],
            'assign_student_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
            ],
            'remove_student_ids' => ['present', 'array'],
            'remove_student_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $assignIds = $this->input('assign_student_ids', []);
                $removeIds = $this->input('remove_student_ids', []);

                if (! is_array($assignIds) || ! is_array($removeIds)) {
                    return;
                }

                if ($assignIds === [] && $removeIds === []) {
                    $validator->errors()->add(
                        'student_ids',
                        'At least one student must be assigned or removed.'
                    );
                }

                if (array_intersect($assignIds, $removeIds) !== []) {
                    $validator->errors()->add(
                        'student_ids',
                        'A student cannot be both assigned and removed.'
                    );
                }
            },
        ];
    }
}
