<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class DataImportResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'import_type_id' => $this->import_type_id,
            'type' => $this->importType?->type,
            'file_name' => $this->file_name,
            'status' => $this->status,
            'total_count' => $this->total_count,
            'success_count' => $this->success_count,
            'failed_count' => $this->failed_count,
            'error_message' => $this->error_message,
            'imported_by' => $this->imported_by,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
