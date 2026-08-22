<?php

namespace App\Http\Requests\HighSchool;

use App\Constants\Status;
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
                Rule::in(Status::activeStatuses()),
            ],
        ];
    }
}
