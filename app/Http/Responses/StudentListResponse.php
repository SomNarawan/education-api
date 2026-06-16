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
            'teacher_full_name_th' => $this->teacher->full_name_th,
            'curriculum_type' => $this->studyPlan->curriculum->program->degree_short_th ?? '',
            'study_plan_name' => $this->studyPlan->name_th,
            'required_credits' => $this->studyPlan->curriculum->total_credits_min,
            'passed_credits' => $this->passed_credits,
            'not_passed_credits' => $this->not_passed_credits,
            'overed_credits' => $this->overed_credits,
            'gpa' => $this->gpa
        ];
    }
}
