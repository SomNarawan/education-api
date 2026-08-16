<?php

namespace App\Http\Requests\HighSchool;

use Illuminate\Foundation\Http\FormRequest;

class HighSchoolWriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:150'],
            'subdistrict_id' => ['required', 'integer', 'exists:subdistricts,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
