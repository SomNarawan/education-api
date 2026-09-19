<?php

namespace App\Http\Requests\ListOfValue;

use App\Enums\ListOfValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOfValueRequest extends FormRequest
{
    public function rules(): array
    {
        $type = $this->listOfValueType();

        return [
            'province_id' => [
                Rule::requiredIf($type === ListOfValueType::Districts),
                Rule::prohibitedIf($type !== ListOfValueType::Districts),
                'integer',
                'exists:provinces,id',
            ],
            'district_id' => [
                Rule::requiredIf($type === ListOfValueType::Subdistricts),
                Rule::prohibitedIf($type !== ListOfValueType::Subdistricts),
                'integer',
                'exists:districts,id',
            ],
            'study_plan_id' => [
                Rule::prohibitedIf($type !== ListOfValueType::SystemTeachers),
                'sometimes',
                'integer',
                'min:1',
            ],
            'curriculum_id' => [
                Rule::requiredIf($type === ListOfValueType::StudyPlans),
                Rule::prohibitedIf($type !== ListOfValueType::StudyPlans),
                'integer',
                'min:1',
            ],
            'include_ids' => [
                'sometimes',
                'array',
            ],
            'include_ids.*' => [
                'integer',
                'min:1',
            ],
        ];
    }

    private function listOfValueType(): ?ListOfValueType
    {
        $type = $this->route('type');

        if ($type instanceof ListOfValueType) {
            return $type;
        }

        return is_string($type) ? ListOfValueType::tryFrom($type) : null;
    }
}
