<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentWithoutAdvisorResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'full_name_th' => trim(($this->title->title_abbr_th ?? '').($this->first_name_th ?? '').' '.($this->last_name_th ?? '')),
        ];
    }
}
