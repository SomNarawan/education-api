<?php

namespace App\Http\Requests\Title;

use App\Constants\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTitleStatusRequest extends FormRequest
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
