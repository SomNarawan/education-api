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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'address' => $this->address,

            'teacher' => $this->whenLoaded('teacher', function () {
                $t = $this->teacher;
                return [
                    'id' => $t->id,
                    'title' => $t->whenLoaded('title', function () use ($t) {
                        return $t->title->name ?? null;
                    }, ($t->title->name ?? null) ?? null),
                    'first_name' => $t->first_name ?? null,
                    'last_name' => $t->last_name ?? null,
                ];
            }),

            'student_status' => $this->whenLoaded('studentStatus', function () {
                return [
                    'id' => $this->studentStatus->id,
                    'name' => $this->studentStatus->name ?? null,
                ];
            }),

            'admission_channel' => $this->whenLoaded('admissionChannel', function () {
                return [
                    'id' => $this->admissionChannel->id,
                    'name' => $this->admissionChannel->name ?? null,
                ];
            }),

            'guardian' => ($this->whenLoaded('guardianTitle') || $this->whenLoaded('guardianRelationship')) ? [
                'title' => $this->guardianTitle->name ?? null,
                'first_name' => $this->guardian_first_name ?? null,
                'last_name' => $this->guardian_last_name ?? null,
                'relationship' => $this->guardianRelationship->name ?? null,
            ] : null,

            'high_school' => $this->whenLoaded('highSchool', function () {
                $hs = $this->highSchool;
                $sub = $hs->relationLoaded('subdistrict') && $hs->subdistrict ? $hs->subdistrict : null;
                $dist = $sub && $sub->relationLoaded('district') && $sub->district ? $sub->district : null;
                $prov = $dist && $dist->relationLoaded('province') && $dist->province ? $dist->province : null;

                return [
                    'id' => $hs->id,
                    'name' => $hs->name ?? null,
                    'subdistrict' => $sub ? [
                        'id' => $sub->id,
                        'name' => $sub->name ?? null,
                        'district' => $dist ? [
                            'id' => $dist->id,
                            'name' => $dist->name ?? null,
                            'province' => $prov ? [
                                'id' => $prov->id,
                                'name' => $prov->name ?? null,
                            ] : null,
                        ] : null,
                    ] : null,
                ];
            }),

            'study_plan' => $this->whenLoaded('studyPlan', function () {
                $sp = $this->studyPlan;
                $curr = $sp->curriculum ?? null;
                $dept = $curr ? $curr->department : null;
                $faculty = $dept ? $dept->faculty : null;

                return [
                    'id' => $sp->id,
                    'curriculum' => $curr ? [
                        'id' => $curr->id,
                        'name' => $curr->name ?? null,
                        'department' => $dept ? [
                            'id' => $dept->id,
                            'name' => $dept->name ?? null,
                            'faculty' => $faculty ? [
                                'id' => $faculty->id,
                                'name' => $faculty->name ?? null,
                            ] : null,
                        ] : null,
                    ] : null,
                ];
            }),

            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
