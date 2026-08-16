<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentListResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'full_name_th' => trim(($this->title?->title_abbr_th ?? '').($this->first_name_th ?? '').' '.($this->last_name_th ?? '')),
            'teacher_id' => $this->teacher_id,
            'teacher_full_name_th' => $this->teacher?->full_name_th,
            'curriculum_type' => $this->curriculumPlan?->curriculum?->degree_short_th ?? '',
            'study_plan_name' => $this->curriculumPlan?->name_th,
            'curriculum_plan_name' => $this->curriculumPlan?->name_th,
            'required_credits' => $this->curriculumPlan?->curriculum?->total_credits_min,
            'passed_credits' => (int) ($this->passed_credits ?? 0),
            'not_passed_credits' => (int) ($this->not_passed_credits ?? 0),
            'overed_credits' => (int) ($this->overed_credits ?? 0),
            'gpa' => (float) $this->gpa,
            'gpax' => (float) $this->gpax,
        ];
    }
}
