<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class HighSchoolListResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'school_name' => $this->school_name,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at,
            'status' => $this->status,
        ];
    }
}
