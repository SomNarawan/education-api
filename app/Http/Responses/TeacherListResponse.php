<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class TeacherListResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nontri_id' => $this->nontri_id,
            'full_name_th' => $this->full_name_th,
            'department_id' => $this->department_id,
        ];
    }
}
