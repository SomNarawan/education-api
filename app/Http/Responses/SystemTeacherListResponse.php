<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class SystemTeacherListResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nontri_id' => $this->nontri_id,
            'full_name_th' => $this->full_name_th,
            'department_id' => $this->department_id,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updated_by,
            'status' => $this->status,
            'sync_id' => $this->sync_id,
        ];
    }
}
