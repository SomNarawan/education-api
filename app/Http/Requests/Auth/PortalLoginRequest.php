<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortalLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'exchangeToken' => ['required', 'string', 'max:8192'],
            'intranetToken' => ['required', 'string', 'max:8192'],
            // The ENG Main tile the user clicked.
            'role' => ['required', 'string', Rule::in(['admin', 'teacher'])],
        ];
    }
}
