<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class SystemDepartmentResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'th_name' => $this->th_name,
            'en_name' => $this->en_name,
            'th_short_name' => $this->th_short_name,
            'en_short_name' => $this->en_short_name,
            'system_faculty_id' => $this->system_faculty_id,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updated_by,
            'deleted_at' => $this->deleted_at,
            'deleted_by' => $this->deleted_by,
            'sync_id' => $this->sync_id,
        ];
    }
}
