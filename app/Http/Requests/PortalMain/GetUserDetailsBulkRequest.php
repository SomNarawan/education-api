<?php

namespace App\Http\Requests\PortalMain;

use Illuminate\Foundation\Http\FormRequest;

class GetUserDetailsBulkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nontriIds' => ['required', 'array', 'min:1'],
            'nontriIds.*' => ['required', 'string', 'max:50'],
        ];
    }
}
