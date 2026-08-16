<?php

namespace App\Http\Requests\HighSchool;

use App\Models\HighSchool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHighSchoolStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    HighSchool::STATUS_ACTIVE,
                    HighSchool::STATUS_INACTIVE,
                ]),
            ],
        ];
    }
}
