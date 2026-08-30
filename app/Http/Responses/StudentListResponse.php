<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentListResponse extends JsonResource
{
    public function toArray($request): array
    {
        $studyPlan = $this->resource->getAttribute('study_plan_data');

        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'full_name_th' => trim(($this->title?->title_abbr_th ?? '').($this->first_name_th ?? '').' '.($this->last_name_th ?? '')),
            'teacher_id' => $this->teacher_id,
            'teacher_full_name_th' => $this->systemTeacher?->full_name_th,
            'curriculum_type' => $studyPlan['curriculum_type'] ?? '',
            'study_plan_name' => $studyPlan['name_th'] ?? null,
            'curriculum_plan_name' => $studyPlan['name_th'] ?? null,
            'required_credits' => $studyPlan['required_credits'] ?? null,
            'passed_credits' => $this->passed_credits === null ? null : (int) $this->passed_credits,
            'not_passed_credits' => $this->not_passed_credits === null ? null : (int) $this->not_passed_credits,
            'overed_credits' => $this->overed_credits === null ? null : (int) $this->overed_credits,
            'gpa' => $this->gpa === null ? null : (float) $this->gpa,
            'gpax' => $this->gpax === null ? null : (float) $this->gpax,
        ];
    }
}
