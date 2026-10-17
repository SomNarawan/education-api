<?php

namespace App\Http\Requests\AcademicTerm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceAcademicTermRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preview_token' => ['required', 'string', 'size:64'],
            'confirmation' => ['required', 'string', Rule::in(['ADVANCE_TERM'])],
        ];
    }
}
