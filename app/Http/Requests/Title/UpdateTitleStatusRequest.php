<?php

namespace App\Http\Requests\Title;

use App\Models\Title;
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
                Rule::in([
                    Title::STATUS_ACTIVE,
                    Title::STATUS_INACTIVE,
                ]),
            ],
        ];
    }
}
