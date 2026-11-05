<?php

namespace App\Http\Requests\PortalMain;

use Illuminate\Foundation\Http\FormRequest;

class GetUserDetailsBulkRequest extends FormRequest
{
    public const MAX_NONTRI_IDS = 100;

    public function rules(): array
    {
        return [
            'nontriIds' => ['required', 'array', 'min:1', 'max:'.self::MAX_NONTRI_IDS],
            'nontriIds.*' => ['required', 'string', 'max:50'],
        ];
    }
}
