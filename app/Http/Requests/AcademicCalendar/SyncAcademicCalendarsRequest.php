<?php

namespace App\Http\Requests\AcademicCalendar;

use Illuminate\Foundation\Http\FormRequest;

class SyncAcademicCalendarsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_years' => ['required', 'array', 'min:1', 'max:10'],
            'academic_years.*' => [
                'required',
                'integer',
                'distinct',
                'between:2000,2100',
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function academicYears(): array
    {
        return collect($this->validated('academic_years'))
            ->map(fn (mixed $year): int => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
