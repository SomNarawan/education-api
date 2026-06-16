<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentDetailResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'title_id' => $this->title_id,
            'full_name_th' => trim(($this->title->title_abbr_th ?? '') . ($this->first_name_th ?? '') . ' ' . ($this->last_name_th ?? '')),
            'full_name_en' => trim(($this->title->title_abbr_en ?? '') . ($this->first_name_en ?? '') . ' ' . ($this->last_name_en ?? '')),
            'first_name_th' => $this->first_name_th,
            'last_name_th' => $this->last_name_th,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'phone' => $this->phone,
            'email' => $this->email,
            'entry_year_ad' => (int) $this->entry_year,
            'entry_year_be' => (int) $this->entry_year + 543,
            'teacher_id' => $this->teacher_id,
            'teacher_full_name_th' => $this->teacher->full_name_th,
            'student_status_id' => $this->student_status_id,
            'student_status_name' => $this->whenLoaded('studentStatus', function () {
                return $this->studentStatus->status_name ?? null;
            }),
            'admission_channel_id' => $this->admission_channel_id,
            'admission_channel_name' => $this->whenLoaded('admissionChannel', function () {
                return $this->admissionChannel->channel_name ?? null;
            }),
            'guardian_full_name' => trim(($this->guardianTitle->title_abbr_th ?? '') . ($this->guardian_first_name_th ?? '') . ' ' . ($this->guardian_last_name_th ?? '')),
            'guardian_relationship_id' => $this->guardian_relationship_id,
            'guardian_relationship_name' => $this->whenLoaded('guardianRelationship', function () {
                return $this->guardianRelationship->relationship_name ?? null;
            }),
            'guardian_phone' => $this->guardian_phone,
            'high_school_id' => $this->high_school_id,
            'high_school_name' => $this->whenLoaded('highSchool', function () {
                return $this->highSchool->school_name ?? null;
            }),
            'high_school_address' => $this->whenLoaded('highSchool', function () {
                $subdistrict = $this->highSchool?->subdistrict;
                $district = $subdistrict?->district;
                $province = $district?->province;

                $subdistrictName = $subdistrict?->subdistrict_name ?? '-';
                $districtName = $district?->district_name ?? '-';
                $provinceName = $province?->province_name ?? '-';
                $postalCode = $subdistrict?->postal_code ?? '-';

                return $provinceName === 'กรุงเทพมหานคร'
                    ? "แขวง{$subdistrictName} เขต{$districtName} {$provinceName} {$postalCode}"
                    : "ตำบล{$subdistrictName} อำเภอ{$districtName} จังหวัด{$provinceName} {$postalCode}";
            }),
            
            'study_plan_id' => $this->study_plan_id,
            'curriculum_type' => $this->studyPlan->curriculum->degree_short_name_th,
            'study_plan_name' => $this->studyPlan->name_th,
            'department_name' => $this->studyPlan->curriculum->department->name_th,
            'faculty_name' => $this->studyPlan->curriculum->department->faculty->name_th,
            'required_credits' => $this->studyPlan->curriculum->total_credits_min,
            'passed_credits' => $this->passed_credits,
            'not_passed_credits' => $this->not_passed_credits,
            'overed_credits' => $this->overed_credits,
            'gpa' => $this->gpa
        ];
    }
}
