<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class GradeCalculationResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'student' => [
                'id' => (int) $this->student_id,
                'student_code' => (string) $this->student?->student_code,
                'full_name_th' => trim(
                    ($this->student?->first_name_th ?? '')
                    .' '
                    .($this->student?->last_name_th ?? '')
                ),
            ],
            'study_year' => (int) $this->study_year,
            'semester' => $this->semester,
            'semester_order' => $this->semester_order === null
                ? null
                : (int) $this->semester_order,
            'current_gpax' => (float) $this->current_gpax,
            'current_credits' => (int) $this->current_credits,
            'term_gpa' => (float) $this->term_gpa,
            'term_credits' => (int) $this->term_credits,
            'cumulative_gpa' => (float) $this->cumulative_gpa,
            'cumulative_credits' => (int) $this->cumulative_credits,
            'courses' => $this->courses,
            'created_at' => $this->created_at,
        ];
    }
}
