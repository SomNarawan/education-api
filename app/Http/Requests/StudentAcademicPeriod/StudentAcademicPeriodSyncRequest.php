<?php

namespace App\Http\Requests\StudentAcademicPeriod;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class StudentAcademicPeriodSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function referenceDate(): CarbonImmutable
    {
        $timezone = config('app.timezone', 'Asia/Bangkok');
        $referenceDate = $this->validated('reference_date');

        return $referenceDate === null
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::createFromFormat('!Y-m-d', $referenceDate, $timezone);
    }
}
