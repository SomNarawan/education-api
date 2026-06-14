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
            'full_name_th' => trim(($this->title->title_abbr_th ?? '') . ($this->first_name_th ?? '') . ' ' . ($this->last_name_th ?? '')),
            'teacher_id' => $this->teacher_id,
            'curriculum_type' => $this->studyPlan->curriculum->degree_short_name_th . ' (' . ($this->studyPlan->name_th ?? '') . ')',
            'credits_required' => $this->studyPlan->curriculum->total_credits_min,
            'pass_credits' => $this->earned_credits,
            'not_pass_credits' => 0,
            'over_credits' => 0,
            'gpa' => $this->gpa
        ];
    }
}
