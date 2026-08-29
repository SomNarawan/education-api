<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class SyncResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sync_type' => $this->sync_type,
            'sync_type_name' => $this->whenHas('sync_type_name'),
            'inserted_count' => $this->inserted_count,
            'updated_count' => $this->updated_count,
            'inactivated_count' => $this->inactivated_count,
            'skipped_count' => $this->skipped_count,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updated_by,
        ];
    }
}
