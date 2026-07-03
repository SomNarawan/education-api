<?php

namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class NoteListResponse extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'note' => $this->noteType->note,
            'remark' => $this->remark,
            'created_at' => $this->created_at,
            'created_by' => $this->created_by,
            'deleted_at' => $this->deleted_at,
            'deleted_by' => $this->deleted_by,
        ];
    }
}
