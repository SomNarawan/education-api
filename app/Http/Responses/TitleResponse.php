<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class TitleResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'title_abbr_th' => $this->title_abbr_th,
            'title_abbr_en' => $this->title_abbr_en,
            'title_name_th' => $this->title_name_th,
            'title_name_en' => $this->title_name_en,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updated_by,
        ];
    }
}
