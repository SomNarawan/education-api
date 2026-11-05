<?php

namespace App\Http\Requests\AcademicCalendar;

use Illuminate\Foundation\Http\FormRequest;

class ListAcademicCalendarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academicYear' => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    public function academicYear(): ?int
    {
        $academicYear = $this->validated('academicYear');

        return $academicYear === null ? null : (int) $academicYear;
    }
}
