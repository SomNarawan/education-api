<?php

namespace App\Http\Requests\PortalMain;

use Illuminate\Foundation\Http\FormRequest;

class SearchNontriByAnyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
