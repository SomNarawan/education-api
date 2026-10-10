<?php

namespace App\Http\Requests\Student;

use App\Constants\HttpStatus;
use App\Constants\StudySemester;
use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ResetStudentGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claims = $this->attributes->get('jwt_claims', []);
        $roles = $claims['role'] ?? [];
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array('admin', $roles, true);
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in(['all', 'semester'])],
            'study_year' => [
                'required_if:scope,semester',
                'prohibited_unless:scope,semester',
                'integer',
                'min:1',
            ],
            'semester' => [
                'required_if:scope,semester',
                'prohibited_unless:scope,semester',
                'integer',
                Rule::in(StudySemester::values()),
            ],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                'Only administrators can reset student grades',
                HttpStatus::FORBIDDEN['code'],
            ),
        );
    }
}
