<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentAcademicPeriodSyncResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'reference_date' => $this->reference_date?->toDateString(),
            'academic_year' => $this->academic_year,
            'term_code' => $this->term_code,
            'term_name_th' => $this->term_name_th,
            'total_students' => $this->total_students,
            'updated_count' => $this->updated_count,
            'skipped_count' => $this->skipped_count,
            'failed_count' => $this->failed_count,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
        ];
    }
}
