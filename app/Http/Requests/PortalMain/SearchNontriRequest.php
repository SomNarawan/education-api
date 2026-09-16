<?php

namespace App\Http\Requests\PortalMain;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SearchNontriRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nontriId' => ['sometimes', 'string', 'max:50'],
            'fullName' => ['sometimes', 'string', 'max:255'],
            'agency' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('nontriId') && ! $this->filled('fullName') && ! $this->filled('agency')) {
                $validator->errors()->add('search', 'At least one of nontriId, fullName, or agency is required.');
            }
        });
    }
}
