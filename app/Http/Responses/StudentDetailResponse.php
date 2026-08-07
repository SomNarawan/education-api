<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentDetailResponse extends JsonResource
{
    public function toArray($request): array
    {
        $subdistrict = $this->highSchool?->subdistrict;
        $district = $subdistrict?->district;
        $province = $district?->province;

        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'student_id_card' => $this->student_id_card,
            'title_id' => $this->title_id,
            'full_name_th' => trim(($this->title?->title_abbr_th ?? '').($this->first_name_th ?? '').' '.($this->last_name_th ?? '')),
            'full_name_en' => trim(($this->title?->title_abbr_en ?? '').($this->first_name_en ?? '').' '.($this->last_name_en ?? '')),
            'first_name_th' => $this->first_name_th,
            'last_name_th' => $this->last_name_th,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'phone' => $this->phone,
            'email' => $this->email,
            'entry_year' => (int) $this->entry_year,
            'entry_year_be' => (int) $this->entry_year + 543,
            'study_year' => (int) $this->study_year,
            'study_semester' => (int) $this->study_semester,
            'study_period' => $this->study_period,
            'current_year' => (int) $this->study_year,
            'current_semester' => (int) $this->study_semester,
            'teacher_id' => $this->teacher_id,
            'teacher_full_name_th' => $this->teacher?->full_name_th,
            'student_status_id' => $this->student_status_id,
            'student_status_name' => $this->studentStatus?->status_name,
            'admission_channel_id' => $this->admission_channel_id,
            'admission_channel_name' => $this->admissionChannel?->channel_name,
            'guardian_title_id' => $this->guardian_title_id,
            'guardian_first_name_th' => $this->guardian_first_name_th,
            'guardian_last_name_th' => $this->guardian_last_name_th,
            'guardian_full_name' => trim(($this->guardianTitle?->title_abbr_th ?? '').($this->guardian_first_name_th ?? '').' '.($this->guardian_last_name_th ?? '')),
            'guardian_relationship_id' => $this->guardian_relationship_id,
            'guardian_relationship_name' => $this->guardianRelationship?->relationship_name,
            'guardian_phone' => $this->guardian_phone,
            'high_school_id' => $this->high_school_id,
            'high_school_name' => $this->highSchool?->school_name,
            'high_school_address' => $this->formatAddress(
                $subdistrict?->subdistrict_name,
                $district?->district_name,
                $province?->province_name,
                $subdistrict?->postal_code,
            ),
            'study_plan_id' => $this->study_plan_id,
            'curriculum_type' => $this->curriculumPlan?->curriculum?->degree_short_th,
            'study_plan_name' => $this->curriculumPlan?->name_th,
            'curriculum_plan_name' => $this->curriculumPlan?->name_th,
            'department_id' => $this->system_department_id,
            'department_name' => $this->systemDepartment?->th_name,
            'faculty_id' => $this->systemDepartment?->system_faculty_id,
            'faculty_name' => $this->systemDepartment?->systemFaculty?->th_name,
            'required_credits' => $this->curriculumPlan?->curriculum?->total_credits_min,
            'passed_credits' => (int) ($this->passed_credits ?? 0),
            'not_passed_credits' => (int) ($this->not_passed_credits ?? 0),
            'overed_credits' => (int) ($this->overed_credits ?? 0),
            'gpa' => (float) $this->gpa,
        ];
    }

    private function formatAddress(
        ?string $subdistrict,
        ?string $district,
        ?string $province,
        ?string $postalCode,
    ): ?string {
        if ($subdistrict === null && $district === null && $province === null) {
            return null;
        }

        $subdistrict ??= '-';
        $district ??= '-';
        $province ??= '-';
        $postalCode ??= '-';

        return $province === 'กรุงเทพมหานคร'
            ? "แขวง{$subdistrict} เขต{$district} {$province} {$postalCode}"
            : "ตำบล{$subdistrict} อำเภอ{$district} จังหวัด{$province} {$postalCode}";
    }
}
