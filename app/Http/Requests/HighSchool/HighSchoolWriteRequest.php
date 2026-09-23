<?php

namespace App\Http\Requests\HighSchool;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HighSchoolWriteRequest extends FormRequest
{
    public function rules(): array
    {
        $highSchoolId = $this->route('id');

        return [
            'school_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('high_schools', 'school_name')->ignore($highSchoolId),
            ],
            'subdistrict_id' => ['required', 'integer', 'exists:subdistricts,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
