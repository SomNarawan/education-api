<?php

namespace App\Http\Requests\Geography;

use Illuminate\Foundation\Http\FormRequest;

class ListSubdistrictsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'district_id' => ['required', 'integer', 'exists:districts,id'],
        ];
    }
}
